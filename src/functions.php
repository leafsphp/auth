<?php

use Leaf\Auth;
use Leaf\Config;

if (!function_exists('auth') && class_exists('Leaf\App')) {
    /**
     * Return the leaf auth object
     *
     * @return Leaf\Auth
     */
    function auth()
    {
        if (!(Config::getStatic('auth'))) {
            Config::singleton('auth', function () {
                return new Auth();
            });
        }

        return Config::get('auth');
    }
}
