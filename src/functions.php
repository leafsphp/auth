<?php

if (!function_exists('auth')) {
    /**
     * Return Leaf's auth object or run an auth guard
     * 
     * @param string $guard The auth guard to run
     */
    function auth($guard = null)
    {
        if (!$guard) return \Leaf\Auth::class;

        if ($guard === 'session') {
            return \Leaf\Auth::session();
        }

        return \Leaf\Auth::guard($guard);
    }
}

if (!function_exists('hasAuth')) {
    /**
     * Find out if there's an active sesion
     */
    function hasAuth()
    {
        return !!sessionUser();
    }
}

if (!function_exists('sessionUser')) {
    /**
     * Get the currently logged in user
     */
    function sessionUser()
    {
        return \Leaf\Http\Session::get('AUTH_USER');
    }
}
