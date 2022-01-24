<?php

if (!function_exists('auth') && class_exists('Leaf\App')) {
    /**
     * Return the leaf auth object
     * 
     * @return Leaf\Auth
     */
    function auth()
    {
        if (!(\Leaf\Config::get("auth.instance"))) {
            \Leaf\Config::set("auth.instance", new Leaf\Auth());
        }

        return \Leaf\Config::get("auth.instance");
    }
}

if (!function_exists('guard') && function_exists('auth')) {
    /**
     * Run an auth guard
     * 
     * @param string $guard The auth guard to run
     */
    function guard(string $guard)
    {
        return auth()->guard($guard);
    }
}

if (!function_exists('hasAuth') && function_exists('auth')) {
    /**
     * Find out if there's an active sesion
     */
    function hasAuth(): bool
    {
        return !!sessionUser();
    }
}

if (!function_exists('sessionUser') && function_exists('auth')) {
    /**
     * Get the currently logged in user
     */
    function sessionUser()
    {
        return \Leaf\Http\Session::get('AUTH_USER');
    }
}
