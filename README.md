# Withings Provider for OAuth 2.0 Client

[![CI](https://github.com/waytohealth/oauth2-withings/actions/workflows/ci.yml/badge.svg)](https://github.com/waytohealth/oauth2-withings/actions/workflows/ci.yml)

This package provides Withings OAuth 2.0 support for the PHP League's [OAuth 2.0 Client](https://github.com/thephpleague/oauth2-client).

This package is compliant with [PSR-1][], [PSR-12][], [PSR-4][], and [PSR-7][]. If you notice compliance oversights, please send a patch via pull request.

## Requirements

The following versions of PHP are supported.

* PHP 8.3

## Installation

To install, use composer:

```
composer require waytohealth/oauth2-withings
```

## Usage

### Authorization Code Grant

```php
use WayToHealth\OAuth2\Client\Provider\Withings;

$provider = new Withings([
    'clientId'          => '{withings-oauth2-client-id}',
    'clientSecret'      => '{withings-client-secret}',
    'redirectUri'       => 'https://example.com/callback-url'
]);

// Fetch the authorization URL from the provider; this returns the
// urlAuthorize option and generates and applies any necessary parameters
// (e.g. state).
$authorizationUrl = $provider->getAuthorizationUrl($options);

// Try to get an access token using the authorization code grant.
$accessToken = $provider->getAccessToken('authorization_code', $options);

// Add subscription
$subscriptionUrl = sprintf('https://wbsapi.withings.net/notify?action=subscribe&access_token=%s&callbackurl=%s&appli=%s&comment=Way_To_Health',
    $accessToken,
    $params['callbackurl'],
    $params['appli']
);
$subscriptionRequest = $provider->getAuthenticatedRequest('GET', $subscriptionUrl, $accessToken, $options);
$provider->getParsedResponse($request);

// Get data
$request = $provider->getAuthenticatedRequest('GET', $url, $accessToken, $options);
$data = $provider->getParsedResponse($request);
```

### Refreshing a Token

Once your application is authorized, you can refresh an expired token using a refresh token rather than going through the entire process of obtaining a brand new token. To do so, simply reuse this refresh token from your data store to request a refresh.

```php
$provider = new WayToHealth\OAuth2\Client\Provider\Withings([
    'clientId'          => '{withings-oauth2-client-id}',
    'clientSecret'      => '{withings-client-secret}',
    'redirectUri'       => 'https://example.com/callback-url'
]);

$existingAccessToken = getAccessTokenFromYourDataStore();

if ($existingAccessToken->hasExpired()) {
    $newAccessToken = $provider->getAccessToken('refresh_token', [
        'refresh_token' => $existingAccessToken->getRefreshToken()
    ]);

    // Purge old access token and store new access token to your data store.
}
```

## Testing

``` bash
$ ./vendor/bin/phpunit
```

## Contributing

Please see [CONTRIBUTING](https://github.com/waytohealth/oauth2-withings/blob/master/CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](https://github.com/waytohealth/oauth2-withings/blob/master/LICENSE) for more information.

[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-4]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md
[PSR-7]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message.md
[PSR-12]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-12-extended-coding-style-guide.md
