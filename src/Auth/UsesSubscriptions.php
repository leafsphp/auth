<?php

namespace Leaf\Auth;

use Leaf\Billing\Subscription;

/**
 * Functionality for user subscriptions
 * ----
 * Addition to user class
 *
 * @version 0.2.0
 * @since 4.1.0
 */
trait UsesSubscriptions
{
    /**
     * User Subscription
     * @var array<string, mixed>|null
     */
    protected $subscription = null;

    /**
     * Get current subscription
     *
     * The most recent subscription is returned, so a resubscribed user
     * gets their new subscription rather than an older cancelled one.
     *
     * @return mixed[]|null
     */
    public function subscription(): ?array
    {
        if (billing()->tiers() === []) {
            return null;
        }

        if (
            !$this->subscription && (
                $this->subscription = db()
                    ->select('subscriptions')
                    ->where('user_id', $this->id())
                    ->last()
            )
        ) {
            $this->subscription['tier'] = billing()->tier($this->subscription['plan_id']);
        }

        return $this->subscription ? $this->subscription : null;
    }

    /**
     * Check if user has a subscription
     * @return bool
     */
    public function hasSubscription(): bool
    {
        return $this->subscription() && $this->subscription['status'] !== Subscription::STATUS_CANCELLED;
    }

    /**
     * Check if user has an active subscription
     *
     * Users on a trial, and users who cancelled but are still inside the
     * period they paid for (grace period), still count as active.
     *
     * @return bool
     */
    public function hasActiveSubscription(): bool
    {
        if (!$this->subscription()) {
            return false;
        }

        $status = $this->subscription['status'];

        if ($status === Subscription::STATUS_ACTIVE || $status === Subscription::STATUS_TRIAL) {
            return true;
        }

        return $status === Subscription::STATUS_CANCELLED && $this->onGracePeriod();
    }

    /**
     * Check if user is actively subscribed to a specific plan
     *
     * Matches on the subscription name or on the billing tier's name or id,
     * so `isSubscribedTo('Starter')` works whether you named the
     * subscription after the tier or not.
     *
     * @param string $plan The plan name or tier id to check
     * @return bool
     */
    public function isSubscribedTo(string $plan): bool
    {
        if (!$this->hasActiveSubscription()) {
            return false;
        }

        $subscription = $this->subscription();

        if (($subscription['name'] ?? null) === $plan) {
            return true;
        }

        $tier = $subscription['tier'] ?? null;

        if ($tier instanceof \Leaf\Billing\Tier) {
            $tier = $tier->toArray();
        }

        if (!is_array($tier)) {
            return false;
        }

        return ($tier['name'] ?? null) === $plan || ($tier['id'] ?? null) === $plan;
    }

    /**
     * Check if user is on a trial
     * @return bool
     */
    public function onTrial(): bool
    {
        if (!$this->subscription()) {
            return false;
        }

        if ($this->subscription['status'] === Subscription::STATUS_TRIAL) {
            return true;
        }

        $trialEndsAt = $this->subscription['trial_ends_at'] ?? null;

        return $trialEndsAt && strtotime($trialEndsAt) > time();
    }

    /**
     * Check if user cancelled but still has access until the period ends
     * @return bool
     */
    public function onGracePeriod(): bool
    {
        if (!$this->subscription()) {
            return false;
        }

        $endDate = $this->subscription['end_date'] ?? null;

        return $this->subscription['status'] === Subscription::STATUS_CANCELLED
            && $endDate
            && strtotime($endDate) > time();
    }

    /**
     * Check if subscription payment is past due (renewal failed, in dunning)
     * @return bool
     */
    public function hasPastDueSubscription(): bool
    {
        return $this->subscription() && $this->subscription['status'] === Subscription::STATUS_PAST_DUE;
    }

    /**
     * Cancel current subscription
     *
     * By default the subscription is cancelled at the end of the current
     * billing period, so the user keeps access to what they paid for.
     * Pass false to cancel and revoke access immediately.
     *
     * @param bool $atPeriodEnd Cancel at period end instead of immediately
     * @return bool
     */
    public function cancelSubscription(bool $atPeriodEnd = true): bool
    {
        $subscription = $this->subscription();

        if (!$subscription) {
            return true;
        }

        return billing()->cancelSubscription($subscription['subscription_id'], $atPeriodEnd);
    }

    /**
     * Resume a subscription that was cancelled at period end
     * but has not run out yet.
     *
     * @return bool
     */
    public function resumeSubscription(): bool
    {
        $subscription = $this->subscription();

        if (!$subscription || !$this->onGracePeriod()) {
            return false;
        }

        return billing()->resumeSubscription($subscription['subscription_id']);
    }
}
