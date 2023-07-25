<?php

beforeEach(function () {
    createUsersTable();

    \Leaf\Auth\Core::connect(
        'localhost',
        'leaf',
        'root',
        'root',
        'mysql'
    );
});

test('register should save user in database', function () {

    $settings = [
        'DB_TABLE' => 'users',
        'AUTH_NO_PASS' => false,
        'USE_TIMESTAMPS' => false,
        'TIMESTAMP_FORMAT' => 'c',
        'PASSWORD_ENCODE' => null,
        'PASSWORD_VERIFY' => null,
        'PASSWORD_KEY' => 'password',
        'HIDE_ID' => true,
        'ID_KEY' => 'id',
        'USE_UUID' => false,
        'HIDE_PASSWORD' => true,
        'LOGIN_PARAMS_ERROR' => 'Incorrect credentials!',
        'LOGIN_PASSWORD_ERROR' => 'Password is incorrect!',
        'USE_SESSION' => false,
        'SESSION_ON_REGISTER' => false,
        'GUARD_LOGIN' => '/auth/login',
        'GUARD_REGISTER' => '/auth/register',
        'GUARD_HOME' => '/home',
        'GUARD_LOGOUT' => '/auth/logout',
        'SAVE_SESSION_JWT' => false,
        'TOKEN_LIFETIME' => null,
        'TOKEN_SECRET' => '@_leaf$0Secret!',
    ];

	$auth = new \Leaf\Auth();
    $auth::config($settings);
    $response = $auth::register(['username' => 'test-user', 'password' => 'test-password']);

    expect($response['user']['username'])->toBe('test-user');
});

afterEach(function () {
    $db = new \Leaf\Db();
    $db->connect(
        'localhost',
        'leaf',
        'root',
        'root'
    );

    $db->delete('users')->where('username', '=', 'test-user')->execute();
});
