<?php

beforeAll(function () {
    createTableForUsers();
    dbInstance()->delete('users')->execute();
});

afterEach(function () {
    dbInstance()->delete('users')->execute();
});

test('user can register an account', function (array $userData) {
    $auth = authInstance();

    $success = $auth->register($userData);

    if (!$success) {
        $this->fail(json_encode($auth->errors()));
    }

    expect($success)->toBeTrue();
    expect($auth->user())->toBeInstanceOf(\Leaf\Auth\User::class);
    expect($auth->user()->username)->toBe($userData['username']);
})->with('test-user');

test('user can login after registering', function (array $userData) {
    $auth = authInstance();

    $registerSuccess = $auth->register($userData);

    expect($registerSuccess)->toBeTrue();

    $loginSuccess = $auth->login($userData);

    expect($loginSuccess)->toBeTrue();
    expect($auth->user())->toBeInstanceOf(\Leaf\Auth\User::class);
    expect($auth->user()->username)->toBe($userData['username']);
})->with('test-user');

test('user can only sign up once', function (array $userData) {
    $auth = authInstance();

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
})->with('test-user');

test('register passwords are encrypted', function (array $userData) {
    $auth = authInstance();

    $auth->config([
        'hidden' => []
    ]);

    $registerSuccess = $auth->register($userData);

    expect($registerSuccess)->toBeTrue();
    expect($auth->user()->password)->not()->toBe($userData['password']);
    expect(password_verify($userData['password'], $auth->user()->password))->toBeTrue();
})->with('test-user');
