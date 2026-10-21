<?php

use Leaf\App;
use Leaf\Auth;
use Leaf\Config;

if (!function_exists('auth') && class_exists(App::class)) {
    /**
     * Return the leaf auth object
     *
     * @return Leaf\Auth
     */
    function auth()
    {
        if (!(Config::getStatic('auth'))) {
            Config::singleton('auth', fn () => new Auth());
        }

        return Config::get('auth');
    }
}
