<?php

namespace Leaf\Auth;

use Firebase\JWT\JWT;
use Leaf\Http\Session;
use Leaf\Db;

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

    /**
     * User Roles
     */
    protected array $roles = [];

    public function __construct($data)
    {
        $this->data = $data;

        $sessionLifetime = Config::get('token.lifetime');

        if (Config::get('session')) {
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
                Session::set(
                    'auth.ttl',
                    is_int($sessionLifetime)
                    ? time() + $sessionLifetime
                    : strtotime($sessionLifetime) ?? throw new \Exception('Invalid session lifetime')
                );
            }
        }

        $this->tokens['access'] = $this->generateToken($sessionLifetime);
        $this->tokens['refresh'] = $this->generateToken($sessionLifetime + 259200);
    }

    /**
     * Return the id of current user
     * @return string|int
     */
    public function id()
    {
        return $this->data['id'] ?? null;
    }

    /**
     * Get auth information to be sent to the client
     * @return object
     */
    public function getAuthInfo(): object
    {
        $dataToReturn = (object) [
            'user' => $this->get(),
            'accessToken' => $this->tokens['access'],
            'refreshToken' => $this->tokens['refresh'],
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

        // no fallback because we need the user id
        $userId = $this->data[$userIdKey];

        $payload = [
            'user.id' => $userId,
            'iat' => time(),
            'exp' => $tokenLifetime,
            'iss' => $_SERVER['HTTP_HOST'] ?? 'localhost',
        ];

        $token = JWT::encode($payload, $secretPhrase, 'HS256');

        return $token;
    }

    public function get()
    {
        $userData = $this->data;

        $idKey = Config::get('id.key');
        $hidden = Config::get('hidden');
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
     * @return Db
     */
    public function __call($method, $args)
    {
        if (!class_exists('Leaf\App')) {
            throw new \Exception('Relations are only available in Leaf apps.');
        }

        return auth()
            ->db()
            ->select($method)
            ->where('user_id', $this->id());
    }
}
