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

test('assigning an unknown role fails loudly instead of silently doing nothing', function () {
    $user = $this->auth->user();

    $errors = [];
    set_error_handler(function ($errno, $errstr) use (&$errors) {
        $errors[] = $errstr;
        return true;
    });

    $result = $user->assign('superadmin');

    restore_error_handler();

    expect($result)->toBeFalse();
    expect($errors)->toHaveCount(1);
    expect($errors[0])->toContain('superadmin');
    expect($user->roles())->toBe([]);
    expect($user->permissions())->toBe([]);
});

test('a known role mixed with an unknown one assigns nothing', function () {
    $user = $this->auth->user();

    set_error_handler(fn () => true);
    $result = $user->assign(['admin', 'nope']);
    restore_error_handler();

    expect($result)->toBeFalse();
    expect($user->roles())->toBe([]);
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

/*
 * Route middleware. The registered middleware calls the failure handler and
 * then exits, so each test hands it a handler that throws: a thrown marker
 * means "denied" and the exit is never reached, while no throw means the
 * request was allowed through.
 */

class MiddlewareDenied extends \Exception
{
}

function registerRoleMiddleware(Leaf\Auth $auth): void
{
    foreach (['is', 'isNot', 'can', 'cannot'] as $name) {
        $auth->middleware($name, function () use ($name) {
            throw new MiddlewareDenied($name);
        });
    }
}

function runRoleMiddleware(string $name, $args)
{
    $registered = (new ReflectionClass(Leaf\Router::class))->getProperty('namedMiddleware');
    $registered->setAccessible(true);

    return $registered->getValue()[$name](is_array($args) ? $args : [$args]);
}

test('is middleware allows a user holding the role', function () {
    $this->auth->user()->assign('admin');
    registerRoleMiddleware($this->auth);

    expect(fn () => runRoleMiddleware('is', ['admin']))->not->toThrow(MiddlewareDenied::class);
});

test('is middleware blocks a user without the role', function () {
    $this->auth->user()->assign('guest');
    registerRoleMiddleware($this->auth);

    expect(fn () => runRoleMiddleware('is', ['admin']))->toThrow(MiddlewareDenied::class);
});

test('is middleware allows any role in a piped list', function () {
    $this->auth->user()->assign('support');
    registerRoleMiddleware($this->auth);

    // 'is:admin|support' reaches the middleware already exploded
    expect(fn () => runRoleMiddleware('is', ['admin', 'support']))->not->toThrow(MiddlewareDenied::class);
});

test('can middleware allows a permission the role grants', function () {
    $this->auth->user()->assign('support');
    registerRoleMiddleware($this->auth);

    expect(fn () => runRoleMiddleware('can', ['edit-ticket']))->not->toThrow(MiddlewareDenied::class);
});

test('can middleware blocks a permission the role does not grant', function () {
    $this->auth->user()->assign('support');
    registerRoleMiddleware($this->auth);

    expect(fn () => runRoleMiddleware('can', ['delete-ticket']))->toThrow(MiddlewareDenied::class);
});

test('isNot middleware blocks a user holding the role', function () {
    $this->auth->user()->assign('admin');
    registerRoleMiddleware($this->auth);

    expect(fn () => runRoleMiddleware('isNot', ['admin']))->toThrow(MiddlewareDenied::class);
});

test('cannot middleware blocks a user holding the permission', function () {
    $this->auth->user()->assign('admin');
    registerRoleMiddleware($this->auth);

    expect(fn () => runRoleMiddleware('cannot', ['delete-ticket']))->toThrow(MiddlewareDenied::class);
});
