<?php

namespace Leaf\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

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
        $accessTokenLifetime = Config::get('session')
            ? Config::get('session.lifetime')
            : Config::get('token.lifetime');

        $this->data = $data;

        $this->tokens['access'] = $this->generateToken($accessTokenLifetime);
        $this->tokens['refresh'] = $this->generateToken($accessTokenLifetime + 259200);
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

        $dataToReturn = (object) [
            'user' => $userData,
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
        return $this->data;
    }

    public function __toString()
    {
        return json_encode($this->data);
    }

    public function __get($name)
    {
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
}
