<?php

beforeAll(function () {
    createUsersTable();

    $auth = auth();
    $auth->config(['timestamps.format' => 'YYYY-MM-DD HH:MM:ss']);

    $auth->register([
        'username' => 'extra-user',
        'email' => 'extra-user@example.com',
        'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    ]);
});

afterAll(function () {
    deleteUser('extra-user');
});

test('login should produce valid token', function () {
    $auth = auth();

    $auth->config(['hidden' => ['password']]);
    $data = $auth->login(['username' => 'extra-user', 'password' => 'login-pass']);

    expect($auth->validateUserToken($data['token'])->user_id)->toBe((string) $data['user']['id']);
});
