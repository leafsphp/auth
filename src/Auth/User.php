<?php

namespace Leaf\Auth;

use Firebase\JWT\JWT;
use Leaf\Date;
use Leaf\Helpers\Password;
use Leaf\Http\Session;

/**
 * Auth User
 * ----
 * Class representing a user
 *
 * @since 3.0.0
 * @version 1.0.0
 * @property mixed $email
 */
class User
{
    use UsesRoles;
    use UsesSubscriptions;

    /**
     * Internal instance of Leaf database
     * @var \Leaf\Db
     */
    protected $db;

    /**
     * Internal instance of Leaf session
     * @var Session
     */
    protected $session;

    /** @var array<string, mixed> User Information */
    protected array $data = [];

    /**
     * @var array{
     *     access?: string,
     *     refresh?: string,
     * } User Tokens
     */
    protected array $tokens = [];

    /**
     * All errors caught
     * @var array<string, string>
     */
    protected $errorsArray = [];

    /**
     * @param array<string, mixed> $data
     * @param bool $session
     */
    public function __construct($data, $session = true)
    {
        $this->data = $data;

        $sessionLifetime = Config::get('token.lifetime');

        if (Config::get('session') && $session) {
            $sessionLifetime = Config::get('session.lifetime');

            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_set_cookie_params(Config::get('session.cookie'));
                session_start();
            }

            session_regenerate_id();

            if (!Session::has('auth.startedAt')) {
                Session::set('auth.startedAt', time());
            }

            Session::set('auth.lastActivity', time());
            Session::set('auth.id', $this->id());
            Session::set('auth.user', $this->get());

            if ($sessionLifetime !== 0 && $sessionLifetime !== null) {
                if (!is_int($sessionLifetime)) {
                    $sessionLifetime = strtotime($sessionLifetime);

                    if (!$sessionLifetime) {
                        throw new \Exception('Invalid session lifetime');
                    }
                } else {
                    $sessionLifetime = time() + $sessionLifetime;
                }

                Session::set('auth.ttl', $sessionLifetime);
            }
        }

        $sessionLifetime = $sessionLifetime && !is_numeric($sessionLifetime)
            ? strtotime($sessionLifetime)
            : (time() + intval($sessionLifetime));

        $this->tokens['access'] = $this->generateToken($sessionLifetime);
        $this->tokens['refresh'] = $this->generateToken($sessionLifetime + 259200);

        if ($data[Config::get('roles.key')] ?? null) {
            $this->setRolesAndPermissions(json_decode($data[Config::get('roles.key')], true));
        }
    }

    /**
     * Return the id of current user
     * @return string|int
     */
    public function id()
    {
        return $this->data[Config::get('id.key')] ?? null;
    }

    /**
     * Update user data
     * ---
     * Update user data in the database
     *
     * @param array{
     *   email?: string,
     * } | array<string, mixed> $userData User data
     * @return bool
     */
    public function update(array $userData): bool
    {
        $user = $this->get();

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

        if (\count(Config::get('unique')) > 0) {
            foreach (Config::get('unique') as $unique) {
                if (!isset($userData[$unique])) {
                    continue;
                }

                $data = $this->db->select($table, Config::get('id.key'))->where($unique, $userData[$unique])->first();

                if ($data && $data[Config::get('id.key')] !== $this->id()) {
                    $this->errorsArray[$unique] = "$unique already exists";
                }
            }

            if (\count($this->errorsArray) > 0) {
                return false;
            }
        }

        try {
            $query = $this->db->update($table)->params($userData)->where($idKey, $this->id())->execute();

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
            $this->data[$key] = $value;
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
        $user = $this->get();

        if (!$user) {
            return false;
        }

        $passwordKey = Config::get('password.key');

        if (Config::get('password.verify') !== false && isset($user[$passwordKey])) {
            $passwordIsValid = (is_callable(Config::get('password.verify')))
                ? call_user_func(Config::get('password.verify'), $oldPassword, $user[$passwordKey])
                : Password::verify($oldPassword, $user[$passwordKey]);

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

        $this->data[$passwordKey] = $newPassword;

        return true;
    }

    /**
     * Reset user password
     * ---
     * Reset user password in the database
     *
     * @param string $newPassword New password
     * @return bool
     */
    public function resetPassword(string $newPassword): bool
    {
        $user = $this->get();

        if (!$user) {
            return false;
        }

        $passwordKey = Config::get('password.key');
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

        $this->data[$passwordKey] = $newPassword;

        return true;
    }

    /**
     * Get auth information to be sent to the client
     * @return object
     */
    public function getAuthInfo(): object
    {
        $dataToReturn = (object) [
            'user' => $this->get(),
            'accessToken' => $this->tokens['access'] ?? null,
            'refreshToken' => $this->tokens['refresh'] ?? null,
        ];

        if (count($this->roles)) {
            $dataToReturn->roles = $this->roles;
        }

        if (count($this->permissions)) {
            $dataToReturn->permissions = $this->permissions;
        }

        return $dataToReturn;
    }

    /**
     * Return generated tokens
     * @return array{
     *     access?: string,
     *     refresh?: string,
     * }
     */
    public function tokens(): array
    {
        return $this->tokens;
    }

    /**
     * Generate a new JWT for the user
     * @param int $tokenLifetime
     * @return string
     */
    public function generateToken($tokenLifetime): string
    {
        $userIdKey = Config::get('id.key');
        $secretPhrase = Config::get('token.secret');

        $payload = [
            'user.id' => $this->data[$userIdKey],
            'iat' => time(),
            'exp' => $tokenLifetime,
            'iss' => $_SERVER['HTTP_HOST'] ?? 'localhost',
        ];

        return JWT::encode($payload, $secretPhrase, 'HS256');
    }

    /**
     * Generate a verification token for the user
     * @param mixed $expiresIn Token expiration time
     * @param string|null $purpose Purpose of the token
     * @return string
     */
    public function generateVerificationToken($expiresIn = null, ?string $purpose = null): string
    {
        $userIdKey = Config::get('id.key');
        $secretPhrase = Config::get('token.secret') . '-verification';

        $payload = [
            'user.id' => $this->data[$userIdKey],
            'user.email' => $this->data['email'],
            'iat' => time(),
            'exp' => $expiresIn ?? (time() + 600),
            'iss' => $_SERVER['HTTP_HOST'] ?? 'localhost',
        ];

        if ($purpose) {
            $payload['token.purpose'] = $purpose;
        }

        return JWT::encode($payload, $secretPhrase, 'HS256');
    }

    /**
     * Check if email is verified
     * @return bool
     */
    public function isVerified(): bool
    {
        return !!($this->data['email_verified_at'] ?? false);
    }

    /**
     * Verify user's email
     * @return bool
     */
    public function verifyEmail(): bool
    {
        if ($this->isVerified()) {
            return true;
        }

        if (!array_key_exists('email_verified_at', $this->data)) {
            $this->db->query('ALTER TABLE ' . Config::get('db.table') . ' ADD COLUMN email_verified_at TIMESTAMP NULL DEFAULT NULL')->execute();
        }

        $this->data['email_verified_at'] = tick()->format(Config::get('timestamps.format'));

        try {
            $this->db->update(Config::get('db.table'))
                ->params(['email_verified_at' => $this->data['email_verified_at']])
                ->where(Config::get('id.key'), $this->data[Config::get('id.key')])
                ->execute();

            return true;
        } catch (\Throwable $th) {
            return false;
        }
    }

    /** @return array<string, mixed> */
    public function get()
    {
        $userData = $this->data;

        $idKey = Config::get('id.key');
        $hidden = array_merge(Config::get('hidden'), [Config::get('roles.key')]);
        $passwordKey = Config::get('password.key');

        if (count($hidden) > 0) {
            foreach ($hidden as $item) {
                if (isset($userData[$item])) {
                    unset($userData[$item]);
                }

                if ($item === 'field.id' && isset($userData[$idKey])) {
                    unset($userData[$idKey]);
                }

                if ($item === 'field.password' && isset($userData[$passwordKey])) {
                    unset($userData[$passwordKey]);
                }
            }
        }

        return $userData;
    }

    /**
     * Set user db instance
     * @param \Leaf\Db $db
     * @return User
     */
    public function setDb($db)
    {
        $this->db = $db;

        return $this;
    }

    /**
     * Get user errors
     * @return array
     */
    public function errors()
    {
        return $this->errorsArray;
    }

    public function __toString()
    {
        return json_encode($this->get()) ?: '';
    }

    /**
     * @param string $name
     * @return ?mixed
     */
    public function __get($name)
    {
        // using data instead of get() here because
        // we want people to be able to user()->get hidden fields
        // since it's expected to be used within the app
        return $this->data[$name] ?? null;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    /**
     * @param string $name
     * @return void
     */
    public function __unset($name)
    {
        unset($this->data[$name]);
    }

    /**
     * Get a "user to many" table relation
     *
     * <code>
     * auth()->user()->orders()->all();
     * auth()->user()->transactions()->where('amount', '>', 100)->get();
     * auth()->user()->notes()->where('title', 'like', '%important%')->get();
     * auth()->user()->posts()->where('published', true)->all();
     * </code>
     *
     * @param mixed $method The table to relate to
     * @param mixed $args
     * @throws \Exception
     *
     * @return Model
     */
    public function __call($method, $args)
    {
        if (!class_exists('Leaf\App')) {
            throw new \Exception('Relations are only available in Leaf apps.');
        }

        return (new Model([
            'user' => $this,
            'table' => $method,
            'db' => auth()->db(),
        ]));
    }
}
