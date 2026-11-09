<?php

// Runs Auth::denyAccess() in its own process, since both branches exit().
// Usage: php deny-access.php <vendor/autoload.php> [no-pages]
// "no-pages" hides Leaf\Crash\* from the autoloader to mimic a Leaf 4 app,
// where leafs/exception <5 has no Crash\Pages class.

require $argv[1];

if (($argv[2] ?? '') === 'no-pages') {
    $loaders = spl_autoload_functions();

    foreach ($loaders as $loader) {
        spl_autoload_unregister($loader);
    }

    spl_autoload_register(function ($class) use ($loaders) {
        if (strpos($class, 'Leaf\\Crash\\') === 0) {
            return;
        }

        foreach ($loaders as $loader) {
            $loader($class);

            if (class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false)) {
                return;
            }
        }
    });
}

register_shutdown_function(function () {
    fwrite(STDERR, 'STATUS=' . var_export(http_response_code(), true));
});

$deny = new ReflectionMethod(Leaf\Auth::class, 'denyAccess');
$deny->setAccessible(true);
$deny->invoke(null);

echo 'NOT-STOPPED';
