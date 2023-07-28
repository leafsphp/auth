<?php

beforeEach(function () {
    createUsersTable();
    haveRegisteredUser('login-user', 'login-pass');
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

afterEach(function () {
    deleteUser('login-user');
});
