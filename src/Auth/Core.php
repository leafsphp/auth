<?php

namespace Leaf\Auth;

use Leaf\Helpers\Authentication;

/**
 * Leaf Simple Auth [Core]
 * -------------------------
 * Authentication made easy.
 *
 * @author Michael Darko
 * @since 3.0
 * @version 2.0.0
 */
class Core
{
    private const TIMESTAMP_OF_ONE_DAY = 60 * 60 * 24;

    /**
     * All errors caught
     */
    protected static $errors = [];

    /**
     * Auth Settings
     */
    protected static $settings = [
        'id.key' => 'id',
        'id.uuid' => null,

        'db.table' => 'users',

        'timestamps' => true,
        'timestamps.format' => 'c',

        'password' => true,
        'password.encode' => null,
        'password.verify' => null,
        'password.key' => 'password',

        'unique' => ['email', 'username'],
        'hidden' => ['field.id', 'field.password'],

        'session' => false,
        'session.logout' => null,
        'session.register' => null,
        'session.lifetime' => self::TIMESTAMP_OF_ONE_DAY,
        'session.cookie' => ['secure' => true, 'httponly' => true, 'samesite' => 'lax'],
    
        'token.lifetime' => null,
        'token.secret' => '@_leaf$0Secret!',

        'messages.loginParamsError' => 'Incorrect credentials!',
        'messages.loginPasswordError' => 'Password is incorrect!',
    ];

    /**
     * @var \Leaf\Db
     */
    protected static $db;

    /**
     * @var \Leaf\Http\Session
     */
    protected static $session;

    /**
     * Connect leaf auth to the database
     *
     * @param string|array $host Host Name or full config
     * @param string $dbname Database name
     * @param string $user Database username
     * @param string $password Database password
     * @param string $dbtype Type of database: mysql, postgres, sqlite, ...
     * @param array $pdoOptions Options for PDO connection
     */
    public static function connect(
        $host,
        string $dbname,
        string $user,
        string $password,
        string $dbtype,
        array $pdoOptions = []
    ) {
        $db = new \Leaf\Db();
        $db->connect($host, $dbname, $user, $password, $dbtype, $pdoOptions);
        static::$db = $db;
    }

    /**
     * Connect to database using environment variables
     *
     * @param array $pdoOptions Options for PDO connection
     */
    public static function autoConnect(array $pdoOptions = [])
    {
        $db = new \Leaf\Db();
        $db->autoConnect($pdoOptions);
        static::$db = $db;
    }

    /**
     * Pass in db connetion instance directly
     *
     * @param \PDO $connection A connection instance of your db
     */
    public static function dbConnection(\PDO $connection)
    {
        $db = new \Leaf\Db();
        $db->connection($connection);
        static::$db = $db;
    }

    /**
     * Auto connect to leaf db
     */
    protected static function leafDbConnect()
    {
        if (!static::$db && function_exists('db')) {
            if (db()->connection() instanceof \PDO || db()->autoConnect()) {
                static::$db = db();
            }
        }

        if (!static::$db) {
            trigger_error('You need to connect to your database first');
        }
    }

    /**
     * Set auth config
     *
     * @param string|array $config The auth config key or array of config
     * @param mixed $value The value if $config is a string
     */
    public static function config($config, $value = null)
    {
        if (is_array($config)) {
            foreach ($config as $key => $configValue) {
                static::config($key, $configValue);
            }
        } else {
            if ($value === null) {
                return static::$settings[$config] ?? null;
            }

            static::$settings[$config] = $value;
        }
    }

    /**
     * Validate Json Web Token
     *
     * @param string $token The token validate
     * @param string $secretKey The secret key used to encode token
     */
    public static function validateUserToken(string $token, ?string $secretKey = null)
    {
        $payload = Authentication::validate($token, $secretKey ?? static::config("token.secret"));
        if ($payload) return $payload;

        static::$errors = array_merge(static::$errors, Authentication::errors());

        return null;
    }

    /**
     * Validate Bearer Token
     *
     * @param string $secretKey The secret key used to encode token
     */
    public static function validateToken(?string $secretKey = null)
    {
        $payload = Authentication::validateToken($secretKey ?? static::config("token.secret"));
        if ($payload) return $payload;

        static::$errors = array_merge(static::$errors, Authentication::errors());

        return null;
    }

    /**
     * Get Bearer token
     */
    public static function getBearerToken()
    {
        $token = Authentication::getBearerToken();
        if ($token) return $token;

        static::$errors = array_merge(static::$errors, Authentication::errors());

        return null;
    }

    /**
     * Get all authentication errors as associative array
     */
    public static function errors(): array
    {
        return static::$errors;
    }
}
