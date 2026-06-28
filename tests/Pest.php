<?php

use Leaf\Auth;
use Leaf\Db;
use Leaf\Helpers\Password;

function loadEnvironmentVariables(): void
{
    if (file_exists(__DIR__ . '/../.env.php')) {
        $_ENV = require __DIR__ . '/../.env.php';
    }

    $_ENV += require __DIR__ . '/../.env.example.php';
}

function dbInstance(): Db
{
    static $db = null;

    if ($db === null) {
        loadEnvironmentVariables();
        $db = new Db();
        $db->autoConnect();

        // Leaf DB keeps reconnecting while deferred config is set.
        // This breaks SQLite :memory: tests because each query gets a new DB.
        $db->connection();
        $db->config(['deferred' => false]);
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
    dbInstance()->delete($table)->where('username', $username)->execute();
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
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
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
