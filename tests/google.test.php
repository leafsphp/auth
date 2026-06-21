<?php

declare(strict_types=1);

namespace Tests {

    use Pest\Expectation;

    /**
     * Creates a new expectation.
     *
     * @param mixed $value the Value
     *
     * @return Expectation
     */
    function expect($value = null): Expectation
    {
        return \expect($value);
    }
}

namespace {

    use Leaf\Auth;
    use League\OAuth2\Client\Provider\Google;

    use function Tests\expect;

    test('initializes google withou leafs/leaf', function (): void {
        $_ENV = [
            'GOOGLE_AUTH_CLIENT_ID' => '{google-auth-client-id}',
            'GOOGLE_AUTH_CLIENT_SECRET' => '{google-auth-client-secret}',
            'GOOGLE_AUTH_REDIRECT_URI' => '{google-auth-redirect-uri}',
            'APP_URL' => 'http://localhost',
        ];

        $auth = new Auth();
        $google = $auth->client('google');
        assert($google instanceof Google);

        $reflectionClass = new ReflectionClass(Google::class);
        $clientId = $reflectionClass->getProperty('clientId');
        $clientSecret = $reflectionClass->getProperty('clientSecret');
        $redirectUri = $reflectionClass->getProperty('redirectUri');

        $clientId->setAccessible(true);
        $clientSecret->setAccessible(true);
        $redirectUri->setAccessible(true);

        expect($clientId->getValue($google))
            ->toBe($_ENV['GOOGLE_AUTH_CLIENT_ID']);

        expect($clientSecret->getValue($google))
            ->toBe($_ENV['GOOGLE_AUTH_CLIENT_SECRET']);

        expect($redirectUri->getValue($google))
            ->toBe($_ENV['GOOGLE_AUTH_REDIRECT_URI']);
    });

    test('use APP_URL in __construct', function (): void {
        $_ENV = [
            'GOOGLE_AUTH_CLIENT_ID' => '{google-auth-client-id}',
            'GOOGLE_AUTH_CLIENT_SECRET' => '{google-auth-client-secret}',
            'APP_URL' => 'http://localhost',
        ];

        $auth = new Auth();
        $google = $auth->client('google');
        assert($google instanceof Google);

        $reflectionClass = new ReflectionClass(Google::class);
        $redirectUri = $reflectionClass->getProperty('redirectUri');

        $redirectUri->setAccessible(true);

        expect($redirectUri->getValue($google))
            ->toBe("{$_ENV['APP_URL']}/auth/register/google");
    });

    test('use APP_URL in withGoogle without redirectUri', function (): void {
        $_ENV = [
            'APP_URL' => 'http://localhost',
        ];

        $auth = new Auth();
        $google = $auth->withGoogle('', '')->client('google');
        assert($google instanceof Google);

        $reflectionClass = new ReflectionClass(Google::class);
        $redirectUri = $reflectionClass->getProperty('redirectUri');

        $redirectUri->setAccessible(true);

        expect($redirectUri->getValue($google))
            ->toBe("{$_ENV['APP_URL']}/auth/google/callback");
    });
}
