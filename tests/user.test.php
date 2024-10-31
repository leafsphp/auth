<?php

beforeAll(function () {
    createTableForUsers();
    dbInstance()->delete('users')->execute();

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

test('auth user is instance of Leaf\Auth\User', function () {
    $auth = authInstance();
    $auth->config(['db.table' => 'users']);

    $testUser = [
        'username' => 'test-user',
        'email' => 'test-user@example.com',
        'password' => 'password'
    ];

    $success = $auth->login($testUser);

    if (!$success) {
        $this->fail(json_encode($auth->errors()));
    }

    expect($success)->toBeTrue();
    expect($auth->user())->toBeInstanceOf(\Leaf\Auth\User::class);
    expect($auth->user()->username)->toBe($testUser['username']);
});

test('logout can use logout callback to run custom action', function () {
    $auth = authInstance();
    $auth->config(['db.table' => 'users']);

    $testUser = [
        'username' => 'test-user',
        'email' => 'test-user@example.com',
        'password' => 'password'
    ];

    $success = $auth->login($testUser);

    if (!$success) {
        $this->fail(json_encode($auth->errors()));
    }

    expect($success)->toBeTrue();
    expect($auth->user())->toBeInstanceOf(\Leaf\Auth\User::class);
    expect($auth->user()->username)->toBe($testUser['username']);

    $auth->logout(function ($auth) use ($testUser) {
        expect($auth)->toBeInstanceOf(\Leaf\Auth::class);
    });

    expect($auth->user())->toBeNull();
});
