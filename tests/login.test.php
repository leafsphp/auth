<?php

beforeAll(function () {
    createTableForUsers();

    try {
        dbInstance()
            ->insert('users')
            ->params([
                'username' => 'test-user',
                'email' => 'test-user@example.com',
                'password' => password_hash('password', PASSWORD_BCRYPT)
            ])
            ->execute();
    } catch (\Throwable $th) {
        throw $th;
    }
});

afterAll(function () {
    dbInstance()->delete('users')->execute();
});

test('user can login', function () {
    $auth = authInstance();

    $testUser = [
        'username' => 'test-user',
        'email' => 'test-user@example.com',
        'password' => 'password'
    ];

    $success = $auth->login($testUser);

    expect($success)->toBeTrue();
    expect($auth->user())->toBeInstanceOf(\Leaf\Auth\User::class);
    expect($auth->user()->username)->toBe($testUser['username']);
});

test('login generates tokens on success', function () {
    $auth = authInstance();

    $testUser = [
        'username' => 'test-user',
        'email' => 'test-user@example.com',
        'password' => 'password'
    ];

    $success = $auth->login($testUser);

    expect($success)->toBeTrue();
    expect($auth->data())->not()->toBeNull();
    expect($auth->data()->accessToken)->toBeString();
    expect($auth->data()->refreshToken)->toBeString();
});

test('login fails with incorrect password', function () {
    $auth = authInstance();

    $userData = [
        'username' => 'test-user',
        'password' => 'wrong-password'
    ];

    $success = $auth->login($userData);

    $loginPasswordError = $auth->config('messages.loginPasswordError');

    expect($success)->toBeFalse();
    expect($auth->user())->toBeNull();
    expect($auth->errors()['password'] ?? null)->toBe($loginPasswordError);
});

test('login should fail if user does not exist', function () {
    $auth = authInstance();

    $userData = [
        'username' => 'non-existent-user',
        'password' => 'password'
    ];

    $success = $auth->login($userData);

    $loginParamsError = $auth->config('messages.loginParamsError');

    expect($success)->toBeFalse();
    expect($auth->user())->toBeNull();
    expect($auth->errors()['auth'] ?? null)->toBe($loginParamsError);
});

test('login should work without password is password.key is false', function () {
    $auth = authInstance();

    $auth->config('password.key', false);

    $userData = [
        'username' => 'test-user'
    ];

    $success = $auth->login($userData);

    expect($success)->toBeTrue();
    expect($auth->user())->toBeInstanceOf(\Leaf\Auth\User::class);
    expect($auth->user()->username)->toBe($userData['username']);

    $auth->config('password.key', 'password');
});
