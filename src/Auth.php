<?php

namespace Leaf;

use Leaf\Auth\Core;
use Leaf\Helpers\Authentication;
use Leaf\Helpers\Password;

/**
 * Leaf Simple Auth
 * -------------------------
 * Simple, straightforward authentication.
 *
 * @author Michael Darko
 * @since 1.5.0
 * @version 2.0.0
 */
class Auth extends Core
{
    /**
     * Simple user login
     *
     * @param array $credentials User credentials
     *
     * @return array|false false or all user info + tokens + session data
     */
    public static function login(array $credentials)
    {
        static::leafDbConnect();

        static::$errors = [];
        $table = static::$settings['db.table'];

        if (static::config('session')) {
            static::useSession();
        }

        $passKey = static::$settings['password.key'];
        $password = $credentials[$passKey] ?? null;

        if (isset($credentials[$passKey])) {
            unset($credentials[$passKey]);
        } else {
            static::$settings['password'] = false;
        }

        $user = static::$db->select($table)->where($credentials)->fetchAssoc();

        if (!$user) {
            static::$errors['auth'] = static::$settings['messages.loginParamsError'];
            return false;
        }

        if (static::$settings['password']) {
            $passwordIsValid = (static::$settings['password.verify'] !== false && isset($user[$passKey]))
                ? ((is_callable(static::$settings['password.verify']))
                    ? call_user_func(static::$settings['password.verify'], $password, $user[$passKey])
                    : Password::verify($password, $user[$passKey]))
                : false;

            if (!$passwordIsValid) {
                static::$errors['password'] = static::$settings['messages.loginPasswordError'];
                return false;
            }
        }

        $token = Authentication::generateSimpleToken(
            $user[static::$settings['id.key']],
            static::config('token.secret'),
            static::config('token.lifetime')
        );

        if (isset($user[static::$settings['id.key']])) {
            $userId = $user[static::$settings['id.key']];

            if (in_array(static::$settings['id.key'], static::$settings['hidden']) || in_array('field.id', static::$settings['hidden'])) {
                unset($user[static::$settings['id.key']]);
            }
        }

        if ((in_array(static::$settings['password.key'], static::$settings['hidden']) || in_array('field.password', static::$settings['hidden'])) && (isset($user[$passKey]) || !$user[$passKey])) {
            unset($user[$passKey]);
        }

        if (!$token) {
            static::$errors = array_merge(static::$errors, Authentication::errors());
            return false;
        }

        if (static::config('session')) {
            if (isset($userId)) {
                $user[static::$settings['id.key']] = $userId;
            }

            self::setUserToSession($user, $token);
        }

        $response['user'] = $user;
        $response['token'] = $token;

        return $response;
    }

    /**
     * Simple user registration
     *
     * @param array $credentials Information for new user
     * @param array $uniques Parameters which should be unique
     *
     * @return array|false false or all user info + tokens + session data
     */
    public static function register(array $credentials)
    {
        static::leafDbConnect();

        static::$errors = [];
        $table = static::$settings['db.table'];
        $passKey = static::$settings['password.key'];

        if (!isset($credentials[$passKey])) {
            static::$settings['password'] = false;
        }

        if (static::$settings['password'] && static::$settings['password.encode'] !== false) {
            $credentials[$passKey] = (is_callable(static::$settings['password.encode']))
                ? call_user_func(static::$settings['password.encode'], $credentials[$passKey])
                : Password::hash($credentials[$passKey]);

        }

        if (static::$settings['timestamps']) {
            $now = (new \Leaf\Date())->tick()->format(static::$settings['timestamps.format']);
            $credentials['created_at'] = $now;
            $credentials['updated_at'] = $now;
        }

        if (isset($credentials[static::$settings['id.key']])) {
            $credentials[static::$settings['id.key']] = is_callable($credentials[static::$settings['id.key']])
                ? call_user_func($credentials[static::$settings['id.key']])
                : $credentials[static::$settings['id.key']];
        }

        try {
            $query = static::$db->insert($table)->params($credentials)->unique(static::$settings['unique'])->execute();
        } catch (\Throwable $th) {
            throw new \Exception($th->getMessage());
        }

        if (!$query) {
            static::$errors = array_merge(static::$errors, static::$db->errors());
            return false;
        }

        $user = static::$db->select($table)->where($credentials)->fetchAssoc();

        if (!$user) {
            static::$errors = array_merge(static::$errors, static::$db->errors());
            return false;
        }

        $token = Authentication::generateSimpleToken(
            $user[static::$settings['id.key']],
            static::config('token.secret'),
            static::config('token.lifetime')
        );

        if (isset($user[static::$settings['id.key']])) {
            $userId = $user[static::$settings['id.key']];
        }

        if (
            in_array(static::$settings['id.key'], static::$settings['hidden']) || in_array('field.id', static::$settings['hidden'])
        ) {
            unset($user[static::$settings['id.key']]);
        }

        if (
            (in_array(static::$settings['password.key'], static::$settings['hidden']) || in_array('field.password', static::$settings['hidden']))
            && (isset($user[$passKey]) || !$user[$passKey])
        ) {
            unset($user[$passKey]);
        }

        if (!$token) {
            static::$errors = array_merge(static::$errors, Authentication::errors());
            return false;
        }

        if (static::config('session') && static::config('session.register')) {
            static::useSession();

            if (isset($userId)) {
                $user[static::$settings['id.key']] = $userId;
            }

            self::setUserToSession($user, $token);
        }

        $response['user'] = $user;
        $response['token'] = $token;

        return $response;
    }

    /**
     * Simple user update
     *
     * @param array $credentials New information for user
     * @param array $uniques Parameters which should be unique
     *
     * @return array|false all user info + tokens + session data
     */
    public static function update(array $credentials)
    {
        static::leafDbConnect();

        static::$errors = [];

        $table = static::$settings['db.table'];

        if (static::config('session')) {
            static::useSession();
        }

        $passKey = static::$settings['password.key'];
        $loggedInUser = static::user();

        if (!$loggedInUser) {
            static::$errors['auth'] = 'Not authenticated';
            return false;
        }

        $where = isset($loggedInUser[static::$settings['id.key']]) ? [static::$settings['id.key'] => $loggedInUser[static::$settings['id.key']]] : $loggedInUser;

        if (!isset($credentials[$passKey])) {
            static::$settings['password'] = false;
        }

        if (static::$settings['password'] && static::$settings['password.encode'] !== false) {
            $credentials[$passKey] = (is_callable(static::$settings['password.encode']))
                ? call_user_func(static::$settings['password.encode'], $credentials[$passKey])
                : Password::hash($credentials[$passKey]);
        }

        if (static::$settings['timestamps']) {
            $credentials['updated_at'] = (new \Leaf\Date())->tick()->format(static::$settings['timestamps.format']);
        }

        if (count(static::$settings['unique']) > 0) {
            foreach (static::$settings['unique'] as $unique) {
                if (!isset($credentials[$unique])) {
                    trigger_error("$unique not found in credentials.");
                }

                $data = static::$db->select($table)->where($unique, $credentials[$unique])->fetchAssoc();

                $wKeys = array_keys($where);
                $wValues = array_values($where);

                if (isset($data[$wKeys[0]]) && $data[$wKeys[0]] != $wValues[0]) {
                    static::$errors[$unique] = "$unique already exists";
                }
            }

            if (count(static::$errors) > 0) {
                return false;
            }
        }

        try {
            $query = static::$db->update($table)->params($credentials)->where($where)->execute();
        } catch (\Throwable $th) {
            trigger_error($th->getMessage());
        }

        if (!$query) {
            static::$errors = array_merge(static::$errors, static::$db->errors());
            return false;
        }

        if (isset($credentials['updated_at'])) {
            unset($credentials['updated_at']);
        }

        $user = static::$db->select($table)->where($credentials)->fetchAssoc();

        if (!$user) {
            static::$errors = array_merge(static::$errors, static::$db->errors());
            return false;
        }

        $token = Authentication::generateSimpleToken(
            $user[static::$settings['id.key']],
            static::config('token.secret'),
            static::config('token.lifetime')
        );

        if (isset($user[static::$settings['id.key']])) {
            $userId = $user[static::$settings['id.key']];
        }

        if (
            (in_array(static::$settings['id.key'], static::$settings['hidden']) || in_array('field.id', static::$settings['hidden']))
            && isset($user[static::$settings['id.key']])
        ) {
            unset($user[static::$settings['id.key']]);
        }

        if (
            (in_array(static::$settings['password.key'], static::$settings['hidden']) || in_array('field.password', static::$settings['hidden']))
            && (isset($user[$passKey]) || !$user[$passKey])
        ) {
            unset($user[$passKey]);
        }

        if (!$token) {
            static::$errors = array_merge(static::$errors, Authentication::errors());
            return false;
        }

        if (static::config('session')) {
            if (isset($userId)) {
                $user[static::$settings['id.key']] = $userId;
            }

            static::$session->set('auth.user', $user);
            static::$session->set('auth.token', $token);
        }

        $response['user'] = $user;
        $response['token'] = $token;

        return $response;
    }

    /**
     * Manually start an auth session
     */
    public static function useSession()
    {
        static::config('session', true);
        static::$session = Auth\Session::init(static::config('session.cookie'));
    }

    /**
     * Throw a 'use session' warning
     */
    protected static function sessionCheck()
    {
        if (!static::config('session')) {
            trigger_error('Turn on sessions to use this feature.');
        }

        if (!static::$session) {
            static::useSession();
        }
    }

    /**
     * Check session status
     */
    public static function status()
    {
        static::sessionCheck();
        static::expireSession();

        return static::$session->get('auth.token') ?? false;
    }

    /**
     * Return the user id encoded in token or session
     */
    public static function id()
    {
        static::leafDbConnect();

        static::$errors = [];

        if (static::config('session')) {
            if (static::expireSession()) {
                return null;
            }

            return static::$session->get('auth.token')[static::$settings['id.key']] ?? null;
        }

        $payload = static::validateToken(static::config('token.secret'));

        return $payload->user_id ?? null;
    }

    /**
     * Get the current user data from token
     *
     * @param array $hidden Fields to hide from user array
     */
    public static function user(array $hidden = [])
    {
        $table = static::$settings['db.table'];

        if (!static::id()) {
            return (static::config('session')) ? static::$session->get('auth.token') : null;
        }

        $user = static::$db->select($table)->where(static::$settings['id.key'], static::id())->fetchAssoc();

        if (count($hidden) > 0) {
            foreach ($hidden as $item) {
                if (isset($user[$item]) || !$user[$item]) {
                    unset($user[$item]);
                }
            }
        }

        return $user;
    }

    /**
     * End a session
     *
     * @param string $location A route to redirect to after logout
     */
    public static function logout(?string $location = null)
    {
        static::sessionCheck();

        static::$session->destroy();

        if (is_string($location)) {
            \Leaf\Http\Headers::status(302);
            $route = static::config($location) ?? $location;

            exit(header("location: $route"));
        }
    }

    /**
     * @return bool
     */
    private static function expireSession(): bool
    {
        self::sessionCheck();

        $sessionTtl = static::$session->get('session.ttl');

        if (!$sessionTtl) {
            return false;
        }

        $isSessionExpired = time() > $sessionTtl;

        if ($isSessionExpired) {
            static::$session->unset('auth.token');
            static::$session->unset('HAS_SESSION');
            static::$session->unset('auth.token');
            static::$session->unset('session.startedAt');
            static::$session->unset('session.lastActivity');
            static::$session->unset('session.ttl');
        }

        return $isSessionExpired;
    }

    /**
     * Session last active
     */
    public static function lastActive()
    {
        static::sessionCheck();

        return time() - static::$session->get('session.lastActivity');
    }

    /**
     * Refresh session
     *
     * @param bool $clearData Remove existing session data
     */
    public static function refresh(bool $clearData = true)
    {
        static::sessionCheck();

        $success = static::$session->regenerate($clearData);

        static::$session->set('session.startedAt', time());
        static::$session->set('session.lastActivity', time());
        static::setSessionTtl();

        return $success;
    }

    /**
     * Check how long a session has been going on
     */
    public static function length()
    {
        static::sessionCheck();

        return time() - static::$session->get('session.startedAt');
    }

    /**
     * @param array $user
     * @param string $token
     *
     * @return void
     */
    private static function setUserToSession(array $user, string $token): void
    {
        session_regenerate_id();

        static::$session->set('auth.token', $user);
        static::$session->set('HAS_SESSION', true);
        static::setSessionTtl();

        if (static::config('SAVE_SESSION_JWT')) {
            static::$session->set('auth.token', $token);
        }
    }

    /**
     * @return void
     */
    private static function setSessionTtl(): void
    {
        $sessionLifetime = static::config('session.lifetime');

        if ($sessionLifetime === 0) {
            return;
        }

        if (is_int($sessionLifetime)) {
            static::$session->set('session.ttl', time() + $sessionLifetime);
            return;
        }

        $sessionLifetimeInTime = strtotime($sessionLifetime);

        if (!$sessionLifetimeInTime) {
            throw new \Exception('Provided string could not be converted to time');
        }

        static::$session->set('session.ttl', $sessionLifetimeInTime);
    }
}
