<?php

use Leaf\Auth;
use Leaf\Db;

dataset('test-user', [[[
    'username' => 'test-user',
    'email' => 'test-user@example.com',
    'password' => 'password'
]]]);

function getDatabaseConnection(): array
{
    if (file_exists(__DIR__ . '/../.env.php')) {
        $_ENV = require __DIR__ . '/../.env.php';
    }

    $_ENV += require __DIR__ . '/../.env.example.php';

    return [
        'dbtype' => $_ENV['DB_CONNECTION'],
        'port' => $_ENV['DB_PORT'],
        'host' => $_ENV['DB_HOST'],
        'username' => $_ENV['DB_USERNAME'],
        'password' => $_ENV['DB_PASSWORD'],
        'dbname' => $_ENV['DB_DATABASE'],
    ];
}

function dbInstance(): Db
{
    $db = new Db();

    try {
        $db->connect(getDatabaseConnection());
    } catch (Throwable $th) {
        throw $th;
    }

    return $db;
}

function authInstance(): Auth
{
    $auth = new Auth();
    $auth->dbConnection(dbInstance()->connection());

    return $auth;
}

function deleteUser(string $username, $table = 'users')
{
    $db = new Db();
    $db->connect(getDatabaseConnection());

    $db->delete($table)->where('username', $username)->execute();
}

function createTableForUsers($table = 'users'): void
{
    $db = dbInstance();

    try {
        switch ($_ENV['DB_CONNECTION']) {
            case 'mysql':
                $sql = "CREATE TABLE IF NOT EXISTS $table (
                    id SERIAL PRIMARY KEY,
                    username VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    permissions JSON,
                    roles JSON,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
                break;

            default:
                $sql = "CREATE TABLE IF NOT EXISTS $table (
                    id SERIAL PRIMARY KEY,
                    username VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    permissions JSONB,
                    roles JSONB,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
        }

        $db->query($sql)->execute();
    } catch (Throwable $th) {
        throw new Exception('Failed to create table for users: ' . $th->getMessage());
    }
}
