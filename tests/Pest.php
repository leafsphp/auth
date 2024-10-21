<?php

function createUsersTable($table = 'users', $dynamicId = false)
{
    $db = new \Leaf\Db();
    $db->connect(getConnectionConfig());

    // $auth = new \Leaf\Auth();
    // $auth->dbConnection($db->connection());

    $db->createTableIfNotExists(
        $table,
        [
            // using varchar(255) to mimic binary(16) for uuid
            'id' => $dynamicId ? 'varchar(255)' : 'int NOT NULL AUTO_INCREMENT',
            'username' => 'varchar(255)',
            'email' => 'varchar(255)',
            'password' => 'varchar(255)',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'PRIMARY KEY' => '(id)',
        ]
    )->execute();

    $db->close();
}

function deleteUser(string $username, $table = 'users')
{
    $db = new \Leaf\Db();
    $db->connect(getConnectionConfig());

    $db->delete($table)->where('username', $username)->execute();
}

function getConnectionConfig(): array
{
    return [
        'port' => '3306',
        'host' => '127.0.0.1',
        'username' => 'root',
        'password' => '',
        'dbname' => 'atest',
    ];
}

function auth(): \Leaf\Auth
{
    $db = new \Leaf\Db();
    $db->connect(getConnectionConfig());

    $auth = new \Leaf\Auth();
    $auth->dbConnection($db->connection());

    return $auth;
}

function getAuthConfig(array $settingsReplacement = []): array
{
    $settings = [
        'id.key' => 'id',
        'id.uuid' => null,

        'db.table' => 'users',

        'timestamps' => true,
        'timestamps.format' => 'c',

        'password' => true,
        'password.encode' => null,
        'password.verify' => null,
        'password.key' => 'password',

        'unique' => ['email', 'username'],
        'hidden' => ['field.id', 'field.password'],

        'session' => false,
        'session.logout' => null,
        'session.register' => null,
        'session.lifetime' => 60 * 60 * 24,
        'session.cookie' => ['secure' => true, 'httponly' => true, 'samesite' => 'lax'],

        'token.lifetime' => null,
        'token.secret' => '@_leaf$0Secret!',

        'messages.loginParamsError' => 'Incorrect credentials!',
        'messages.loginPasswordError' => 'Password is incorrect!',
    ];

    return array_replace($settings, $settingsReplacement);
}
