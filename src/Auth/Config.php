<?php

namespace Leaf\Auth;

/**
 * Config for Leaf Auth
 * --------
 * Set/Get config to match your app
 *
 * @since 3.0.0
 * @version 0.1.0
 */
class Config
{
    /**
     * Resolve the JWT signing secret. A secret set in config wins, then
     * AUTH_TOKEN_SECRET from the env, then a secret derived from APP_KEY.
     * Without any of the three, token operations throw instead of signing
     * with a guessable default.
     * @return string
     */
    public static function tokenSecret(): string
    {
        $secret = static::get('token.secret');

        if ($secret) {
            return $secret;
        }

        $env = function (string $key) {
            if (function_exists('_envUncached')) {
                return _envUncached($key);
            }

            $value = $_ENV[$key] ?? getenv($key);

            return ($value === false || $value === '') ? null : $value;
        };

        if ($envSecret = $env('AUTH_TOKEN_SECRET')) {
            return $envSecret;
        }

        if ($appKey = $env('APP_KEY')) {
            return hash_hmac('sha256', 'leaf.auth.token.v1', $appKey);
        }

        throw new \RuntimeException(
            'No auth token secret is set. Set `token.secret` in your auth config, or export AUTH_TOKEN_SECRET in the environment your app actually runs with. In Leaf MVC you can run `leaf key:generate` instead; lite apps have no key:generate and do not load .env files.'
        );
    }

    /** @var array<string, mixed> Configuration for Leaf Auth */
    protected static array $config = [
        'id.key' => 'id',
        'db.table' => 'users',
        'roles.key' => 'leaf_auth_user_roles',

        'timestamps' => true,
        'timestamps.format' => 'YYYY-MM-DD HH:mm:ss',

        'password.encode' => null,
        'password.verify' => null,
        'password.key' => 'password',

        'unique' => ['email', 'username'],
        'hidden' => ['field.id', 'field.password', 'remember_token'],

        'session' => false,
        'session.lifetime' => 60 * 60 * 24,
        'session.cookie' => ['secure' => true, 'httponly' => true, 'samesite' => 'lax'],

        'token.lifetime' => 60 * 60 * 24 * 365,
        'token.secret' => null,

        'redirect.login' => '/auth/login',
        'redirect.guest' => '/dashboard',

        'messages.loginParamsError' => 'Incorrect credentials!',
        'messages.loginPasswordError' => 'Password is incorrect!',
    ];

    /** @var array<string, mixed> Additional user information for cache */
    protected static array $userCache = [];

    /**
     * Set Leaf Auth config
     * @param array<string, mixed> $config
     */
    public static function set($config): void
    {
        static::$config = array_merge(static::$config, $config);
    }

    /**
     * Overwrite Leaf Auth config
     * @param array<string, mixed> $config
     */
    public static function overwrite($config): void
    {
        static::$config = $config;
    }

    /**
     * Get Leaf Auth config
     * @param ?string $key
     * @return ?mixed
     */
    public static function get($key = null)
    {
        if ($key) {
            return static::$config[$key] ?? null;
        }

        return static::$config;
    }

    /**
     * Set user cache
     * @param string $key
     * @param mixed $value
     */
    public static function setUserCache($key, $value): void
    {
        if (isset($_SESSION)) {
            $_SESSION[$key] = $value;
        }

        static::$userCache[$key] = $value;
    }

    /**
     * Get user cache
     * @param ?string $key
     * @return mixed|array<string, mixed>
     */
    public static function getUserCache($key = null)
    {
        $cache = $_SESSION ?? static::$userCache;

        if ($key) {
            return $cache[$key] ?? null;
        }

        return $cache;
    }
}
