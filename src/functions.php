<?php

if (!function_exists('auth') && class_exists('Leaf\App')) {
    /**
     * Return the leaf auth object
     *
     * @return Leaf\Auth
     */
    function auth()
    {
        if (!(\Leaf\Config::getStatic('auth'))) {
            \Leaf\Config::singleton('auth', function () {
                return new \Leaf\Auth();
            });
        }

        return \Leaf\Config::get('auth');
    }
}
