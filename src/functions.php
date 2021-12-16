<?php

if (!function_exists('auth')) {
    /**
     * Return Leaf's auth object or run an auth guard
     * 
     * @param string|null $guard The auth guard to run
     */
    function auth(string $guard = null)
    {
        if (!$guard) {
            if (class_exists('\Leaf\Config')) {
                $auth = Leaf\Config::get("auth")["instance"] ?? null;

                if (!$auth) {
                    $auth = new Leaf\Auth;
                    Leaf\Config::set("auth", ["instance" => $auth]);
                }

                return $auth;
            }

            return \Leaf\Auth::class;
        }

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
    function hasAuth(): bool
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
