<?php

declare(strict_types=1);

namespace Leaf\Auth;

/**
 * Auth Sessions [CORE]
 * -----
 * Core engine powering auth sessions.
 *
 * @author Michael Darko
 * @since 1.5.0
 * @version 2.0.0
 */
class Session
{
    /**
     * @var \Leaf\Http\Session
     */
    protected static $session;

    public static function init(array $sessionCookieParams = [])
    {
        static::$session = new \Leaf\Http\Session(false);

        if (!isset($_SESSION)) {
            session_set_cookie_params($sessionCookieParams);
            session_start();
        };

        if (!static::$session->get('session.startedAt')) {
            static::$session->set('session.startedAt', time());
        }

        static::$session->set('session.lastActivity', time());

        return static::$session;
    }
}
