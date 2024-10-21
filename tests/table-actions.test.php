<?php

beforeAll(function () {
    createUsersTable('myusers');

    deleteUser('test-user', 'myusers');
    deleteUser('test-user55', 'myusers');
});

afterAll(function () {
    deleteUser('test-user', 'myusers');
    deleteUser('test-user55', 'myusers');
    deleteUser('test-user', 'uuid_users');
});

test('register should save user in user defined table', function () {
    $auth = auth();
    $auth->config(['session' => false, 'db.table' => 'myusers']);

    $response = $auth->register([
        'username' => 'test-user',
        'email' => 'test-user@example.com',
        'password' => 'password',
    ]);

    if (!$response) {
        $this->fail(json_encode($auth->errors()));
    }

    expect($response['user']['username'])->toBe('test-user');
});

test('login should work with user defined table', function () {
    $auth = auth();
    $auth->config(['session' => false, 'db.table' => 'myusers']);

    $response = $auth->login([
        'username' => 'test-user',
        'password' => 'password'
    ]);

    if (!$response) {
        $this->fail(json_encode($auth->errors()));
    }

    expect($response['user']['username'])->toBe('test-user');
});

test('update should work with user defined table', function () {
    $auth = auth();
    $auth->config(['session' => true, 'db.table' => 'myusers', 'session.lifetime' => '1 day']);

    $loginData = $auth->login([
        'username' => 'test-user',
        'password' => 'password'
    ]);

    if (!$loginData) {
        $this->fail(json_encode($auth->errors()));
    }

    $response = $auth->update([
        'username' => 'test-user55',
        'email' => 'test-user55@example.com',
    ]);

    if (!$response) {
        $this->fail(json_encode($auth->errors()));
    }

    expect($response['user']['username'])->toBe('test-user55');
    expect($response['user']['email'])->toBe('test-user55@example.com');
});

test('user table can use uuid as id', function () {
    createUsersTable('uuid_users', true);

    $auth = auth();
    $auth->config(['session' => false, 'db.table' => 'uuid_users']);

    $response = $auth->register([
        'id' => '123e4567-e89b-12d3-a456-426614174000',
        'username' => 'test-user',
        'email' => 'test-user@example.com',
        'password' => 'password',
    ]);

    if (!$response) {
        $this->fail(json_encode($auth->errors()));
    }

    expect($response['user']['username'])->toBe('test-user');
});
