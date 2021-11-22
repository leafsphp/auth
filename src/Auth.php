<?php

namespace Leaf;

use Leaf\Helpers\Authentication;
use Leaf\Helpers\Password;

/**
 * Leaf Simple Auth
 * -------------------------
 * Authentication made easy.
 *
 * @author Michael Darko
 * @since 1.5.0
 * @version 2.0.0
 */
class Auth
{
	/**
	 * Create a db connection
	 *
	 * @param string $host The db host name
	 * @param string $host The db user
	 * @param string $host The db password
	 * @param string $host The db name
	 */
	public static function connect(string $host, string $user, string $password, string $dbname): void
	{
		Auth\Core::connect($host, $user, $password, $dbname);
	}

	/**
	 * Create a database connection from env variables
	 */
	public static function autoConnect(): void
	{
		Auth\Core::autoConnect();
	}

	/**
	 * Set auth config
	 */
	public static function config($config, $value = null)
	{
		return Auth\Core::config($config, $value);
	}

	/**
	 * Simple user login
	 *
	 * @param string table: Table to look for users
	 * @param array $credentials User credentials
	 * @param array $validate Validation for parameters
	 *
	 * @return array user: all user info + tokens + session data
	 */
	public static function login(string $table, array $credentials, array $validate = [])
	{
		return Auth\Login::user($table, $credentials, $validate);
	}

	/**
	 * Simple user registration
	 *
	 * @param string $table: Table to store user in
	 * @param array $credentials Information for new user
	 * @param array $uniques Parameters which should be unique
	 * @param array $validate Validation for parameters
	 *
	 * @return array user: all user info + tokens + session data
	 */
	public static function register(string $table, array $credentials, array $uniques = [], array $validate = [])
	{
		return Auth\Register::user($table, $credentials, $uniques, $validate);
	}

	/**
	 * Simple user update
	 *
	 * @param string $table: Table to store user in
	 * @param array $credentials New information for user
	 * @param array $where Information to find user by
	 * @param array $uniques Parameters which should be unique
	 * @param array $validate Validation for parameters
	 *
	 * @return array user: all user info + tokens + session data
	 */
	public static function update(string $table, array $credentials, array $where, array $uniques = [], array $validate = [])
	{
		return Auth\User::update($table, $credentials, $where, $uniques, $validate);
	}

	/**
	 * Validate Json Web Token
	 *
	 * @param string $token The token validate
	 * @param string $secretKey The secret key used to encode token
	 */
	public static function validate($token, $secretKey = null)
	{
		return Auth\User::validate($token, $secretKey);
	}

	/**
	 * Validate Bearer Token
	 *
	 * @param string $secretKey The secret key used to encode token
	 */
	public static function validateToken($secretKey = null)
	{
		return Auth\User::validateToken($secretKey);
	}

	/**
	 * Get Bearer token
	 */
	public static function getBearerToken()
	{
		return Auth\User::getBearerToken();
	}

	/**
	 * Get the current user data from token
	 *
	 * @param string $table The table to look for user
	 * @param array $hidden Fields to hide from user array
	 */
	public static function user($table = "users", $hidden = [])
	{
		return Auth\User::info($table, $hidden);
	}

	/**
	 * Return the user id encoded in token
	 */
	public static function id()
	{
		return Auth\User::id();
	}

	/**
	 * Return form field
	 */
	public static function get($param)
	{
		return Auth\Core::$form->get($param);
	}

	/**
	 * Get all authentication errors as associative array
	 */
	public static function errors(): array
	{
		return Auth\Core::errors();
	}
}
