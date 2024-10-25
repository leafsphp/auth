<?php

test('auth user is instance of Leaf\Auth\User', function () {
    $userData = [
        'username' => 'test-user',
        'password' => 'password'
    ];

    if (!$auth->login($userData)) {
        $this->fail(json_encode($auth->errors()));
    }

    expect($auth->user())->toBeInstanceOf(\Leaf\Auth\User::class);
});
