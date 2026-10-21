<?php

declare(strict_types=1);

use Leaf\App;
use Leaf\Auth;
use Leaf\Config;

if (!function_exists('auth') && class_exists(App::class)) {
    /** Return the leaf auth object */
    function auth(): Auth
    {
        if (!Config::getStatic('auth')) {
            Config::singleton('auth', static fn (): Auth => new Auth());
        }

        return Config::get('auth');
    }
}
