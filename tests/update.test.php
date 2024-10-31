<?php

beforeAll(function () {
    $auth = authInstance();
    $auth->config(['db.table' => 'users']);

    $auth->register([
        'username' => 'test-user-1',
        'email' => 'test-user-1@example.com',
        'password' => 'password',
    ]);

    $auth->register([
        'username' => 'test-user-2',
        'email' => 'test-user-2@example.com',
        'password' => 'password',
    ]);

    sleep(1);
});

afterAll(function () {
    dbInstance()->delete('users')->execute();
});

test('update should update user data', function () {
    $auth = authInstance();
    $auth->config(['db.table' => 'users']);

    $success = $auth->login([
        'username' => 'test-user-1',
        'password' => 'password'
    ]);

    if (!$success) {
        $this->fail(json_encode($auth->errors()));
    }

    $updateData = [
        'username' => 'test-user-3',
    ];

    $updateSuccess = $auth->update($updateData);

    if (!$updateSuccess) {
        $this->fail(json_encode($auth->errors()));
    }

    expect($auth->user()->username)->toBe($updateData['username']);
});

test('update should fail if user already exists', function () {
    $auth = authInstance();
    $auth->config(['db.table' => 'users']);

    $success = $auth->login([
        'username' => 'test-user-3',
        'password' => 'password'
    ]);

    if (!$success) {
        $this->fail(json_encode($auth->errors()));
    }

    $updateData = [
        'username' => 'test-user-2',
    ];

    $updateSuccess = $auth->update($updateData);

    expect($updateSuccess)->toBeFalse();
    expect($auth->errors()['username'])->toBe('username already exists');
});

test('updatePassword should update user password', function () {
    $auth = authInstance();
    $auth->config(['unique' => ['username']]);
    $auth->config(['db.table' => 'users']);

    $success = $auth->login([
        'username' => 'test-user-2',
        'password' => 'password'
    ]);

    if (!$success) {
        $this->fail(json_encode($auth->errors()));
    }

    $oldPassword = 'password';
    $newPassword = 'new-password';

    $updateSuccess = $auth->updatePassword($oldPassword, $newPassword);

    if (!$updateSuccess) {
        $this->fail(json_encode($auth->errors()));
    }

    $loginSuccess = $auth->login([
        'username' => 'test-user-2',
        'password' => 'new-password'
    ]);

    expect($loginSuccess)->toBeTrue();
    expect($auth->user()->{$auth->config('password.key')})->not()->toBe($oldPassword);
});

test('update should regenerate session id if session => true', function () {
    $auth = authInstance();
    $auth->config(['session' => true]);
    $auth->config(['db.table' => 'users']);

    $success = $auth->login([
        'username' => 'test-user-2',
        'password' => 'new-password'
    ]);

    if (!$success) {
        $this->fail(json_encode($auth->errors()));
    }

    $updateData = [
        'username' => 'test-user-5',
    ];

    $initialSessionId = session_id();

    $updateSuccess = $auth->update($updateData);

    if (!$updateSuccess) {
        $this->fail(json_encode($auth->errors()));
    }

    expect($auth->user()->username)->toBe($updateData['username']);
    expect($initialSessionId)->not()->toBe(session_id());
});
