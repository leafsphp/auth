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
     * configuration for Leaf Auth
     * @var array
     */
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
        'hidden' => ['field.id', 'field.password'],

        'session' => false,
        'session.lifetime' => 60 * 60 * 24,
        'session.cookie' => ['secure' => true, 'httponly' => true, 'samesite' => 'lax'],

        'token.lifetime' => null,
        'token.secret' => '@_leaf$0Secret!',

        'messages.loginParamsError' => 'Incorrect credentials!',
        'messages.loginPasswordError' => 'Password is incorrect!',
    ];

    /**
     * Additional user information for cache
     */
    protected static array $userCache = [];

    /**
     * Set Leaf Auth config
     */
    public static function set($config): void
    {
        static::$config = array_merge(static::$config, $config);
    }

    /**
     * Overwrite Leaf Auth config
     */
    public static function overwrite($config): void
    {
        static::$config = $config;
    }

    /**
     * Get Leaf Auth config
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
