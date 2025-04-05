<?php

namespace Leaf\Auth;

use Firebase\JWT\JWT;
use Leaf\Http\Session;

/**
 * Auth User
 * ----
 * Class representing a user
 *
 * @since 3.0.0
 * @version 1.0.0
 */
class User
{
    use UsesRoles;
    use UsesSubscriptions;

    /**
     * Internal instance of Leaf database
     * @var \Leaf\DB
     */
    protected $db;

    /**
     * Internal instance of Leaf session
     * @var Session
     */
    protected $session;

    /**
     * User Information
     */
    protected array $data = [];

    /**
     * User Tokens
     */
    protected array $tokens = [];

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
            : (time() + $sessionLifetime);

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

        if (count($this->roles ?? [])) {
            $dataToReturn->roles = $this->roles;
        }

        if (count($this->permissions ?? [])) {
            $dataToReturn->permissions = $this->permissions;
        }

        return $dataToReturn;
    }

    /**
     * Return generated tokens
     * @return array
     */
    public function tokens(): array
    {
        return $this->tokens;
    }

    /**
     * Generate a new JWT for the user
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
     * @return string
     */
    public function generateVerificationToken($expiresIn = null): string
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

    public function __toString()
    {
        return json_encode($this->get());
    }

    public function __get($name)
    {
        // using data instead of get() here because
        // we want people to be able to user()->get hidden fields
        // since it's expected to be used within the app
        return $this->data[$name] ?? null;
    }

    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

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
