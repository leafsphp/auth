<?php

beforeAll(function () {
    createUsersTable();

    $auth = auth();
    $auth->config(['timestamps.format' => 'YYYY-MM-DD HH:MM:ss']);

    $auth->register([
        'username' => 'login-user',
        'email' => 'login-user@example.com',
        'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    ]);
});

afterEach(function () {
    if (!session_status()) {
        session_start();
    }

    session_destroy();
});

afterAll(function () {
    deleteUser('login-user');
});

test('login should set user session', function () {
    $auth = auth();
    $auth->useSession();

    $auth->login(['username' => 'login-user', 'password' => 'login-pass']);

    $session = new \Leaf\Http\Session(false);
    $user = $session->get('auth.token');

    expect($user['username'])->toBe('login-user');
});

test('login should set session ttl', function () {
    $auth = auth();
    $auth->useSession();

    $timeBeforeLogin = time();
    $auth->login(['username' => 'login-user', 'password' => 'login-pass']);

    $session = new \Leaf\Http\Session(false);
    $sessionTtl = $session->get('session.ttl');

    expect($sessionTtl > $timeBeforeLogin)->toBeTrue();
});

test('login should set regenerate session id', function () {
    $auth = auth();
    $auth->useSession();

    $auth->login(['username' => 'login-user', 'password' => 'login-pass']);

    $originalSessionId = session_id();

    $auth->login(['username' => 'login-user', 'password' => 'login-pass']);

    expect(session_id())->not()->toBe($originalSessionId);
});

test('login should set secure session cookie params', function () {
    $auth = auth();
    $auth->useSession();

    $auth->login(['username' => 'login-user', 'password' => 'login-pass']);

    $cookieParams = session_get_cookie_params();

    expect($cookieParams['secure'])->toBeTrue();
    expect($cookieParams['httponly'])->toBeTrue();
    expect($cookieParams['samesite'])->toBe('lax');
});

test('Session should expire when fetching user, and then login is possible again', function () {
    $auth = new \Leaf\Auth();
    $auth->config(['session.lifetime' => 2]);

    $auth->login(['username' => 'login-user', 'password' => 'login-pass']);

    $user = $auth->user();

    expect($user)->not()->toBeNull();
    expect($user['username'])->toBe('login-user');

    sleep(1);
    expect($auth->user())->not()->toBeNull();

    sleep(2);
    expect($auth->user())->toBeNull();

    $userAfterReLogin = $auth->login(['username' => 'login-user', 'password' => 'login-pass']);

    expect($userAfterReLogin)->not()->toBeNull();
    expect($userAfterReLogin['user']['username'])->toBe('login-user');
});

test('Session should not expire when fetching user if session lifetime is 0', function () {
    $auth = new \Leaf\Auth();
    $auth->config(['session.lifetime' => 0]);

    $auth->login(['username' => 'login-user', 'password' => 'login-pass']);

    $user = $auth->user();

    expect($user)->not()->toBeNull();
    expect($user['username'])->toBe('login-user');

    sleep(2);
    expect($auth->user())->not()->toBeNull();
});

test('Session should expire when fetching user id', function () {
    $auth = new \Leaf\Auth();

    $auth->config(['session.lifetime' => 2]);
    $auth->login(['username' => 'login-user', 'password' => 'login-pass']);

    expect($auth->id())->not()->toBeNull();

    sleep(1);
    expect($auth->id())->not()->toBeNull();

    sleep(2);
    expect($auth->id())->toBeNull();
});

test('Session should expire when fetching status', function () {
    $auth = new \Leaf\Auth();

    $auth->config(['session.lifetime' => 2]);
    $auth->login(['username' => 'login-user', 'password' => 'login-pass']);

    expect($auth->status())->not()->toBeNull();

    sleep(1);
    expect($auth->status())->not()->toBeNull();

    sleep(2);
    expect($auth->status())->toBeFalse();
});

test('Session lifetime should set correct session ttl when string is configured instead of timestamp', function () {
    $auth = new \Leaf\Auth();

    $auth->config(['session.lifetime' => '1 day']);
    $auth->login(['username' => 'login-user', 'password' => 'login-pass']);

    expect($auth->status())->not()->toBeNull();

    $timestampOneDay = 60 * 60 * 24;
    $session = new \Leaf\Http\Session(false);
    $sessionTtl = $session->get('session.ttl');

    expect($sessionTtl)->toBe(time() + $timestampOneDay);
});

test('Login should throw error when lifetime string is invalid', function () {
    $auth = new \Leaf\Auth();
    $auth->config(['session.lifetime' => 'invalid string']);

    expect(function () use ($auth) {
        return $auth->login(['username' => 'login-user', 'password' => 'login-pass']);
    })->toThrow(Exception::class, 'Provided string could not be converted to time');
});

test('Login should set session ttl on login', function () {
    $auth = auth();
    $auth->useSession();
    $auth->config(['session.lifetime' => 2]);

    $timeBeforeLogin = time();
    $auth->login(['username' => 'login-user', 'password' => 'login-pass']);

    $session = new \Leaf\Http\Session(false);
    $sessionTtl = $session->get('session.ttl');

    expect($sessionTtl > $timeBeforeLogin)->toBeTrue();
});
