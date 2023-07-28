<?php

beforeEach(function () {
    createUsersTable();
});

test('register should save user in database', function () {
	$auth = new \Leaf\Auth();
    $auth::config(getAuthConfig(['USE_SESSION' => false]));
    $response = $auth::register(['username' => 'test-user', 'password' => 'test-password']);

    expect($response['user']['username'])->toBe('test-user');
});

afterEach(function () {
    deleteUser('test-user');
});
