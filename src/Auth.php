<?php

namespace Leaf;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Leaf\Auth\Config;
use Leaf\Auth\User;
use Leaf\Helpers\Password;
use Leaf\Http\Session;

/**
 * Leaf Simple Auth
 * -------------------------
 * Simple, lightweight authentication.
 *
 * @author Michael Darko
 * @since 1.5.0
 * @version 3.0.0
 */
class Auth
{
    /**
     * The currently authenticated user
     * @var User
     */
    protected $user;

    /**
     * Internal instance of Leaf DB
     * @var Db
     */
    protected $db;

    /**
     * Internal instance of Leaf session
     * @var Session
     */
    protected $session;

    /**
     * All errors caught
     * @var array
     */
    protected $errorsArray = [];

    /**
     * Connect leaf auth to the database
     * @param array $dbConfig Configuration for leaf db connection
     * @return $this
     */
    public function connect($dbConfig = [])
    {
        $this->db = new Db();
        $this->db->connect($dbConfig);

        if (Config::get('session')) {
            $this->initSession(Config::get('session.cookie'));
        }

        return $this;
    }

    /**
     * Connect to database using environment variables
     *
     * @param array $pdoOptions Options for PDO connection
     * @return $this
     */
    public function autoConnect(array $pdoOptions = [])
    {
        $this->db = new Db();
        $this->db->autoConnect($pdoOptions);

        if (Config::get('session')) {
            $this->initSession(Config::get('session.cookie'));
        }

        return $this;
    }

    /**
     * Pass in db connection instance directly
     * 
     * @param \PDO $connection A connection instance of your db
     * @return $this;
     */
    public function dbConnection(\PDO $connection)
    {
        $this->db = new Db();
        $this->db->connection($connection);

        if (Config::get('session')) {
            $this->initSession(Config::get('session.cookie'));
        }

        return $this;
    }

    /**
     * Sign a user in
     * ---
     * Verify user credentials and sign them in with token or session
     * 
     * @param array $credentials User credentials
     * @return bool
     */
    public function login(array $credentials): bool
    {
        $this->checkDbConnection();

        $table = Config::get('db.table');
        $passwordKey = Config::get('password.key');

        $userPassword = $credentials[$passwordKey] ?? null;

        if ($userPassword) {
            unset($credentials[$passwordKey]);
        }

        $user = $this->db->select($table)->where($credentials)->first();

        if (!$user) {
            $this->errorsArray['auth'] = Config::get('messages.loginParamsError');
            return false;
        }

        $passwordIsValid = (Config::get('password.verify') !== false && isset($user[$passwordKey]))
            ? ((is_callable(Config::get('password.verify')))
                ? call_user_func(Config::get('password.verify'), $userPassword, $user[$passwordKey])
                : Password::verify($userPassword, $user[$passwordKey]))
            : false;

        if (!$passwordIsValid) {
            $this->errorsArray['password'] = Config::get('messages.loginPasswordError');
            return false;
        }

        echo json_encode($user);

        return false;
    }

    /**
     * Register a new user
     * ---
     * Save a new user to the database
     * 
     * @param array $userData User data
     * @return bool
     */
    public function register(array $userData): bool
    {
        $this->checkDbConnection();

        $table = Config::get('db.table');
        $passwordKey = Config::get('password.key');

        if (Config::get('password.encode') !== false) {
            $userData[$passwordKey] = (is_callable(Config::get('password.encode')))
                ? call_user_func(Config::get('password.encode'), $userData[$passwordKey])
                : Password::hash($userData[$passwordKey]);
        }

        if (Config::get('timestamps')) {
            $now = (new Date())->tick()->format(Config::get('timestamps.format'));
            $userData['created_at'] = $now;
            $userData['updated_at'] = $now;
        }

        if (isset($credentials[Config::get('id.key')])) {
            $userData[Config::get('id.key')] = is_callable($userData[Config::get('id.key')])
                ? call_user_func($userData[Config::get('id.key')])
                : $userData[Config::get('id.key')];
        }

        try {
            $query = $this->db->insert($table)->params($userData)->unique(Config::get('unique'))->execute();

            if (!$query) {
                $this->errorsArray = array_merge($this->errorsArray, $this->db->errors());
                return false;
            }
        } catch (\Throwable $th) {
            throw new \Exception($th->getMessage());
        }

        $user = $this->db->select($table)->where($userData)->first();

        if (!$user) {
            $this->errorsArray = array_merge($this->errorsArray, $this->db->errors());
            return false;
        }

        $this->user = new User($user);

        return true;
    }

    /**
     * Update user data
     * ---
     * Update user data in the database
     * 
     * @param array $userData User data
     * @return bool
     */
    public function update(array $userData): bool
    {
        $this->checkDbConnection();
        return false;
    }

    /**
     * Get the id of the currently authenticated user
     * @return string|int
     */
    public function id()
    {
        if ($this->user) {
            return $this->user->id();
        }

        return Config::get('session')
            ? $this->getFromSession('auth.id')
            : ($this->parseToken()['user.id'] ?? null);
    }

    /**
     * Get the currently authenticated user
     * @return User|null
     */
    public function user()
    {
        if ($this->user) {
            return $this->user;
        }

        $userId = $this->id();

        if (!$userId) {
            return null;
        }

        $idKey = Config::get('id.key');
        $table = Config::get('db.table');

        $user = $this->db->select($table)->where($idKey, $userId)->first();

        if (!$user) {
            $this->errorsArray = $this->db->errors();
            return null;
        }

        $hidden = Config::get('hidden');

        if (count($hidden) > 0) {
            foreach ($hidden as $item) {
                if (isset($user[$item])) {
                    unset($user[$item]);
                }
            }
        }

        return $this->user = new User(
            $user
        );
    }

    /**
     * Get data generated on user login
     * @return object|null
     */
    public function data()
    {
        $user = $this->user();

        if (!$user) {
            return null;
        }

        return $user->getAuthInfo();
    }

    /**
     * Parse the current user's token
     */
    public function parseToken()
    {
        $bearerToken = $this->getTokenFromRequest();

        if ($bearerToken === null) {
            return null;
        }

        return (array) JWT::decode(
            $bearerToken,
            new Key(Config::get('token.secret'), 'HS256')
        );
    }

    protected function checkDbConnection(): void
    {
        if (!$this->db && function_exists('db')) {
            if (db()->connection() instanceof \PDO || db()->autoConnect()) {
                $this->db = db();
            }
        }

        if (!$this->db) {
            throw new \Exception('You need to connect to your database first');
        }
    }

    protected function getFromSession($value)
    {
        if ($this->isSessionExpired()) {
            return null;
        }

        return Session::get($value);
    }

    protected function sessionCheck()
    {
        if (!Config::get('session')) {
            throw new \Exception('Turn on sessions to use this feature.');
        }
    }

    protected function isSessionExpired(): bool
    {
        $sessionTtl = $this->session->get('session.ttl');

        if (!$sessionTtl) {
            return false;
        }

        $isSessionExpired = time() > $sessionTtl;

        if ($isSessionExpired) {
            $this->session->unset('auth.user');
            $this->session->unset('auth.id');
            $this->session->unset('auth.token');
            $this->session->unset('session.startedAt');
            $this->session->unset('session.lastActivity');
            $this->session->unset('session.ttl');
        }

        return $isSessionExpired;
    }

    protected function initSession(array $sessionCookieParams = [])
    {
        $session = new Session(false);

        if (!isset($_SESSION)) {
            session_set_cookie_params($sessionCookieParams);
            session_start();
        }

        if (!$session->has('session.startedAt')) {
            $session->set('session.startedAt', time());
        }

        $session->set('session.lastActivity', time());

        $this->session = $session;
    }

    protected function getTokenFromRequest()
    {
        $headers = null;

        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));

            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }

        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }

        $this->errorsArray['token'] = 'Access token not found';

        return null;
    }

    protected function getTokenFromSession()
    {
        return \Leaf\Http\Session::get('auth.token');
    }

    /**
     * Return all errors caught
     */
    public function errors(): array
    {
        return $this->errorsArray;
    }
}
