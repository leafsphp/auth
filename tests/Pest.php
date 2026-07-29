<?php

use Leaf\Auth;
use Leaf\Db;
use Leaf\Helpers\Password;

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

    // fresh database file per suite run
    static $cleaned = false;

    if (!$cleaned && $_ENV['DB_CONNECTION'] === 'sqlite' && file_exists($_ENV['DB_DATABASE'])) {
        unlink($_ENV['DB_DATABASE']);
        $cleaned = true;
    }

    if ($_ENV['DB_CONNECTION'] === 'sqlite') {
        return [
            'dbtype' => 'sqlite',
            'dbname' => $_ENV['DB_DATABASE'],
        ];
    }

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
    // one shared connection for the whole process — opening a second
    // handle onto the same sqlite file deadlocks on write locks
    static $db = null;

    if ($db === null) {
        $db = new Db();
        $db->connect(getDatabaseConnection());
    }

    return $db;
}

function authInstance(): Auth
{
    $auth = new Auth();
    $auth->dbConnection(dbInstance()->connection());
    $auth->config('token.secret', Password::hash(uniqid()));

    return $auth;
}

function deleteUser(string $username, $table = 'users')
{
    $db = dbInstance();

    $db->delete($table)->where('username', $username)->execute();
}

function createTableForUsers($table = 'users'): void
{
    $db = dbInstance();

    try {
        switch ($_ENV['DB_CONNECTION']) {
            case 'sqlite':
                $sql = "CREATE TABLE IF NOT EXISTS $table (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT NOT NULL,
                    email TEXT NOT NULL,
                    password TEXT NOT NULL,
                    permissions TEXT,
                    roles TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
                break;

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
