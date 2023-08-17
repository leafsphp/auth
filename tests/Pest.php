<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

// uses(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function createUsersTable()
{
    $db = new \Leaf\Db();
    $db->connect(...getConnectionConfig());

    $db->createTableIfNotExists(
        'users',
        [
            'id' => 'int NOT NULL AUTO_INCREMENT',
            'username' => 'varchar(255)',
            'password' => 'varchar(255)',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'PRIMARY KEY' => '(id)',
        ]
    )->execute();
}

function haveRegisteredUser(string $username, string $password): array
{
    \Leaf\Auth\Core::connect(...getConnectionConfig('mysql'));

    $auth = new \Leaf\Auth();
    $auth::config(getAuthConfig(['USE_SESSION' => false]));

    return $auth::register(['username' => $username, 'password' => $password]);
}

function deleteUser(string $username)
{
    $db = new \Leaf\Db();
    $db->connect(...getConnectionConfig());

    $db->delete('users')->where('username', '=', $username)->execute();
}

function getConnectionConfig(?string $dbType = null): array
{
    $config = ['localhost', 'leaf', 'root', 'root'];

    if ($dbType) {
        $config[] = $dbType;
    }

    return $config;
}

function getAuthConfig(array $settingsReplacement = []): array
{
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
        'USE_SESSION' => true,
        'SESSION_ON_REGISTER' => false,
        'GUARD_LOGIN' => '/auth/login',
        'GUARD_REGISTER' => '/auth/register',
        'GUARD_HOME' => '/home',
        'GUARD_LOGOUT' => '/auth/logout',
        'SAVE_SESSION_JWT' => false,
        'TOKEN_LIFETIME' => null,
        'TOKEN_SECRET' => '@_leaf$0Secret!',
        'SESSION_REDIRECT_ON_LOGIN' => false,
        'SESSION_LIFETIME' => 60 * 60 * 24,
    ];

    return array_replace($settings, $settingsReplacement);
}
