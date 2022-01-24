<!-- markdownlint-disable no-inline-html -->
<p align="center">
  <br><br>
  <img src="https://leafphp.netlify.app/assets/img/leaf3-logo.png" height="100"/>
  <h1 align="center">Leaf Auth v2</h1>
  <br><br>
</p>

[![Latest Stable Version](https://poser.pugx.org/leafs/auth/v/stable)](https://packagist.org/packages/leafs/auth)
[![Total Downloads](https://poser.pugx.org/leafs/auth/downloads)](https://packagist.org/packages/leafs/auth)
[![License](https://poser.pugx.org/leafs/auth/license)](https://packagist.org/packages/leafs/auth)

Leaf auth is a simple but powerful module which comes with powerful functions for handling all your authentication needs.

v2 comes with tons of fixes, improvements and upgrades. Running on top of leaf db v2, it also has support for other database types like PostgreSQL, Sqlite and many more.

## Installation

You can easily install Leaf using [Composer](https://getcomposer.org/).

```bash
composer require leafs/auth
```

Or with leaf db

```sh
leaf install auth
```

## Basic Usage

After installing leaf auth, you need to connect to your database. v2 presents additional ways to achieve this.

### connect

The connect method allows you to pass in your database connection parameters directly to leaf auth.

```php
auth()->connect('127.0.0.1', 'dbname', 'username', 'password');
```

### autoConnect

This method creates a database connection using your env variables.

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=LEAF_DB_NAME
DB_USERNAME=LEAF_DB_USERNAME
DB_PASSWORD=
```

And call `autoConnect` in your app.

```php
auth()->autoConnect();
```

### db connection (v2 only)

Leaf auth now allows you to directly pass a PDO connection into leaf auth. This allows you to share your connection with leaf auth and avoid multiple connections.

```php
$auth = new Leaf\Auth;
$auth->dbConnection($pdoConnecction);
```

This also means that you can share you leaf db v2 connection with leaf auth like this:

```php
$auth = new Leaf\Auth;
$auth->dbConnection($db->connection());
```

### Leaf db (auth v2 + leaf 3 only)

If you are using leaf auth in a leaf 3 app, you will have access to the `auth` global as shown in some of the above connections. Along with this, if you already have a leaf db connection, you no longer need to explicitly connect to your database. Leaf auth searches for a leaf db instance and connects to it automatically.

**Note that this only works in a leaf 3 app and only if you already have a leaf db connection.**

```php
<?php

db()->connect('127.0.0.1', 'dbname', 'username', 'password');

// you can use auth straight away without any connect
auth()->login(...);
```

## 📚 Auth methods

After connecting your db, you can use any of the methods below.

WIP: This page will be updated

## ⚡️ Funtional Mode

When using leaf auth in a leaf 3 app, you will have access to the `auth`, `guard`, `hasAuth` and `sessionUser` globals.

## 💬 Stay In Touch

- [Twitter](https://twitter.com/leafphp)
- [Join the forum](https://github.com/leafsphp/leaf/discussions/37)
- [Chat on discord](https://discord.com/invite/Pkrm9NJPE3)

## 📓 Learning Leaf 3

- Leaf has a very easy to understand [documentation](https://leafphp.dev) which contains information on all operations in Leaf.
- You can also check out our [youtube channel](https://www.youtube.com/channel/UCllE-GsYy10RkxBUK0HIffw) which has video tutorials on different topics
- We are also working on codelabs which will bring hands-on tutorials you can follow and contribute to.

## 😇 Contributing

We are glad to have you. All contributions are welcome! To get started, familiarize yourself with our [contribution guide](https://leafphp.dev/community/contributing.html) and you'll be ready to make your first pull request 🚀.

To report a security vulnerability, you can reach out to [@mychidarko](https://twitter.com/mychidarko) or [@leafphp](https://twitter.com/leafphp) on twitter. We will coordinate the fix and eventually commit the solution in this project.

### Code contributors

<table>
	<tr>
		<td align="center">
			<a href="https://github.com/mychidarko">
				<img src="https://avatars.githubusercontent.com/u/26604242?v=4" width="120px" alt=""/>
				<br />
				<sub>
					<b>Michael Darko</b>
				</sub>
			</a>
		</td>
	</tr>
</table>

## 🤩 Sponsoring Leaf

Your cash contributions go a long way to help us make Leaf even better for you. You can sponsor Leaf and any of our packages on [open collective](https://opencollective.com/leaf) or check the [contribution page](https://leafphp.dev/support/) for a list of ways to contribute.

And to all our existing cash/code contributors, we love you all ❤️

### Cash contributors

<table>
	<tr>
		<td align="center">
			<a href="https://opencollective.com/aaron-smith3">
				<img src="https://images.opencollective.com/aaron-smith3/08ee620/avatar/256.png" width="120px" alt=""/>
				<br />
				<sub><b>Aaron Smith</b></sub>
			</a>
		</td>
		<td align="center">
			<a href="https://opencollective.com/peter-bogner">
				<img src="https://images.opencollective.com/peter-bogner/avatar/256.png" width="120px" alt=""/>
				<br />
				<sub><b>Peter Bogner</b></sub>
			</a>
		</td>
		<td align="center">
			<a href="#">
				<img src="https://images.opencollective.com/guest-32634fda/avatar.png" width="120px" alt=""/>
				<br />
				<sub><b>Vano</b></sub>
			</a>
		</td>
		<td align="center">
			<a href="#">
				<img src="https://images.opencollective.com/guest-c72a498e/avatar/256.png" width="120px" alt=""/>
				<br />
				<sub><b>Casprine</b></sub>
			</a>
		</td>
	</tr>
</table>

## 🤯 Links/Projects

- [Leaf Docs](https://leafphp.dev)
- [Skeleton Docs](https://skeleton.leafphp.dev)
- [Leaf CLI Docs](https://cli.leafphp.dev)
- [Aloe CLI Docs](https://leafphp.dev/aloe-cli/)
