<?php

dataset('test-user', [[[
    'username' => 'test-user',
    'email' => 'test-user@example.com',
    'password' => 'password'
]]]);

function getDatabaseConnection(): array
{
    return [
        'dbtype' => 'pgsql',
        'port' => '6543',
        'host' => 'aws-1-eu-west-2.pooler.supabase.com',
        'username' => 'postgres.vljhbheaihorcnvlkljw',
        'password' => 'DV!GE7Aq6C8F55g',
        'dbname' => 'postgres',
    ];
}

function dbInstance(): \Leaf\Db
{
    $db = new \Leaf\Db();

    try {
        $db->connect(getDatabaseConnection());
    } catch (\Throwable $th) {
        throw $th;
    }

    return $db;
}

function authInstance(): \Leaf\Auth
{
    $auth = new \Leaf\Auth();
    $auth->dbConnection(dbInstance()->connection());

    return $auth;
}

function deleteUser(string $username, $table = 'users')
{
    $db = new \Leaf\Db();
    $db->connect(getDatabaseConnection());

    $db->delete($table)->where('username', $username)->execute();
}

function createTableForUsers($table = 'users'): void
{
    $db = dbInstance();

    try {
        $db
            ->query("CREATE TABLE IF NOT EXISTS $table (
                id SERIAL PRIMARY KEY,
                username VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                password VARCHAR(255) NOT NULL,
                permissions JSONB,
                roles JSONB,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )")
            ->execute();
    } catch (\Throwable $th) {
        throw new \Exception('Failed to create table for users: ' . $th->getMessage());
    }
}
