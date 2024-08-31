<?php

namespace WayToHealth\Tests\Provider;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Utils;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use WayToHealth\OAuth2\Client\Provider\Withings;
use League\OAuth2\Client\Token\AccessToken;
use Mockery;

class WithingsTest extends TestCase
{
    private Withings $provider;
    private AccessToken $token;

    protected function setUp(): void
    {
        $this->provider = new Withings([
            'clientId' => 'mock_client_id',
            'clientSecret' => 'mock_secret',
            'redirectUri' => 'none',
        ]);

        $this->token = new AccessToken([
            'access_token' => 'mock_token',
            'expires_in' => 10000,
        ]);

    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    public function testAuthorizationUrl(): void
    {
        $url = $this->provider->getAuthorizationUrl(['prompt' => 'mock_prompt']);
        $uri = parse_url($url);
        parse_str($uri['query'], $query);

        $this->assertArrayHasKey('client_id', $query);
        $this->assertArrayHasKey('redirect_uri', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertArrayHasKey('scope', $query);
        $this->assertArrayHasKey('response_type', $query);
        $this->assertArrayHasKey('prompt', $query);
        $this->assertArrayNotHasKey('approval_prompt', $query);
        $this->assertNotNull($this->provider->getState());
    }

    public function testScopes(): void
    {
        $scopes = ['user.info', 'user.metrics', 'user.activity'];

        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);
        $this->assertStringContainsString(urlencode(implode(',', $scopes)), $url);
    }

    public function testGetAuthorizationUrl(): void
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);
        $this->assertEquals('/oauth2_user/authorize2', $uri['path']);
    }

    public function testGetBaseAccessTokenUrl(): void
    {
        $params = [];
        $url = $this->provider->getBaseAccessTokenUrl($params);
        $uri = parse_url($url);
        $this->assertEquals('/v2/oauth2', $uri['path']);
    }

    public function testGetResourceOwnerDetailsUrl(): void
    {
        $url = $this->provider->getResourceOwnerDetailsUrl($this->token);
        $uri = parse_url($url);
        $this->assertEquals('/v2/user', $uri['path']);
        $this->assertEquals('action=getdevice&access_token=mock_token', $uri['query']);
    }

    public function testGetAccessToken(): void
    {
        $response = Mockery::mock(ResponseInterface::class);
        $response->allows('getBody')
            ->andReturns(Utils::streamFor('{"status":0, "body":{"access_token":"mock_access_token", "token_type":"Bearer", "scope":"identify", "refresh_token":"mock_refresh_token", "user_id":"mock_user_id"}}'));
        $response->allows('getHeader')
            ->andReturns(['content-type' => 'json']);
        $response->allows('getStatusCode')
            ->andReturns(200);

        $client = Mockery::mock(ClientInterface::class);
        $client->expects('send')->once()->andReturns($response);

        $this->provider->setHttpClient($client);
        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);

        $this->assertEquals('mock_access_token', $token->getToken());
        $this->assertEquals('mock_refresh_token', $token->getRefreshToken());
        $this->assertNull($token->getExpires());
        $this->assertNull($token->getResourceOwnerId());
    }

    public function testParsedResponseSuccess(): void
    {
        // When we have a successful response, we return the parsed response
        $successResponse = <<<RESPONSE
            {
                "status": 0,
                "body": {
                    "appli": 0,
                    "callbackurl": "string",
                    "expires": "string",
                    "comment": "string"
                }
            }
        RESPONSE;

        $request = Mockery::mock(RequestInterface::class);

        $response = Mockery::mock(ResponseInterface::class);
        $response->allows('getBody')->andReturns(Utils::streamFor($successResponse));
        $response->allows('getHeader')->andReturns([]);

        $client = Mockery::mock(ClientInterface::class);
        $client->expects('send')->once()->andReturns($response);
        $this->provider->setHttpClient($client);

        $responseBody = $this->provider->getParsedResponse($request)['body'];
        $this->assertEquals($responseBody['expires'], "string");
    }

    public function testParsedResponseFailure(): void
    {
        // When the API responds with an error, we throw an exception
        // $this->expectException(\League\OAuth2\Client\Provider\Exception\IdentityProviderException::class);

        $request = Mockery::mock(RequestInterface::class);

        $response = Mockery::mock(ResponseInterface::class);
        $response->allows('getBody')->andReturns(Utils::streamFor('{"status":503,"error":"Invalid params"}'));
        $response->allows('getHeader')->andReturns([]);

        $client = Mockery::mock(ClientInterface::class);
        $client->expects('send')->once()->andReturns($response);
        $this->provider->setHttpClient($client);

        try {
            $this->provider->getParsedResponse($request);
            $this->fail('An exception should have been thrown');
        } catch (IdentityProviderException $e) {
            $this->assertEquals('Invalid params', $e->getMessage());
            $this->assertEquals(503, $e->getCode());
            // make sure response body is the parsed body
            $body = $e->getResponseBody();
            $this->assertTrue(is_array($body));
            $this->assertEquals(503, $body['status']);
            $this->assertEquals('Invalid params', $body['error']);
        }
    }

    public function testCreateResourceOwner(): void
    {
        $resourceOwner = $this->provider->createResourceOwner(
            ['userid' => 'value'],
            $this->token
        );

        $this->assertEquals('value', $resourceOwner->getId());
    }

    public function testRevoke(): void
    {
        $client = Mockery::spy(ClientInterface::class);
        $this->provider->setHttpClient($client);

        $client->expects('send')->once()->with(
            Mockery::on(function ($argument) {
                $uri = $argument->getUri();
                $path = $uri->getPath() === "/notify";
                $query =  $uri->getQuery() === "action=revoke&token=".$this->token->getToken();
                return $path && $query;
            })
        );

        $this->provider->revoke($this->token);

        $this->expectNotToPerformAssertions();
    }

}
