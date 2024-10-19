<?php

beforeEach(function () {
    createUsersTable();
});

afterEach(function () {
    deleteUser('test-user');
});

test('register should save user in database', function () {
	$auth = new \Leaf\Auth();
    $auth::config(getAuthConfig(['session' => false]));
    $response = $auth::register(['username' => 'test-user', 'password' => 'test-password']);

    expect($response['user']['username'])->toBe('test-user');
});
