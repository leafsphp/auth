<?php

use Leaf\Auth;

/*
 * All tests here run standalone: a raw PDO connection is passed in,
 * sessions are off and no leaf app context is booted.
 */

const ROLES = [
    'admin' => ['view-ticket', 'edit-ticket', 'delete-ticket'],
    'support' => ['view-ticket', 'edit-ticket'],
    'guest' => ['view-ticket'],
];

function roleAuthInstance(): Auth
{
    $auth = authInstance();
    $auth->config(['session' => false, 'db.table' => 'role_users', 'roles.key' => 'roles']);
    $auth->createRoles(ROLES);

    return $auth;
}

beforeAll(function () {
    createTableForUsers('role_users');
});

afterAll(function () {
    dbInstance()->delete('role_users')->execute();
});

beforeEach(function () {
    deleteUser('role-user', 'role_users');

    $this->auth = roleAuthInstance();
    $this->auth->register([
        'username' => 'role-user',
        'email' => 'role-user@example.com',
        'password' => 'password',
    ]);
});

test('createRoles registers roles and their permissions', function () {
    expect($this->auth->roles())->toBe(ROLES);
});

test('a fresh user has no roles or permissions', function () {
    $user = $this->auth->user();

    expect($user->roles())->toBe([]);
    expect($user->permissions())->toBe([]);
    expect($user->is('admin'))->toBeFalse();
    expect($user->can('view-ticket'))->toBeFalse();
});

test('assign gives a user a role and its permissions', function () {
    $user = $this->auth->user();

    expect($user->assign('admin'))->toBeTrue();
    expect($user->roles())->toBe(['admin']);
    expect($user->is('admin'))->toBeTrue();
    expect($user->isNot('guest'))->toBeTrue();
    expect($user->can('delete-ticket'))->toBeTrue();
    expect($user->cannot('nonexistent-permission'))->toBeTrue();
});

test('assign accepts multiple roles and merges permissions without duplicates', function () {
    $user = $this->auth->user();

    $user->assign(['support', 'guest']);

    expect($user->roles())->toBe(['support', 'guest']);
    expect($user->permissions())->toBe(['view-ticket', 'edit-ticket']);
});

test('assigning an unknown role is ignored', function () {
    $user = $this->auth->user();

    $user->assign('superadmin');

    expect($user->roles())->toBe([]);
    expect($user->permissions())->toBe([]);
});

test('is and can accept arrays and match any entry', function () {
    $user = $this->auth->user();
    $user->assign('support');

    expect($user->is(['admin', 'support']))->toBeTrue();
    expect($user->is(['admin', 'guest']))->toBeFalse();
    expect($user->can(['delete-ticket', 'edit-ticket']))->toBeTrue();
    expect($user->can(['delete-ticket']))->toBeFalse();
});

test('roles persist and rehydrate on a fresh login', function () {
    $this->auth->user()->assign('support');

    $freshAuth = roleAuthInstance();
    $freshAuth->login([
        'username' => 'role-user',
        'password' => 'password',
    ]);

    $user = $freshAuth->user();

    expect($user->roles())->toBe(['support']);
    expect($user->can('edit-ticket'))->toBeTrue();
    expect($user->can('delete-ticket'))->toBeFalse();
});

test('unassign revokes a role and recalculates permissions', function () {
    $user = $this->auth->user();
    $user->assign(['admin', 'guest']);

    $user->unassign('admin');

    expect($user->roles())->toBe(['guest']);
    expect($user->permissions())->toBe(['view-ticket']);
    expect($user->cannot('delete-ticket'))->toBeTrue();

    // revocation also persists across logins
    $freshAuth = roleAuthInstance();
    $freshAuth->login(['username' => 'role-user', 'password' => 'password']);

    expect($freshAuth->user()->roles())->toBe(['guest']);
});
