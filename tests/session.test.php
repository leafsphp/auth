<?php

beforeAll(function () {
    createTableForUsers();
    dbInstance()->delete('users')->execute();
});

afterEach(function () {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        session_destroy();
    }
});

afterAll(function () {
    dbInstance()->delete('users')->execute();
});

test('register should create a new session when session => true', function () {
    $auth = authInstance();
    $auth->config(['session' => true]);
    $auth->config(['db.table' => 'users']);

    $userData = [
        'username' => 'test-user',
        'email' => 'test-user@example.com',
        'password' => 'password'
    ];

    $success = $auth->register($userData);

    expect($success)->toBeTrue();
    expect($auth->user())->toBeInstanceOf(\Leaf\Auth\User::class);
    expect($auth->user()->username)->toBe($userData['username']);

    expect(session_status())->toBe(PHP_SESSION_ACTIVE);
    expect($_SESSION['auth']['user']['username'] ?? null)->toBe($userData['username']);
});

test('register should not create a new session when session => false', function () {
    $auth = authInstance();
    $auth->config(['session' => false]);
    $auth->config(['db.table' => 'users']);

    $userData = [
        'username' => 'test-user2',
        'email' => 'test-user2@example.com',
        'password' => 'password'
    ];

    $success = $auth->register($userData);

    expect($success)->toBeTrue();
    expect($auth->user())->toBeInstanceOf(\Leaf\Auth\User::class);

    expect(session_status())->toBe(PHP_SESSION_NONE);
    expect($_SESSION['auth']['user']['username'] ?? null)->toBeNull();
});

test('login should create session when session => true', function () {
    $auth = authInstance();
    $auth->config(['session' => true]);
    $auth->config(['db.table' => 'users']);

    $userData = [
        'username' => 'test-user',
        'email' => 'test-user@example.com',
        'password' => 'password'
    ];

    $success = $auth->login($userData);

    expect($success)->toBeTrue();
    expect($auth->user())->toBeInstanceOf(\Leaf\Auth\User::class);
    expect($auth->user()->username)->toBe($userData['username']);

    expect(session_status())->toBe(PHP_SESSION_ACTIVE);
    expect($_SESSION['auth']['user']['username'] ?? null)->toBe($userData['username']);
});

test('session should create auth.ttl when session.lifetime is not 0', function () {
    $auth = authInstance();
    $auth->config(['session' => true, 'session.lifetime' => 2]);
    $auth->config(['db.table' => 'users']);

    $userData = [
        'username' => 'test-user',
        'email' => 'test-user@example.com',
        'password' => 'password'
    ];

    $timeBeforeLogin = time();

    $success = $auth->login($userData);

    expect($success)->toBeTrue();
    expect($auth->user())->toBeInstanceOf(\Leaf\Auth\User::class);
    expect($auth->user()->username)->toBe($userData['username']);

    expect(session_status())->toBe(PHP_SESSION_ACTIVE);
    expect($_SESSION['auth']['user']['username'] ?? null)->toBe($userData['username']);

    expect($_SESSION['auth']['ttl'])->toBeGreaterThan($timeBeforeLogin);
});

test('session should not create auth.ttl when session.lifetime is 0', function () {
    $auth = authInstance();
    $auth->config(['session' => true, 'session.lifetime' => 0]);
    $auth->config(['db.table' => 'users']);

    $userData = [
        'username' => 'test-user',
        'email' => 'test-user@example.com',
        'password' => 'password'
    ];

    $success = $auth->login($userData);

    expect($success)->toBeTrue();
    expect($auth->user())->toBeInstanceOf(\Leaf\Auth\User::class);
    expect($auth->user()->username)->toBe($userData['username']);

    expect(session_status())->toBe(PHP_SESSION_ACTIVE);
    expect($_SESSION['auth']['user']['username'] ?? null)->toBe($userData['username']);

    expect($_SESSION['auth']['ttl'] ?? null)->toBeNull();
});

test('session should expire after session.lifetime', function () {
    $auth = authInstance();
    $auth->config(['session' => true, 'session.lifetime' => 2]);
    $auth->config(['db.table' => 'users']);

    $userData = [
        'username' => 'test-user',
        'email' => 'test-user@example.com',
        'password' => 'password'
    ];

    $success = $auth->login($userData);

    expect($success)->toBeTrue();
    expect($auth->user())->toBeInstanceOf(\Leaf\Auth\User::class);
    expect($auth->user()->username)->toBe($userData['username']);

    expect(session_status())->toBe(PHP_SESSION_ACTIVE);
    expect($_SESSION['auth']['user']['username'] ?? null)->toBe($userData['username']);

    sleep(3);

    expect($auth->id())->toBeNull();
    expect($auth->user())->toBeNull();
});

test('login should regenerate session id when session => true and session is already active', function () {
    $auth = authInstance();
    $auth->config(['session' => true]);
    $auth->config(['db.table' => 'users']);

    session_start();

    $sessionId = session_id();

    $userData = [
        'username' => 'test-user',
        'email' => 'test-user@example.com',
        'password' => 'password'
    ];

    $success = $auth->login($userData);

    $newSessionId = session_id();

    expect($success)->toBeTrue();
    expect($auth->user())->toBeInstanceOf(\Leaf\Auth\User::class);
    expect($auth->user()->username)->toBe($userData['username']);

    expect(session_status())->toBe(PHP_SESSION_ACTIVE);
    expect($_SESSION['auth']['user']['username'] ?? null)->toBe($userData['username']);

    expect($newSessionId)->not()->toBe($sessionId);
});

test('logout should remove auth info from session when session => true', function () {
    $auth = authInstance();
    $auth->config(['session' => true]);
    $auth->config(['db.table' => 'users']);

    $userData = [
        'username' => 'test-user',
        'email' => 'test-user@example.com',
        'password' => 'password'
    ];

    $success = $auth->login($userData);

    expect($success)->toBeTrue();
    expect($auth->user())->toBeInstanceOf(\Leaf\Auth\User::class);
    expect($_SESSION['auth']['user']['username'] ?? null)->toBe($userData['username']);

    $auth->logout();

    expect($auth->user())->toBeNull();
    expect($_SESSION['auth']['user']['username'] ?? null)->toBeNull();
});
