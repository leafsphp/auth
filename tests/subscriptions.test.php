<?php

use Leaf\Billing\Subscription;

/*
 * Tests for the UsesSubscriptions trait against leafs/billing v5.
 * billing() resolves a fake driver (no provider API calls); subscription
 * rows are written straight to the subscriptions table.
 */

const SUBSCRIPTION_TIERS = [
    'price_basic' => ['id' => 'price_basic', 'name' => 'Basic', 'billingPeriod' => 'monthly'],
];

class FakeBillingDriver
{
    public static $cancelled = [];
    public static $resumed = [];

    public function __construct($config = [])
    {
    }

    public function tier(string $id): ?array
    {
        return SUBSCRIPTION_TIERS[$id] ?? null;
    }

    public function tiers(?string $billingPeriod = null): array
    {
        return SUBSCRIPTION_TIERS;
    }

    public function cancelSubscription(string $id, bool $atPeriodEnd = true): bool
    {
        static::$cancelled[] = [$id, $atPeriodEnd];

        return true;
    }

    public function resumeSubscription(string $id): bool
    {
        static::$resumed[] = $id;

        return true;
    }
}

class_alias(FakeBillingDriver::class, 'Leaf\\Billing\\Fake');


if (!class_exists('Leaf\\Config')) {
    eval('namespace Leaf; class Config {
        protected static $items = [];
        public static function getStatic($key) { return static::$items[$key] ?? null; }
        public static function singleton($key, $callback) { static::$items[$key] = $callback(); }
        public static function get($key) { return static::$items[$key] ?? null; }
    }');
}

function subscriptionUser(): \Leaf\Auth\User
{
    $auth = authInstance();
    $auth->config(['session' => false]);
    $auth->register([
        'username' => 'sub-user',
        'email' => 'sub-user@example.com',
        'password' => 'password',
    ]);

    return $auth->user();
}

function seedUserSubscription(int $userId, array $overrides = []): void
{
    dbInstance()->insert('subscriptions')->params(array_merge([
        'user_id' => $userId,
        'name' => 'Basic',
        'plan_id' => 'price_basic',
        'subscription_id' => 'sub_1',
        'status' => Subscription::STATUS_ACTIVE,
        'start_date' => date('Y-m-d H:i:s'),
        'end_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
        'trial_ends_at' => null,
    ], $overrides))->execute();
}

beforeAll(function () {
    // point the db() singleton (leafs/db ships it since leaf core is a
    // transitive dev dep) at the shared test connection
    db()->connection(dbInstance()->connection());

    billing([
        'default' => 'fake',
        'connections' => ['fake' => ['driver' => 'fake']],
    ]);

    dbInstance()->query('CREATE TABLE IF NOT EXISTS subscriptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        name TEXT,
        plan_id TEXT,
        payment_session_id TEXT,
        subscription_id TEXT,
        status TEXT,
        start_date TIMESTAMP,
        end_date TIMESTAMP,
        trial_ends_at TIMESTAMP
    )')->execute();

    createTableForUsers();
});

beforeEach(function () {
    dbInstance()->delete('subscriptions')->execute();
    deleteUser('sub-user');
    FakeBillingDriver::$cancelled = [];
    FakeBillingDriver::$resumed = [];
});

test('a user with no subscription rows has no subscription', function () {
    $user = subscriptionUser();

    expect($user->subscription())->toBeNull();
    expect($user->hasSubscription())->toBeFalse();
    expect($user->hasActiveSubscription())->toBeFalse();
});

test('an active subscription is returned with its tier attached', function () {
    $user = subscriptionUser();
    seedUserSubscription($user->id());

    expect($user->hasActiveSubscription())->toBeTrue();
    expect($user->subscription()['tier']['name'])->toBe('Basic');
});

test('the latest subscription wins after a resubscribe', function () {
    $user = subscriptionUser();
    seedUserSubscription($user->id(), [
        'subscription_id' => 'sub_old',
        'status' => Subscription::STATUS_CANCELLED,
        'end_date' => date('Y-m-d H:i:s', strtotime('-1 month')),
    ]);
    seedUserSubscription($user->id(), ['subscription_id' => 'sub_new']);

    expect($user->subscription()['subscription_id'])->toBe('sub_new');
    expect($user->hasActiveSubscription())->toBeTrue();
});

test('a cancelled subscription keeps access through the grace period', function () {
    $user = subscriptionUser();
    seedUserSubscription($user->id(), [
        'status' => Subscription::STATUS_CANCELLED,
        'end_date' => date('Y-m-d H:i:s', strtotime('+10 days')),
    ]);

    expect($user->onGracePeriod())->toBeTrue();
    expect($user->hasActiveSubscription())->toBeTrue();
    expect($user->hasSubscription())->toBeFalse();
});

test('a cancelled subscription past its end date has no access', function () {
    $user = subscriptionUser();
    seedUserSubscription($user->id(), [
        'status' => Subscription::STATUS_CANCELLED,
        'end_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
    ]);

    expect($user->onGracePeriod())->toBeFalse();
    expect($user->hasActiveSubscription())->toBeFalse();
});

test('trial state is reported from status or trial_ends_at', function () {
    $user = subscriptionUser();
    seedUserSubscription($user->id(), [
        'status' => Subscription::STATUS_TRIAL,
        'trial_ends_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
    ]);

    expect($user->onTrial())->toBeTrue();
    expect($user->hasActiveSubscription())->toBeTrue();
});

test('past due subscriptions lose access but are flagged for dunning', function () {
    $user = subscriptionUser();
    seedUserSubscription($user->id(), ['status' => Subscription::STATUS_PAST_DUE]);

    expect($user->hasPastDueSubscription())->toBeTrue();
    expect($user->hasActiveSubscription())->toBeFalse();
});

test('cancelSubscription defaults to period end and can cancel immediately', function () {
    $user = subscriptionUser();
    seedUserSubscription($user->id());

    expect($user->cancelSubscription())->toBeTrue();
    expect(FakeBillingDriver::$cancelled)->toBe([['sub_1', true]]);

    expect($user->cancelSubscription(false))->toBeTrue();
    expect(FakeBillingDriver::$cancelled[1])->toBe(['sub_1', false]);
});

test('resumeSubscription only works during the grace period', function () {
    $user = subscriptionUser();
    seedUserSubscription($user->id(), [
        'status' => Subscription::STATUS_CANCELLED,
        'end_date' => date('Y-m-d H:i:s', strtotime('+10 days')),
    ]);

    expect($user->resumeSubscription())->toBeTrue();
    expect(FakeBillingDriver::$resumed)->toBe(['sub_1']);
});

test('resumeSubscription refuses when the subscription is fully over', function () {
    $user = subscriptionUser();
    seedUserSubscription($user->id(), [
        'status' => Subscription::STATUS_CANCELLED,
        'end_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
    ]);

    expect($user->resumeSubscription())->toBeFalse();
    expect(FakeBillingDriver::$resumed)->toBe([]);
});
