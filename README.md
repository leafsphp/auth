<!-- markdownlint-disable no-inline-html -->
<p align="center">
  <br><br>
  <img src="https://leafphp.dev/logo-circle.png" height="100"/>
  <h1 align="center">Leaf Auth</h1>
  <br><br>
</p>

[![Latest Stable Version](https://poser.pugx.org/leafs/auth/v/stable)](https://packagist.org/packages/leafs/auth)
[![Total Downloads](https://poser.pugx.org/leafs/auth/downloads)](https://packagist.org/packages/leafs/auth)
[![License](https://poser.pugx.org/leafs/auth/license)](https://packagist.org/packages/leafs/auth)

Leaf provides a lightweight but very powerful authentication system to handle all the complexities of authentication in a few lines of code. We understand that authentication is a critical part of your application, so we've made it as simple and secure as possible.

## Installation

You can easily install Leaf Auth using the Leaf CLI:

```bash
leaf install auth
```

Or via composer:

```sh
composer require leafs/auth
```

## Connecting to a database

To do any kind of authentication, you need to connect to some kind of database which will store your users' data.

```php
auth()->connect([
  'dbtype' => '...',
  'charset' => '...',
  'port' => '...',
  'host' => '...',
  'dbname' => '...',
  'user' => '...',
  'password' => '...'
]);
```

If you have an existing PDO connection, you can pass it to Leaf Auth:

```php
$db = new PDO('mysql:dbname=test;host=127.0.0.1', 'root', '');

auth()->dbConnection($db);

// you can use leaf auth the same way you always have
```

## Signing a user in

To sign a user in, you can use the login() method. This method takes in an array of data you want to use to authenticate the user. This data is usually the user's email and password, but can be anything as long as the password field is present.

```php
auth()->login([
  'email' => 'm@example.com',
  'password' => 'password'
]);
```

## Signing a user up

To sign a user up is to create a new user account on your application. This is usually done by collecting the user's details and storing them in your database. You also need to validate the user's details to ensure they are correct and that they don't conflict with existing data.

Leaf allows you to do all this using the register() method. This method takes in an array of data you want to use to create the user.

```php
auth()->register([
  'username' => 'example',
  'email' => 'm@example.com',
  'password' => 'password'
]);
```

## Using Middleware

Leaf Auth also provides a middleware that you can use to protect your routes. The auth middleware checks if a user is logged in and allows you to set a callback function to run if a user is not logged in.

```php
auth()->middleware('auth.required', function () {
  response()->redirect('/login');
});
```

Once you have defined a callback for the middleware, you can use it in your routes like this:

```php
app()->get('/protected', ['middleware' => 'auth.required', function () {
  // this route is protected
}]);

// or on a route group
app()->group('/protected', ['middleware' => 'auth.required', function () {
  app()->get('/route', function () {
    // this route is protected
  });
}]);
```

You can find the full documentation [here](https://leafphp.dev/docs/auth/protected-routes.html)

## Stay In Touch

- [Twitter](https://twitter.com/leafphp)
- [Join the forum](https://github.com/leafsphp/leaf/discussions/37)
- [Chat on discord](https://discord.com/invite/Pkrm9NJPE3)

## Learning Leaf PHP

- Leaf has a very easy to understand [documentation](https://leafphp.dev) which contains information on all operations in Leaf.
- You can also check out our [youtube channel](https://www.youtube.com/channel/UCllE-GsYy10RkxBUK0HIffw) which has video tutorials on different topics
- You can also learn from [codelabs](https://leafphp.dev/codelabs/) and contribute as well.

## Contributing

We are glad to have you. All contributions are welcome! To get started, familiarize yourself with our [contribution guide](https://leafphp.dev/community/contributing.html) and you'll be ready to make your first pull request 🚀.

To report a security vulnerability, you can reach out to [@mychidarko](https://twitter.com/mychidarko) or [@leafphp](https://twitter.com/leafphp) on twitter. We will coordinate the fix and eventually commit the solution in this project.

## Sponsoring Leaf

We are committed to keeping Leaf open-source and free, but maintaining and developing new features now requires significant time and resources. As the project has grown, so have the costs, which have been mostly covered by the team. To sustain and grow Leaf, we need your help to support full-time maintainers.

You can sponsor Leaf and any of our packages on [open collective](https://opencollective.com/leaf) or check the [contribution page](https://leafphp.dev/support/) for a list of ways to contribute.

And to all our [existing cash/code contributors](https://leafphp.dev#sponsors), we love you all ❤️
