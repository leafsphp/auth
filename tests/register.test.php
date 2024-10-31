<?php

beforeAll(function () {
    createTableForUsers();
    dbInstance()->delete('users')->execute();
});

afterEach(function () {
    dbInstance()->delete('users')->execute();
});

test('user can register an account', function () {
    $auth = authInstance();
    $auth->config(['db.table' => 'users']);

    $userData = [
        'username' => 'test-user',
        'email' => 'test-user@example.com',
        'password' => 'password'
    ];

    $success = $auth->register($userData);

    if (!$success) {
        $this->fail(json_encode($auth->errors()));
    }

    expect($success)->toBeTrue();
    expect($auth->user())->toBeInstanceOf(\Leaf\Auth\User::class);
    expect($auth->user()->username)->toBe($userData['username']);
});

test('user can login after registering', function () {
    $auth = authInstance();
    $auth->config(['db.table' => 'users']);

    $userData = [
        'username' => 'test-user',
        'email' => 'test-user@example.com',
        'password' => 'password'
    ];

    $registerSuccess = $auth->register($userData);

    expect($registerSuccess)->toBeTrue();

    $loginSuccess = $auth->login($userData);

    expect($loginSuccess)->toBeTrue();
    expect($auth->user())->toBeInstanceOf(\Leaf\Auth\User::class);
    expect($auth->user()->username)->toBe($userData['username']);
});

test('user can only sign up once', function () {
    $auth = authInstance();
    $auth->config(['db.table' => 'users']);

    $userData = [
        'username' => 'test-user',
        'email' => 'test-user@example.com',
        'password' => 'password'
    ];

    $auth->config([
        'unique' => ['email', 'username']
    ]);

    $registerSuccess = $auth->register($userData);

    expect($registerSuccess)->toBeTrue();

    $registerAgain = $auth->register($userData);

    expect($registerAgain)->toBeFalse();

    expect($auth->errors())->toBe([
        'email' => 'email already exists',
        'username' => 'username already exists',
    ]);
});

test('register passwords are encrypted', function () {
    $auth = authInstance();
    $auth->config(['db.table' => 'users']);

    $userData = [
        'username' => 'test-user',
        'email' => 'test-user@example.com',
        'password' => 'password'
    ];

    $auth->config([
        'hidden' => []
    ]);

    $registerSuccess = $auth->register($userData);

    expect($registerSuccess)->toBeTrue();
    expect($auth->user()->password)->not()->toBe($userData['password']);
    expect(password_verify($userData['password'], $auth->user()->password))->toBeTrue();
});
