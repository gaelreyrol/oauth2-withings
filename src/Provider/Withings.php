<?php

namespace WayToHealth\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\GenericResourceOwner;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

class Withings extends AbstractProvider
{
    use BearerAuthorizationTrait;

    /**
     * Withings URL.
     */
    public const string BASE_WITHINGS_URL = 'https://account.withings.com';

    /**
     * Withings API URL
     */
    public const string BASE_WITHINGS_API_URL = 'https://wbsapi.withings.net';

    /**
     * HTTP header Accept-Language.
     */
    public const string HEADER_ACCEPT_LANG = 'Accept-Language';

    /**
     * HTTP header Accept-Locale.
     */
    public const string HEADER_ACCEPT_LOCALE = 'Accept-Locale';

    /**
     * @var string Key used in a token response to identify the resource owner.
     */
    public const string ACCESS_TOKEN_RESOURCE_OWNER_ID = 'userid';

    /**
     * Get authorization url to begin OAuth flow.
     */
    public function getBaseAuthorizationUrl(): string
    {
        return static::BASE_WITHINGS_URL . '/oauth2_user/authorize2';
    }

    /**
     * Get access token url to retrieve token.
     *
     * @param array<string, mixed> $params
     */
    public function getBaseAccessTokenUrl(array $params): string
    {
        return static::BASE_WITHINGS_API_URL . '/v2/oauth2';
    }

    /**
     * Requests an access token using a specified grant and option set.
     *
     * @param array<string, mixed> $options
     *
     * @throws IdentityProviderException
     */
    public function getAccessToken(mixed $grant, array $options = []): AccessTokenInterface
    {
        // withings requires the action to be 'requesttoken' when getting an access token
        if (empty($options['action'])) {
            $options['action'] = 'requesttoken';
        }

        return parent::getAccessToken($grant, $options);
    }

    /**
     * Returns the url to retrieve the resource owners's profile/details.
     */
    public function getResourceOwnerDetailsUrl(AccessToken $token): string
    {
        return static::BASE_WITHINGS_API_URL . '/v2/user?action=getdevice&access_token=' . $token->getToken();
    }

    /**
     * Returns all scopes available from Withings.
     * It is recommended you only request the scopes you need!
     *
     * @return array<string>
     */
    protected function getDefaultScopes(): array
    {
        return ['user.info', 'user.metrics', 'user.activity'];
    }

    /**
     * Checks Withings API response for errors.
     *
     * @throws IdentityProviderException
     *
     * @param array<string, mixed>|string $data     Parsed response data
     */
    protected function checkResponse(ResponseInterface $response, mixed $data): void
    {
        if (array_key_exists('error', $data)) {
            $errorMessage = $data['error'];
            $errorCode = array_key_exists('status', $data) ?
                $data['status'] : $response->getStatusCode();
            throw new IdentityProviderException(
                $errorMessage,
                $errorCode,
                $data
            );
        }
    }

    /**
     * Prepares an parsed access token response for a grant.
     *
     * Custom mapping of expiration, etc should be done here. Always call the
     * parent method when overloading this method.
     *
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     *
     * @throws IdentityProviderException
     */
    protected function prepareAccessTokenResponse(array $result): array
    {
        if (!array_key_exists('status', $result)) {
            throw new IdentityProviderException(
                'Invalid response received from Authorization Server. Missing status.',
                0,
                $result
            );
        }

        if ($result['status'] !== 0) {
            throw new IdentityProviderException(
                sprintf('Invalid response received from Authorization Server. Status code %d.', $result['status']),
                0,
                $result
            );
        }

        if (!array_key_exists('body', $result)) {
            throw new IdentityProviderException(
                'Invalid response received from Authorization Server. Missing body.',
                0,
                $result
            );
        }

        return parent::prepareAccessTokenResponse($result['body']);
    }

    /**
     * Returns authorization parameters based on provided options.
     * Withings does not use the 'approval_prompt' param and here we remove it.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed> Authorization parameters
     */
    protected function getAuthorizationParameters(array $options): array
    {
        $params = parent::getAuthorizationParameters($options);
        unset($params['approval_prompt']);
        if (!empty($options['prompt'])) {
            $params['prompt'] = $options['prompt'];
        }

        return $params;
    }

    /**
     * Generates a resource owner object from a successful resource owner
     * details request.
     *
     * @param array<string, mixed> $response
     */
    public function createResourceOwner(array $response, AccessToken $token): GenericResourceOwner
    {
        return new GenericResourceOwner($response, self::ACCESS_TOKEN_RESOURCE_OWNER_ID);
    }

    /**
     * Revoke access for the given token.
     */
    public function revoke(AccessToken $accessToken): ResponseInterface
    {
        $options = $this->optionProvider->getAccessTokenOptions($this->getAccessTokenMethod(), []);
        $uri = $this->appendQuery(
            self::BASE_WITHINGS_API_URL . '/notify?action=revoke',
            $this->buildQueryString(['token' => $accessToken->getToken()])
        );
        $request = $this->getRequest(self::METHOD_POST, $uri, $options);

        return $this->getResponse($request);
    }

    /**
     * @return string|array<string, mixed>
     */
    public function parseResponse(ResponseInterface $response): string|array
    {
        return parent::parseResponse($response);
    }
}
