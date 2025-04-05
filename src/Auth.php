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
     * All errors caught
     * @var array
     */
    protected $errorsArray = [];

    public function __construct()
    {
        $this->middleware('auth.required', function () {
            response()->redirect('/auth/login');
        });

        $this->middleware('auth.guest', function () {
            response()->redirect('/dashboard');
        });

        $this->middleware('is', function ($role) {
            \Leaf\Exception\General::error(
                '404',
                '<p>The page you are looking for could not be found.</p>',
                403
            );
        });

        $this->middleware('isNot', function () {
            \Leaf\Exception\General::error(
                '404',
                '<p>The page you are looking for could not be found.</p>',
                403
            );
        });

        $this->middleware('can', function () {
            \Leaf\Exception\General::error(
                '404',
                '<p>The page you are looking for could not be found.</p>',
                403
            );
        });

        $this->middleware('cannot', function () {
            \Leaf\Exception\General::error(
                '404',
                '<p>The page you are looking for could not be found.</p>',
                403
            );
        });

        $this->middleware('auth.verified', function () {
            response()->redirect('/auth/verify');
        });

        $this->middleware('auth.unverified', function () {
            response()->redirect('/dashboard');
        });
    }

    /**
     * Connect leaf auth to the database
     * @param array $dbConfig Configuration for leaf db connection
     * @return $this
     */
    public function connect($dbConfig = [])
    {
        $this->db = new Db();
        $this->db->connect($dbConfig);

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

        return $this;
    }

    /**
     * Get/Set Leaf Auth config
     *
     * @param string|array $config The auth config key or array of config
     * @param mixed $value The value if $config is a string
     */
    public function config($config, $value = null)
    {
        if (is_string($config) && $value === null) {
            return Config::get($config);
        }

        Config::set(
            is_string($config)
            ? [$config => $value]
            : $config
        );
    }

    /**
     * Create roles and permissions
     *
     * @param array $roles Array of roles and their permissions
     * @return Auth
     */
    public function createRoles(array $roles)
    {
        Config::set([
            'roles' => $roles,
        ]);

        return $this;
    }

    /**
     * Return all roles and their permissions
     *
     * @return array
     */
    public function roles()
    {
        return Config::get('roles');
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

        try {
            $user = $this->db->select($table)->where($credentials)->first();

            if (!$user) {
                $this->errorsArray['auth'] = Config::get('messages.loginParamsError');
                return false;
            }
        } catch (\Throwable $th) {
            throw new \Exception($th->getMessage());
        }

        if ($passwordKey !== false) {
            $passwordIsValid = (Config::get('password.verify') !== false && isset($user[$passwordKey]))
                ? ((is_callable(Config::get('password.verify')))
                    ? call_user_func(Config::get('password.verify'), $userPassword, $user[$passwordKey])
                    : Password::verify($userPassword, $user[$passwordKey]))
                : false;

            if (!$passwordIsValid) {
                $this->errorsArray['password'] = Config::get('messages.loginPasswordError');
                return false;
            }
        }

        $this->user = new User($user);

        return true;
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
        $passwordEncode = Config::get('password.encode');

        if ($passwordEncode !== false && $passwordKey !== false) {
            $userData[$passwordKey] = (is_callable($passwordEncode))
                ? call_user_func($passwordEncode, $userData[$passwordKey])
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

        $user = $this->user();

        if (!$user) {
            return false;
        }

        $idKey = Config::get('id.key');
        $table = Config::get('db.table');

        if (Config::get('timestamps')) {
            $userData['updated_at'] = (new Date())->tick()->format(Config::get('timestamps.format'));
        }

        if (count(Config::get('unique')) > 0) {
            foreach (Config::get('unique') as $unique) {
                if (!isset($userData[$unique])) {
                    continue;
                }

                $data = $this->db->select($table, Config::get('id.key'))->where($unique, $userData[$unique])->first();

                if ($data && $data[Config::get('id.key')] !== $this->id()) {
                    $this->errorsArray[$unique] = "$unique already exists";
                }
            }

            if (count($this->errorsArray) > 0) {
                return false;
            }
        }

        try {
            $query = $this->db->update($table)->params($userData)->where($idKey, $this->user->{$idKey})->execute();

            if (!$query) {
                $this->errorsArray = array_merge($this->errorsArray, $this->db->errors());
                return false;
            }
        } catch (\Throwable $th) {
            throw new \Exception($th->getMessage());
        }

        if (Config::get('session')) {
            session_regenerate_id();
        }

        foreach ($userData as $key => $value) {
            $this->user->{$key} = $value;
        }

        return true;
    }

    /**
     * Update user password
     * ---
     * Update user password in the database
     *
     * @param string $oldPassword Old password
     * @param string $newPassword New password
     * @return bool
     */
    public function updatePassword(string $oldPassword, string $newPassword): bool
    {
        $this->checkDbConnection();

        $user = $this->user();

        if (!$user) {
            return false;
        }

        $passwordKey = Config::get('password.key');

        if (Config::get('password.verify') !== false && isset($user->{$passwordKey})) {
            $passwordIsValid = (is_callable(Config::get('password.verify')))
                ? call_user_func(Config::get('password.verify'), $oldPassword, $user->{$passwordKey})
                : Password::verify($oldPassword, $user->{$passwordKey});

            if (!$passwordIsValid) {
                $this->errorsArray['password'] = Config::get('messages.loginPasswordError');
                return false;
            }
        }

        $newPassword = (Config::get('password.encode') !== false)
            ? ((is_callable(Config::get('password.encode')))
                ? call_user_func(Config::get('password.encode'), $newPassword)
                : Password::hash($newPassword))
            : $newPassword;

        try {
            $query = $this->db->update(Config::get('db.table'))
                ->params([$passwordKey => $newPassword])
                ->where(Config::get('id.key'), $this->id())
                ->execute();

            if (!$query) {
                $this->errorsArray = array_merge($this->errorsArray, $this->db->errors());
                return false;
            }
        } catch (\Throwable $th) {
            throw new \Exception($th->getMessage());
        }

        $this->user->{$passwordKey} = $newPassword;

        return true;
    }

    /**
     * Create a new user from OAuth
     *
     * @param array $userData User data
     *
     * @return bool
     */
    public function fromOAuth(array $userData): bool
    {
        $initialPassword = Config::get('password.key');

        $this->checkDbConnection();
        $this->config('password.key', false);

        $user = $this->db->select(Config::get('db.table'))->where($userData['user'])->first();

        Config::setUserCache('oauth-token', $userData['token']);

        if (!$user) {
            $success = $this->register($userData['user']);
            $this->config('password.key', $initialPassword);

            return $success;
        }

        $this->user = new User($user);
        $this->config('password.key', $initialPassword);

        return true;
    }

    /**
     * Find a user by id
     * ---
     * Select and return an existing user from db
     *
     * @param string|int $id The id of the user to grab
     * @return User|null
     */
    public function find($id)
    {
        $this->checkDbConnection();

        $userData = $this->db->select(Config::get('db.table'))->where(Config::get('id.key'), $id)->first();

        if (!$userData) {
            return null;
        }

        return (new User($userData, false))->setDb($this->db);
    }

    /**
     * Create a new user
     * ---
     * Create an account for another user
     *
     * @param array The user details to save
     */
    public function createUserFor($userData)
    {
        $this->checkDbConnection();

        $table = Config::get('db.table');
        $passwordKey = Config::get('password.key');
        $passwordEncode = Config::get('password.encode');

        if ($passwordEncode !== false && $passwordKey !== false) {
            $userData[$passwordKey] = (is_callable($passwordEncode))
                ? call_user_func($passwordEncode, $userData[$passwordKey])
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

        return (new User($user, false))->setDb($this->db);
    }

    /**
     * Get saved OAuth token
     */
    public function oauthToken()
    {
        return Config::getUserCache('oauth-token');
    }

    /**
     * Sign a user out
     * ---
     * Sign out the currently authenticated user
     *
     * @param string|array|callable|null $redirectUrl Redirect to this url after logout
     * @return bool
     */
    public function logout($action = null): bool
    {
        if (Config::get('session')) {
            Session::unset('auth');
        }

        $this->user = null;

        if (is_callable($action)) {
            $action($this);
            return true;
        }

        if ($action) {
            response()->redirect($action);
            exit;
        }

        return true;
    }

    /**
     * Get the id of the currently authenticated user
     * @return string|int
     */
    public function id()
    {
        return Config::get('session')
            ? $this->getFromSession('auth.id')
            : ($this->user ? $this->user->id() : ($this->parseToken()['user.id'] ?? null));
    }

    /**
     * Get the currently authenticated user
     * @return User|null
     */
    public function user()
    {
        if (Config::get('session')) {
            $userId = $this->getFromSession('auth.id');

            if (!$userId) {
                return null;
            }
        }

        if ($this->user) {
            return $this->user->setDb($this->db);
        }

        $userId = $this->id();

        if (!$userId) {
            return null;
        }

        $this->checkDbConnection();

        $idKey = Config::get('id.key');
        $table = Config::get('db.table');

        try {
            $user = $this->db->select($table)->where($idKey, $userId)->first();

            if (!$user) {
                $this->errorsArray = $this->db->errors();
                return null;
            }
        } catch (\Throwable $th) {
            throw new \Exception($th->getMessage());
        }

        return $this->user = (new User(
            $user
        ))->setDb($this->db);
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
     * Get generated access tokens
     * @return array|null
     */
    public function tokens()
    {
        $user = $this->user();

        if (!$user) {
            return null;
        }

        return $user->tokens();
    }

    /**
     * Register auth middleware for your Leaf apps
     * @param string $middleware The middleware to register
     * @param callable $callback The callback to run if middleware fails
     */
    public function middleware(string $middleware, callable $callback)
    {
        if (!class_exists(\Leaf\App::class)) {
            throw new \Exception('This feature is only available for Leaf apps');
        }

        if ($middleware === 'auth.required') {
            return app()->registerMiddleware('auth.required', function () use ($callback) {
                if (!$this->user()) {
                    $callback();
                    exit;
                }
            });
        }

        if ($middleware === 'auth.guest') {
            return app()->registerMiddleware('auth.guest', function () use ($callback) {
                if ($this->user()) {
                    $callback();
                    exit;
                }

                auth()->clearErrors();
            });
        }

        if ($middleware === 'is') {
            return app()->registerMiddleware('is', function ($role) use ($callback) {
                if (!$this->user() || $this->user()?->isNot($role)) {
                    $callback($role);
                    exit;
                }
            });
        }

        if ($middleware === 'isNot') {
            return app()->registerMiddleware('isNot', function ($role) use ($callback) {
                if (!$this->user() || $this->user()?->is($role)) {
                    $callback($role);
                    exit;
                }
            });
        }

        if ($middleware === 'can') {
            return app()->registerMiddleware('can', function ($role) use ($callback) {
                if (!$this->user() || $this->user()?->cannot($role)) {
                    $callback($role);
                    exit;
                }
            });
        }

        if ($middleware === 'cannot') {
            return app()->registerMiddleware('cannot', function ($role) use ($callback) {
                if (!$this->user() || $this->user()?->can($role)) {
                    $callback($role);
                    exit;
                }
            });
        }

        if ($middleware === 'auth.verified') {
            return app()->registerMiddleware('auth.verified', function () use ($callback) {
                if (!$this->user() || !$this->user()->isVerified()) {
                    $callback();
                    exit;
                }
            });
        }

        if ($middleware === 'auth.unverified') {
            return app()->registerMiddleware('auth.unverified', function () use ($callback) {
                if (!$this->user() || $this->user()->isVerified()) {
                    $callback();
                    exit;
                }
            });
        }

        app()->registerMiddleware($middleware, $callback);
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

        try {
            return (array) JWT::decode(
                $bearerToken,
                new Key(Config::get('token.secret'), 'HS256')
            );
        } catch (\Throwable $th) {
            $this->errorsArray['token'] = $th->getMessage();
            return null;
        }
    }

    /**
     * Verify a user's token
     * @param string $token The token to verify
     */
    public function verifyToken(string $token)
    {
        try {
            $decodedToken = (array) JWT::decode(
                $token,
                new Key(Config::get('token.secret') . '-verification', 'HS256')
            );

            if (!isset($decodedToken['user.email'])) {
                $this->errorsArray['token'] = 'Invalid token';
                return null;
            }

            $user = $this->find($decodedToken['user.id']);

            if (!$user) {
                $this->errorsArray['token'] = 'User not found';
                return null;
            }

            if ($user->email !== $decodedToken['user.email']) {
                $this->errorsArray['token'] = 'Invalid token';
                return null;
            }

            return true;
        } catch (\Throwable $th) {
            $this->errorsArray['token'] = $th->getMessage();
            return null;
        }
    }

    /**
     * Return the current db instance
     *
     * @return Db
     */
    public function db()
    {
        return $this->db;
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
        if ($this->checkAndExpireSession()) {
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

    protected function checkAndExpireSession(): bool
    {
        $sessionTtl = Session::get('auth.ttl');

        if (!$sessionTtl) {
            return false;
        }

        $isSessionExpired = time() > $sessionTtl;

        if ($isSessionExpired) {
            Session::unset('auth');
        }

        return $isSessionExpired;
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
        return Session::get('auth.token');
    }

    /**
     * Clear all errors caught
     */
    public function clearErrors()
    {
        $this->errorsArray = [];
    }

    /**
     * Return all errors caught
     */
    public function errors(): array
    {
        return $this->errorsArray;
    }
}
