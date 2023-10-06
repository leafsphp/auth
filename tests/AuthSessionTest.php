<?php

beforeEach(function () {
    createUsersTable();
    haveRegisteredUser('login-user', 'login-pass');
});

afterEach(function () {
    deleteUser('login-user');

    if (!session_status()) {
        session_start();
    }

    session_destroy();
});

test('login should set user session', function () {
    $auth = new \Leaf\Auth();
    $auth::config(getAuthConfig());
    $auth::login(['username' => 'login-user', 'password' => 'login-pass']);

    $session = new \Leaf\Http\Session(false);
    $user = $session->get('AUTH_USER');

    expect($user['username'])->toBe('login-user');
});

test('login should set session ttl', function () {
    $auth = new \Leaf\Auth();
    $auth::config(getAuthConfig());

    $timeBeforeLogin = time();
    $auth::login(['username' => 'login-user', 'password' => 'login-pass']);

    $session = new \Leaf\Http\Session(false);
    $sessionTtl = $session->get('SESSION_TTL');

    expect($sessionTtl > $timeBeforeLogin)->toBeTrue();
});

test('login should set regenerate session id', function () {
    $auth = new \Leaf\Auth();
    $auth::config(getAuthConfig());

    $auth::login(['username' => 'login-user', 'password' => 'login-pass']);

    $originalSessionId = session_id();

    $auth::login(['username' => 'login-user', 'password' => 'login-pass']);

    expect(session_id())->not()->toBe($originalSessionId);
});

test('login should set secure session cookie params', function () {
    $auth = new \Leaf\Auth();
    $auth::config(getAuthConfig());

    $auth::login(['username' => 'login-user', 'password' => 'login-pass']);

    $cookieParams = session_get_cookie_params();

    expect($cookieParams['secure'])->toBeTrue();
    expect($cookieParams['httponly'])->toBeTrue();
    expect($cookieParams['samesite'])->toBe('lax');
});

test('register should set session ttl on login', function () {
    $auth = new \Leaf\Auth();
    $auth::config(getAuthConfig());

    $timeBeforeLogin = time();
    $auth::login(['username' => 'login-user', 'password' => 'login-pass']);

    $session = new \Leaf\Http\Session(false);
    $sessionTtl = $session->get('SESSION_TTL');

    expect($sessionTtl > $timeBeforeLogin)->toBeTrue();
});

test('Session should expire when fetching user, and then login is possible again', function () {
    $auth = new \Leaf\Auth();
    $auth::config(getAuthConfig(['SESSION_LIFETIME' => 2]));

    $auth::login(['username' => 'login-user', 'password' => 'login-pass']);

    $user = $auth::user();
    expect($user)->not()->toBeNull();
    expect($user['username'])->toBe('login-user');

    sleep(1);
    expect($auth::user())->not()->toBeNull();

    sleep(2);
    expect($auth::user())->toBeNull();

    $userAfterReLogin = $auth::login(['username' => 'login-user', 'password' => 'login-pass']);
    expect($userAfterReLogin)->not()->toBeNull();
    expect($userAfterReLogin['user']['username'])->toBe('login-user');
});

test('Session should not expire when fetching user if session lifetime is 0', function () {
    $auth = new \Leaf\Auth();
    $auth::config(getAuthConfig(['SESSION_LIFETIME' => 0]));

    $auth::login(['username' => 'login-user', 'password' => 'login-pass']);

    $user = $auth::user();
    expect($user)->not()->toBeNull();
    expect($user['username'])->toBe('login-user');

    sleep(2);
    expect($auth::user())->not()->toBeNull();
});

test('Session should expire when fetching user id', function () {
    $auth = new \Leaf\Auth();
    $auth::config(getAuthConfig(['SESSION_LIFETIME' => 2]));

    $auth::login(['username' => 'login-user', 'password' => 'login-pass']);

    expect($auth::id())->not()->toBeNull();

    sleep(1);
    expect($auth::id())->not()->toBeNull();

    sleep(2);
    expect($auth::id())->toBeNull();
});

test('Session should expire when fetching status', function () {
    $auth = new \Leaf\Auth();
    $auth::config(getAuthConfig(['SESSION_LIFETIME' => 2]));
    $auth::login(['username' => 'login-user', 'password' => 'login-pass']);

    expect($auth::status())->not()->toBeNull();

    sleep(1);
    expect($auth::status())->not()->toBeNull();

    sleep(2);
    expect($auth::status())->toBeFalse();
});

test('Session lifetime should set correct session ttl when string is configured instead of timestamp', function () {
    $auth = new \Leaf\Auth();
    $auth::config(getAuthConfig(['SESSION_LIFETIME' => '1 day']));
    $auth::login(['username' => 'login-user', 'password' => 'login-pass']);

    expect($auth::status())->not()->toBeNull();

    $timestampOneDay = 60 * 60 * 24;
    $session = new \Leaf\Http\Session(false);
    $sessionTtl = $session->get('SESSION_TTL');

    expect($sessionTtl)->toBe(time() + $timestampOneDay);
});

test('Login should throw error when lifetime string is invalid', function () {
    $auth = new \Leaf\Auth();
    $auth::config(getAuthConfig(['SESSION_LIFETIME' => 'invalid string']));

    expect(fn() => $auth::login(['username' => 'login-user', 'password' => 'login-pass']))
        ->toThrow(Exception::class, 'Provided string could not be converted to time');
});
