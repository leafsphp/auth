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

        $table = static::$settings['DB_TABLE'];

        if (static::config('USE_SESSION')) {
            static::useSession();
        }

        $passKey = static::$settings['PASSWORD_KEY'];
        $password = $credentials[$passKey] ?? null;

        if (isset($credentials[$passKey])) {
            unset($credentials[$passKey]);
        } else {
            static::$settings['AUTH_NO_PASS'] = true;
        }

        $user = static::$db->select($table)->where($credentials)->fetchAssoc();

        if (!$user) {
            static::$errors['auth'] = static::$settings['LOGIN_PARAMS_ERROR'];
            return false;
        }

        if (static::$settings['AUTH_NO_PASS'] === false) {
            $passwordIsValid = false;

            if (static::$settings['PASSWORD_VERIFY'] !== false && isset($user[$passKey])) {
                if (is_callable(static::$settings['PASSWORD_VERIFY'])) {
                    $passwordIsValid = call_user_func(static::$settings['PASSWORD_VERIFY'], $password, $user[$passKey]);
                } else if (static::$settings['PASSWORD_VERIFY'] === Password::MD5) {
                    $passwordIsValid = md5($password) === $user[$passKey];
                } else {
                    $passwordIsValid = Password::verify($password, $user[$passKey]);
                }
            }

            if (!$passwordIsValid) {
                static::$errors['password'] = static::$settings['LOGIN_PASSWORD_ERROR'];
                return false;
            }
        }

        $token = Authentication::generateSimpleToken(
            $user[static::$settings['ID_KEY']],
            static::config('TOKEN_SECRET'),
            static::config('TOKEN_LIFETIME')
        );

        if (isset($user[static::$settings['ID_KEY']])) {
            $userId = $user[static::$settings['ID_KEY']];

            if (static::$settings['HIDE_ID']) {
                unset($user[static::$settings['ID_KEY']]);
            }
        }

        if (static::$settings['HIDE_PASSWORD'] && (isset($user[$passKey]) || !$user[$passKey])) {
            unset($user[$passKey]);
        }

        if (!$token) {
            static::$errors = array_merge(static::$errors, Authentication::errors());
            return false;
        }

        if (static::config('USE_SESSION')) {
            if (isset($userId)) {
                $user[static::$settings['ID_KEY']] = $userId;
            }

            self::setUserToSession($user, $token);

            if (static::config('SESSION_REDIRECT_ON_LOGIN')) {
                exit(header('location: ' . static::config('GUARD_HOME')));
            }
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
    public static function register(array $credentials, array $uniques = [])
    {
        static::leafDbConnect();

        $table = static::$settings['DB_TABLE'];
        $passKey = static::$settings['PASSWORD_KEY'];

        if (!isset($credentials[$passKey])) {
            static::$settings['AUTH_NO_PASS'] = true;
        }

        if (static::$settings['AUTH_NO_PASS'] === false) {
            if (static::$settings['PASSWORD_ENCODE'] !== false) {
                if (is_callable(static::$settings['PASSWORD_ENCODE'])) {
                    $credentials[$passKey] = call_user_func(static::$settings['PASSWORD_ENCODE'], $credentials[$passKey]);
                } else if (static::$settings['PASSWORD_ENCODE'] === 'md5') {
                    $credentials[$passKey] = md5($credentials[$passKey]);
                } else {
                    $credentials[$passKey] = Password::hash($credentials[$passKey]);
                }
            }
        }

        if (static::$settings['USE_TIMESTAMPS']) {
            $now = (new \Leaf\Date())->tick()->format(static::$settings['TIMESTAMP_FORMAT']);
            $credentials['created_at'] = $now;
            $credentials['updated_at'] = $now;
        }

        if (static::$settings['USE_UUID'] !== false) {
            $credentials[static::$settings['ID_KEY']] = static::$settings['USE_UUID'];
        }

        try {
            $query = static::$db->insert($table)->params($credentials)->unique($uniques)->execute();
        } catch (\Throwable $th) {
            trigger_error($th->getMessage());
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
            $user[static::$settings['ID_KEY']],
            static::config('TOKEN_SECRET'),
            static::config('TOKEN_LIFETIME')
        );

        if (isset($user[static::$settings['ID_KEY']])) {
            $userId = $user[static::$settings['ID_KEY']];
        }

        if (static::$settings['HIDE_ID']) {
            unset($user[static::$settings['ID_KEY']]);
        }

        if (static::$settings['HIDE_PASSWORD'] && (isset($user[$passKey]) || !$user[$passKey])) {
            unset($user[$passKey]);
        }

        if (!$token) {
            static::$errors = array_merge(static::$errors, Authentication::errors());
            return false;
        }

        if (static::config('USE_SESSION')) {
            if (static::config('SESSION_ON_REGISTER')) {
                if (isset($userId)) {
                    $user[static::$settings['ID_KEY']] = $userId;
                }

                self::setUserToSession($user, $token);

                exit(header('location: ' . static::config('GUARD_HOME')));
            } else {
                if (static::config('SESSION_REDIRECT_ON_REGISTER')) {
                    exit(header('location: ' . static::config('GUARD_LOGIN')));
                }
            }
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
    public static function update(array $credentials, array $uniques = [])
    {
        static::leafDbConnect();

        $table = static::$settings['DB_TABLE'];

        if (static::config('USE_SESSION')) {
            static::useSession();
        }

        $passKey = static::$settings['PASSWORD_KEY'];
        $loggedInUser = static::user();

        if (!$loggedInUser) {
            static::$errors['auth'] = 'Not authenticated';
            return false;
        }

        $where = isset($loggedInUser[static::$settings['ID_KEY']]) ? [static::$settings['ID_KEY'] => $loggedInUser[static::$settings['ID_KEY']]] : $loggedInUser;

        if (!isset($credentials[$passKey])) {
            static::$settings['AUTH_NO_PASS'] = true;
        }

        if (
            static::$settings['AUTH_NO_PASS'] === false &&
            static::$settings['PASSWORD_ENCODE'] !== false
        ) {
            if (is_callable(static::$settings['PASSWORD_ENCODE'])) {
                $credentials[$passKey] = call_user_func(static::$settings['PASSWORD_ENCODE'], $credentials[$passKey]);
            } else if (static::$settings['PASSWORD_ENCODE'] === 'md5') {
                $credentials[$passKey] = md5($credentials[$passKey]);
            } else {
                $credentials[$passKey] = Password::hash($credentials[$passKey]);
            }
        }

        if (static::$settings['USE_TIMESTAMPS']) {
            $credentials['updated_at'] = (new \Leaf\Date())->tick()->format(static::$settings['TIMESTAMP_FORMAT']);
        }

        if (count($uniques) > 0) {
            foreach ($uniques as $unique) {
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
            $user[static::$settings['ID_KEY']],
            static::config('TOKEN_SECRET'),
            static::config('TOKEN_LIFETIME')
        );

        if (isset($user[static::$settings['ID_KEY']])) {
            $userId = $user[static::$settings['ID_KEY']];
        }

        if (static::$settings['HIDE_ID'] && isset($user[static::$settings['ID_KEY']])) {
            unset($user[static::$settings['ID_KEY']]);
        }

        if (static::$settings['HIDE_PASSWORD'] && (isset($user[$passKey]) || !$user[$passKey])) {
            unset($user[$passKey]);
        }

        if (!$token) {
            static::$errors = array_merge(static::$errors, Authentication::errors());
            return false;
        }

        if (static::config('USE_SESSION')) {
            if (isset($userId)) {
                $user[static::$settings['ID_KEY']] = $userId;
            }

            static::$session->set('AUTH_USER', $user);
            static::$session->set('HAS_SESSION', true);

            if (static::config('SAVE_SESSION_JWT')) {
                static::$session->set('AUTH_TOKEN', $token);
            }

            return $user;
        }

        $response['user'] = $user;
        $response['token'] = $token;

        return $response;
    }

    /**
     * Validation for parameters
     *
     * @param array $rules Rules for parameter validation
     */
    public function validate(array $rules): bool
    {
        $validation = Form::validate($rules);

        if (!$validation) {
            static::$errors = array_merge(static::$errors, Form::errors());
        }

        return $validation;
    }

    /**
     * Manually start an auth session
     */
    public static function useSession()
    {
        static::config('USE_SESSION', true);
        static::$session = Auth\Session::init(static::config('SESSION_COOKIE_PARAMS'));
    }

    /**
     * Throw a 'use session' warning
     */
    protected static function sessionCheck()
    {
        if (!static::config('USE_SESSION')) {
            trigger_error('Turn on USE_SESSION to use this feature.');
        }

        if (!static::$session) {
            static::useSession();
        }
    }

    /**
     * A simple auth guard: 'guest' pages can't be viewed when logged in,
     * 'auth' pages can't be viewed without authentication
     *
     * @param string $type The type of guard/guard options
     */
    public static function guard(string $type)
    {
        static::sessionCheck();

        if ($type === 'guest' && static::status()) {
            exit(header('location: ' . static::config('GUARD_HOME'), true, 302));
        }

        if ($type === 'auth' && !static::status()) {
            exit(header('location: ' . static::config('GUARD_LOGIN'), true, 302));
        }
    }

    /**
     * Check session status
     */
    public static function status()
    {
        static::sessionCheck();
        static::expireSession();

        return static::$session->get('AUTH_USER') ?? false;
    }

    /**
     * Return the user id encoded in token or session
     */
    public static function id()
    {
        static::leafDbConnect();

        if (static::config('USE_SESSION')) {
            if (static::expireSession()) {
                return null;
            }

            return static::$session->get('AUTH_USER')[static::$settings['ID_KEY']] ?? null;
        }

        $payload = static::validateToken(static::config('TOKEN_SECRET'));

        return $payload->user_id ?? null;
    }

    /**
     * Get the current user data from token
     *
     * @param array $hidden Fields to hide from user array
     */
    public static function user(array $hidden = [])
    {
        $table = static::$settings['DB_TABLE'];

        if (!static::id()) {
            if (static::config('USE_SESSION')) {
                return static::$session->get('AUTH_USER');
            }

            return null;
        }

        $user = static::$db->select($table)->where(static::$settings['ID_KEY'], static::id())->fetchAssoc();

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

        $sessionTtl = static::$session->get('SESSION_TTL');

        if (!$sessionTtl) {
            return false;
        }

        $isSessionExpired = time() > $sessionTtl;

        if ($isSessionExpired) {
            static::$session->unset('AUTH_USER');
            static::$session->unset('HAS_SESSION');
            static::$session->unset('AUTH_TOKEN');
            static::$session->unset('SESSION_STARTED_AT');
            static::$session->unset('SESSION_LAST_ACTIVITY');
            static::$session->unset('SESSION_TTL');
        }

        return $isSessionExpired;
    }

    /**
     * Session last active
     */
    public static function lastActive()
    {
        static::sessionCheck();

        return time() - static::$session->get('SESSION_LAST_ACTIVITY');
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

        static::$session->set('SESSION_STARTED_AT', time());
        static::$session->set('SESSION_LAST_ACTIVITY', time());
        static::setSessionTtl();

        return $success;
    }

    /**
     * Check how long a session has been going on
     */
    public static function length()
    {
        static::sessionCheck();

        return time() - static::$session->get('SESSION_STARTED_AT');
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

        static::$session->set('AUTH_USER', $user);
        static::$session->set('HAS_SESSION', true);
        static::setSessionTtl();

        if (static::config('SAVE_SESSION_JWT')) {
            static::$session->set('AUTH_TOKEN', $token);
        }
    }

    /**
     * @return void
     */
    private static function setSessionTtl(): void
    {
        $sessionLifetime = static::config('SESSION_LIFETIME');

        if ($sessionLifetime === 0) {
            return;
        }

        if (is_int($sessionLifetime)) {
            static::$session->set('SESSION_TTL', time() + $sessionLifetime);
            return;
        }

        $sessionLifetimeInTime = strtotime($sessionLifetime);

        if (!$sessionLifetimeInTime) {
            throw new \Exception('Provided string could not be converted to time');
        }

        static::$session->set('SESSION_TTL', $sessionLifetimeInTime);
    }
}
