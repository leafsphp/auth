<?php

beforeAll(function () {
    createUsersTable();
});

afterAll(function () {
    deleteUser('test-user');
    deleteUser('test-user22');
});

test('register should save user in database', function () {
    $auth = auth();

    $auth->config(['timestamps.format' => 'YYYY-MM-DD HH:MM:ss']);

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

test('register should fail if user already exists', function () {
    $auth = auth();

    $auth->config([
        'timestamps.format' => 'YYYY-MM-DD HH:MM:ss',
        'unique' => ['username', 'email']
    ]);

    $response = $auth->register([
        'username' => 'test-user',
        'email' => 'test-user@example.com',
        'password' => 'password'
    ]);

    expect($response)->toBe(false);
    expect($auth->errors()['email'])->toBe('email already exists');
    expect($auth->errors()['username'])->toBe('username already exists');
});

test('login should retrieve user from database', function () {
    $auth = auth();

    $response = $auth->login([
        'username' => 'test-user',
        'password' => 'password'
    ]);

    if (!$response) {
        $this->fail(json_encode($auth->errors()));
    }

    expect($response['user']['username'])->toBe('test-user');
});

test('login should fail if user does not exist', function () {
    $auth = auth();

    $response = $auth->login([
        'username' => 'non-existent-user',
        'password' => 'password'
    ]);

    expect($response)->toBe(false);
    expect($auth->errors()['auth'])->toBe('Incorrect credentials!');
});

test('login should fail if password is wrong', function () {
    $db = new \Leaf\Db();
    $db->connect(getConnectionConfig());

    $db
        ->insert('users')
        ->params([
            'username' => 'test-user',
            'email' => 'test-user@example.com',
            'password' => '$2y$10$91T2Y5/D4e9QXw8EgU33E.9J1N23hHg.6lG5ofVhh69la492kqKga',
        ])
        ->execute();

    $auth = new \Leaf\Auth();
    $auth->dbConnection($db->connection());

    $userData = $auth->login([
        'username' => 'test-user',
        'password' => 'wrong-password'
    ]);

    expect($userData)->toBe(false);
    expect($auth->errors()['password'])->toBe('Password is incorrect!');
});

test('update should update user in database', function () {
    $auth = auth();

    $auth->useSession();

    $data = $auth->login([
        'username' => 'test-user',
        'password' => 'password'
    ]);

    if (!$data) {
        $this->fail(json_encode($auth->errors()));
    }

    $response = $auth->update([
        'username' => 'test-user22',
        'email' => 'test-user22@test.com',
    ]);

    if (!$response) {
        $this->fail(json_encode($auth->errors()));
    }

    $auth->config(['session' => false]);

    expect($response['user']['username'])->toBe('test-user22');
    expect($response['user']['email'])->toBe('test-user22@test.com');
});

test('update should fail if user already exists', function () {
    $auth = auth();

    $auth->useSession();

    $data = $auth->login([
        'username' => 'test-user22',
        'password' => 'password'
    ]);

    if (!$data) {
        $this->fail(json_encode($auth->errors()));
    }

    $response = $auth->update([
        'username' => 'test-user',
        'email' => 'test-user@example.com',
    ]);

    expect($response)->toBe(false);
    expect($auth->errors()['email'])->toBe('email already exists');
    expect($auth->errors()['username'])->toBe('username already exists');
});
