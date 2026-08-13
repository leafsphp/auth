<?php

declare(strict_types=1);

// sqlite needs zero setup and runs on every CI OS — point these at your own
// mysql/postgres instance via .env.php when you want to test a real driver
return [
    'DB_CONNECTION' => 'sqlite',
    'DB_PORT' => '',
    'DB_HOST' => '',
    'DB_USERNAME' => '',
    'DB_PASSWORD' => '',
    'DB_DATABASE' => ':memory:', // in-memory sqlite: no files, no cleanup, fastest
];
