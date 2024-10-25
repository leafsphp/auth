<?php

function getDatabaseConnection(): array
{
    return [
        'dbtype' => 'pgsql',
        'port' => '5432',
        'host' => 'ep-autumn-block-a28alwsy.eu-central-1.aws.neon.tech',
        'username' => 'sandbox_owner',
        'password' => 'WH1qpBIf7LYc',
        'dbname' => 'sandbox',
    ];
}

function connectToDatabase(): \Leaf\Db
{
    $db = new \Leaf\Db();
    $db->connect(getDatabaseConnection());

    return $db;
}
