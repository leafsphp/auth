<?php

use Leaf\Auth\Config;

/*
|--------------------------------------------------------------------------
| Token defaults & error-state regressions
|--------------------------------------------------------------------------
|
| Every test here pins a bug reported by an AI agent building a lite app
| on default config: born-expired tokens, a secret the JWT library
| rejects, leaked error state, and null columns escaping get().
|
*/

afterEach(function () {
    // config is static — leave it as other suites expect
    Config::set(['token.secret' => null, 'token.lifetime' => 60 * 60 * 24 * 365]);
    unset($_ENV['APP_KEY'], $_ENV['AUTH_TOKEN_SECRET']);
});

test('the default token lifetime is a year, not zero', function () {
    expect(Config::get('token.lifetime'))->toBe(60 * 60 * 24 * 365);
});

test('tokens are minted with a future expiry on default config', function () {
    $auth = authInstance();
    $auth->createUserFor([
        'username' => 'token-check',
        'email' => 'token-check@example.com',
        'password' => 'secret1234',
    ]);

    $login = $auth->login(['email' => 'token-check@example.com', 'password' => 'secret1234']);

    expect($login)->toBeTrue();

    $token = $auth->data()->accessToken;
    $payload = json_decode(base64_decode(explode('.', $token)[1]), true);

    // the born-expired bug: exp equalled iat on default config
    expect($payload['exp'])->toBeGreaterThan($payload['iat'] + 60 * 60 * 24 * 364);

    deleteUser('token-check');
});

test('the token secret derives from APP_KEY when nothing else is set', function () {
    Config::set(['token.secret' => null]);
    $_ENV['APP_KEY'] = 'base64:test-app-key';

    $secret = Config::tokenSecret();

    expect($secret)->toBe(hash_hmac('sha256', 'leaf.auth.token.v1', 'base64:test-app-key'))
        ->and($secret)->not->toBe('base64:test-app-key')
        ->and(strlen($secret))->toBe(64); // long enough for HS256 everywhere
});

test('AUTH_TOKEN_SECRET beats the APP_KEY derivation', function () {
    Config::set(['token.secret' => null]);
    $_ENV['APP_KEY'] = 'base64:test-app-key';
    $_ENV['AUTH_TOKEN_SECRET'] = 'explicit-secret-that-is-long-enough-for-hs256';

    expect(Config::tokenSecret())->toBe('explicit-secret-that-is-long-enough-for-hs256');
});

test('token operations throw clearly when no secret can be resolved', function () {
    Config::set(['token.secret' => null]);
    unset($_ENV['APP_KEY'], $_ENV['AUTH_TOKEN_SECRET']);

    expect(fn () => Config::tokenSecret())
        ->toThrow(RuntimeException::class, 'No auth token secret');
});

test('each operation starts with a clean error state', function () {
    $auth = authInstance();
    $auth->createUserFor([
        'username' => 'error-state',
        'email' => 'error-state@example.com',
        'password' => 'secret1234',
    ]);

    // op 1: duplicate registration fails with an email error
    $auth->register([
        'username' => 'error-state',
        'email' => 'error-state@example.com',
        'password' => 'secret1234',
    ]);

    expect($auth->errors())->not->toBeEmpty();

    // op 2: wrong-password login must not carry op 1's email error
    $auth->login(['email' => 'error-state@example.com', 'password' => 'wrong']);

    expect($auth->errors())->not->toHaveKey('email');

    // op 3: a successful login reports no stale errors at all
    $login = $auth->login(['email' => 'error-state@example.com', 'password' => 'secret1234']);

    expect($login)->toBeTrue()
        ->and($auth->errors())->toBeEmpty();

    deleteUser('error-state');
});

test('a present-but-null roles column is hidden from get()', function () {
    $auth = authInstance();
    $user = new \Leaf\Auth\User([
        'id' => 1,
        'email' => 'null-roles@example.com',
        Config::get('roles.key') => null,
    ], false);

    expect($user->get())->not->toHaveKey(Config::get('roles.key'));
});
