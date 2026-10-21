<?php

declare(strict_types=1);

namespace Leaf;

use League\OAuth2\Client\Provider\AbstractProvider;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Leaf\Auth\Config;
use Leaf\Auth\User;
use Leaf\Crash\Pages;
use Leaf\Helpers\Password;
use Leaf\Http\Session;
use League\OAuth2\Client\Provider\Google;
use PDO;
use Throwable;

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
    /** @var ?User The currently authenticated user */
    protected $user;

    /** @var ?Db Internal instance of Leaf DB */
    protected $db;

    /** All errors caught */
    protected array $errorsArray = [];

    /** Configured oauth clients */
    protected array $oauthClients = [];

    public function __construct()
    {
        if (class_exists(App::class)) {
            $this->middleware('auth.required', static function (): void {
                response()->redirect(Config::get('redirect.login') ?? '/auth/login');
            });

            $this->middleware('auth.guest', static function (): void {
                response()->redirect(Config::get('redirect.guest') ?? '/dashboard');
            });

            $this->middleware('is', static function (): void {
                Pages::error(
                    'Page not found',
                    'The page you are looking for could not be found.',
                    403,
                );
            });

            $this->middleware('isNot', static function (): void {
                Pages::error(
                    'Page not found',
                    'The page you are looking for could not be found.',
                    403,
                );
            });

            $this->middleware('can', static function (): void {
                Pages::error(
                    'Page not found',
                    'The page you are looking for could not be found.',
                    403,
                );
            });

            $this->middleware('cannot', static function (): void {
                Pages::error(
                    'Page not found',
                    'The page you are looking for could not be found.',
                    403,
                );
            });

            $this->middleware('auth.verified', static function (): void {
                response()->redirect('/auth/verify');
            });

            $this->middleware('auth.unverified', static function (): void {
                response()->redirect('/dashboard');
            });
        }

        if (
            $this->env('GOOGLE_AUTH_CLIENT_ID')
            && $this->env('GOOGLE_AUTH_CLIENT_SECRET')
            && class_exists(Google::class)
        ) {
            $this->withGoogle(
                $this->env('GOOGLE_AUTH_CLIENT_ID'),
                $this->env('GOOGLE_AUTH_CLIENT_SECRET'),
                [
                    'name' => 'google',
                    'redirectUri' => $this->env(
                        'GOOGLE_AUTH_REDIRECT_URI',
                        $this->env('APP_URL') . '/auth/register/google',
                    ),
                ],
            );
        }
    }

    /**
     * Read an environment value, with or without leaf core around.
     * Reads live rather than through _env()'s per-process cache: these
     * reads happen once during setup (e.g. resolving the Google redirect
     * URI), so the uncached read costs nothing and stays honest when the
     * environment is set at runtime.
     */
    private function env(string $name, mixed $default = false): mixed
    {
        if (function_exists('_envUncached')) {
            return _envUncached($name, $default);
        }

        // Return the value if found, otherwise $default.
        return $_ENV[$name] ?? $default;
    }

    /**
     * Connect leaf auth to the database
     * @param array{
     *     host?: string,
     *     dbname?: string,
     *     username?: string,
     *     password?: string,
     *     dbtype?: string,
     *     pdoOptions?: mixed[],
     * } $dbConfig Configuration for leaf db connection
     */
    public function connect($dbConfig = []): static
    {
        $this->db = new Db();
        $this->db->connect($dbConfig);

        return $this;
    }

    /**
     * Connect to database using environment variables
     *
     * @param mixed[] $pdoOptions Options for PDO connection
     */
    public function autoConnect(array $pdoOptions = []): static
    {
        $this->db = new Db();
        $this->db->autoConnect($pdoOptions);

        return $this;
    }

    /**
     * Pass in db connection instance directly
     *
     * @param PDO $connection A connection instance of your db
     */
    public function dbConnection(PDO $connection): static
    {
        $this->db = new Db();
        $this->db->connection($connection);

        return $this;
    }

    /**
     * Register a Google OAuth client
     * ---
     * Register a Google OAuth client to use with Leaf Auth, should be a league/oauth2-client compatible client.
     * @param array{
     *     name?: string,
     *     redirectUri?: string,
     * } $options If `$options['redirectUri']` is not set, it will default to the value of `APP_URL` (as resolved by this class's environment helper) with `/auth/google/callback` appended; if `APP_URL` is unset or empty, the default will be `/auth/google/callback`.
     */
    public function withGoogle(
        string $clientId,
        string $clientSecret,
        array $options = [],
    ): static {
        $clientName = $options['name'] ?? 'google';

        unset($options['name']);

        $options['redirectUri'] ??= $this->env('APP_URL') . '/auth/google/callback';

        $this->withProvider($clientName, new Google(array_merge([
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri' => $options['redirectUri'],
        ], $options)));

        return $this;
    }

    /**
     * Register a generic OAuth client
     * ---
     * Register a generic OAuth client to use with Leaf Auth, should be a league/oauth2-client compatible client.
     * @param string $clientName The name of the client to register
     * @param AbstractProvider $client An instance of a league/oauth2-client compatible client
     */
    public function withProvider(string $clientName, $client): static
    {
        $this->oauthClients[$clientName] = $client;
        return $this;
    }

    /**
     * Return an oauth client
     * @param string $clientName The name of the client to return
     * @return AbstractProvider|null
     */
    public function client(string $clientName)
    {
        return $this->oauthClients[$clientName] ?? null;
    }

    /**
     * Get/Set Leaf Auth config
     *
     * @param string|array<string, mixed> $config The auth config key or array of config
     * @param mixed $value The value if $config is a string
     */
    public function config($config, mixed $value = null): mixed
    {
        if (is_string($config) && $value === null) {
            return Config::get($config);
        }

        Config::set(
            is_string($config)
            ? [$config => $value]
            : $config
        );

        return null;
    }

    /**
     * Create roles and permissions
     *
     * @param array<string, string[]> $roles Array of roles and their permissions
     */
    public function createRoles(array $roles): static
    {
        Config::set([
            'roles' => $roles,
        ]);

        return $this;
    }

    /** Return all roles and their permissions */
    public function roles(): ?array
    {
        return Config::get('roles');
    }

    /**
     * Sign a user in
     * ---
     * Verify user credentials and sign them in with token or session
     *
     * @param array<string, mixed> $credentials User credentials
     */
    public function login(array $credentials): bool
    {
        $this->errorsArray = [];

        $this->checkDbConnection();

        $table = Config::get('db.table');
        $passwordKey = Config::get('password.key');

        $userPassword = $credentials[$passwordKey] ?? null;

        if ($userPassword) {
            unset($credentials[$passwordKey]);
        }

        if (isset($credentials['email'])) {
            $credentials['email'] = strtolower($credentials['email']);
        }

        try {
            $user = $this->db->select($table)->where($credentials)->first();

            if (!$user) {
                $this->errorsArray['auth'] = Config::get('messages.loginParamsError');
                return false;
            }
        } catch (Throwable $throwable) {
            throw new Exception($throwable->getMessage(), $throwable->getCode(), $throwable);
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
     * @param array<string, mixed> $userData User data
     */
    public function register(array $userData): bool
    {
        // each operation owns its error state: stale errors from a
        // previous call must never leak into this result
        $this->errorsArray = [];

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

        if (isset($userData['email'])) {
            $userData['email'] = strtolower($userData['email']);
        }

        if (isset($userData[Config::get('id.key')])) {
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
        } catch (Throwable $throwable) {
            throw new Exception($throwable->getMessage(), $throwable->getCode(), $throwable);
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
     * @param array<string, mixed> $userData User data
     */
    public function update(array $userData): bool
    {
        // each operation owns its error state: stale errors from a
        // previous call must never leak into this result
        $this->errorsArray = [];

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

        if (isset($userData['email'])) {
            $userData['email'] = strtolower($userData['email']);
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
        } catch (Throwable $throwable) {
            throw new Exception($throwable->getMessage(), $throwable->getCode(), $throwable);
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
        } catch (Throwable $throwable) {
            throw new Exception($throwable->getMessage(), $throwable->getCode(), $throwable);
        }

        $this->user->{$passwordKey} = $newPassword;

        return true;
    }

    /**
     * Create a new user from OAuth
     *
     * @param array<string, mixed> $userData User data
     */
    public function fromOAuth(array $userData): bool
    {
        $initialPassword = Config::get('password.key');

        $this->checkDbConnection();
        $this->config('password.key', value: false);

        $user = $this->db->select(Config::get('db.table'))
            ->where([
                'email' => $userData['user']['email'] ?? null,
            ])
            ->first();

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
     */
    public function find($id): ?User
    {
        $this->checkDbConnection();

        $userData = $this->db->select(Config::get('db.table'))->where(Config::get('id.key'), $id)->first();

        if (!$userData) {
            return null;
        }

        return (new User($userData, session: false))->setDb($this->db);
    }

    /**
     * Create a new user
     * ---
     * Create an account for another user
     *
     * @param array<string, mixed> $userData The user details to save
     * @throws Exception If database connection is not established
     */
    public function createUserFor($userData): false|User
    {
        // each operation owns its error state: stale errors from a
        // previous call must never leak into this result
        $this->errorsArray = [];

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

        if (isset($userData['email'])) {
            $userData['email'] = strtolower($userData['email']);
        }

        if (isset($userData[Config::get('id.key')])) {
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
        } catch (Throwable $throwable) {
            throw new Exception($throwable->getMessage(), $throwable->getCode(), $throwable);
        }

        $user = $this->db->select($table)->where($userData)->first();

        if (!$user) {
            $this->errorsArray = array_merge($this->errorsArray, $this->db->errors());
            return false;
        }

        return (new User($user, session: false))->setDb($this->db);
    }

    /** Get saved OAuth token */
    public function oauthToken(): mixed
    {
        return Config::getUserCache('oauth-token');
    }

    /**
     * Sign a user out
     * ---
     * Sign out the currently authenticated user
     *
     * @param string|mixed[]|callable|null $action Redirect to this url after logout
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

    /** Get the id of the currently authenticated user */
    public function id(): string|int
    {
        return Config::get('session')
            ? $this->getFromSession('auth.id')
            : ($this->user ? $this->user->id() : ($this->parseToken()['user.id'] ?? null));
    }

    /** Get the currently authenticated user */
    public function user(): ?User
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
        } catch (Throwable $throwable) {
            throw new Exception($throwable->getMessage(), $throwable->getCode(), $throwable);
        }

        return $this->user = (new User(
            $user
        ))->setDb($this->db);
    }

    /** Get data generated on user login */
    public function data(): ?object
    {
        $user = $this->user();

        if (!$user) {
            return null;
        }

        return $user->getAuthInfo();
    }

    /** Get generated access tokens */
    public function tokens(): ?array
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
     * @throws Exception If not used with leafs/leaf installed
     */
    public function middleware(string $middleware, callable $callback): void
    {
        if (!class_exists(App::class)) {
            throw new Exception('This feature is only available for Leaf apps');
        }

        if ($middleware === 'auth.required') {
            app()->registerMiddleware('auth.required', function () use ($callback): void {
                if (!$this->user()) {
                    $callback();
                    exit;
                }
            });

            return;
        }

        if ($middleware === 'auth.guest') {
            app()->registerMiddleware('auth.guest', function () use ($callback): void {
                if ($this->user()) {
                    $callback();
                    exit;
                }

                auth()->clearErrors();
            });

            return;
        }

        if ($middleware === 'is') {
            app()->registerMiddleware('is', function ($role) use ($callback): void {
                if (!$this->user() || $this->user()->isNot($role)) {
                    $callback($role);
                    exit;
                }
            });

            return;
        }

        if ($middleware === 'isNot') {
            app()->registerMiddleware('isNot', function ($role) use ($callback): void {
                if (!$this->user() || $this->user()->is($role)) {
                    $callback($role);
                    exit;
                }
            });

            return;
        }

        if ($middleware === 'can') {
            app()->registerMiddleware('can', function ($role) use ($callback): void {
                if (!$this->user() || $this->user()->cannot($role)) {
                    $callback($role);
                    exit;
                }
            });

            return;
        }

        if ($middleware === 'cannot') {
            app()->registerMiddleware('cannot', function ($role) use ($callback): void {
                if (!$this->user() || $this->user()->can($role)) {
                    $callback($role);
                    exit;
                }
            });

            return;
        }

        if ($middleware === 'auth.verified') {
            app()->registerMiddleware('auth.verified', function () use ($callback): void {
                if (!$this->user() || !$this->user()->isVerified()) {
                    $callback();
                    exit;
                }
            });

            return;
        }

        if ($middleware === 'auth.unverified') {
            app()->registerMiddleware('auth.unverified', function () use ($callback): void {
                if (!$this->user() || $this->user()->isVerified()) {
                    $callback();
                    exit;
                }
            });

            return;
        }

        app()->registerMiddleware($middleware, $callback);
    }

    /**
     * Parse the current user's token
     */
    public function parseToken(): ?array
    {
        $bearerToken = $this->getTokenFromRequest();

        if ($bearerToken === null) {
            return null;
        }

        try {
            return (array) JWT::decode(
                $bearerToken,
                new Key(Config::tokenSecret(), 'HS256')
            );
        } catch (Throwable $throwable) {
            $this->errorsArray['token'] = $throwable->getMessage();
            return null;
        }
    }

    /**
     * Verify a user's token
     * @param string $token The token to verify
     */
    public function verifyToken(string $token, ?string $purpose = null): ?User
    {
        try {
            $decodedToken = (array) JWT::decode(
                $token,
                new Key(Config::tokenSecret() . '-verification', 'HS256')
            );

            if (!isset($decodedToken['user.email'])) {
                $this->errorsArray['token'] = 'Invalid token';
                return null;
            }

            $user = $this->find($decodedToken['user.id']);

            if (!$user instanceof User) {
                $this->errorsArray['token'] = 'User not found';
                return null;
            }

            if ($user->email !== $decodedToken['user.email']) {
                $this->errorsArray['token'] = 'Invalid token';
                return null;
            }

            if ($purpose && (!isset($decodedToken['token.purpose']) || $decodedToken['token.purpose'] !== $purpose)) {
                $this->errorsArray['token'] = 'Invalid token';
                return null;
            }

            return $user;
        } catch (Throwable $throwable) {
            $this->errorsArray['token'] = $throwable->getMessage();
            return null;
        }
    }

    /** Return the current db instance */
    public function db(): ?Db
    {
        return $this->db;
    }

    /** @phpstan-assert Db $this->db */
    protected function checkDbConnection(): void
    {
        if (!$this->db && function_exists('db')) {
            if (db()->connection() instanceof PDO || db()->autoConnect()) {
                $this->db = db();
            }
        }

        if (!$this->db) {
            throw new Exception('You need to connect to your database first');
        }
    }

    /** @param string|array<string, mixed> $value */
    protected function getFromSession($value): mixed
    {
        if ($this->checkAndExpireSession()) {
            return null;
        }

        return Session::get($value);
    }

    /** @throws Exception If sessions are not enabled */
    protected function sessionCheck(): void
    {
        if (!Config::get('session')) {
            throw new Exception('Turn on sessions to use this feature.');
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

    protected function getTokenFromRequest(): ?string
    {
        $headers = null;

        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
            $requestHeaders = array_combine(array_map(ucwords(...), array_keys($requestHeaders)), array_values($requestHeaders));

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

    protected function getTokenFromSession(): mixed
    {
        return Session::get('auth.token');
    }

    /**
     * Clear all errors caught
     */
    public function clearErrors(): void
    {
        $this->errorsArray = [];
    }

    /** Return all errors caught */
    public function errors(): array
    {
        return $this->errorsArray;
    }
}
