<?php

declare(strict_types=1);

namespace Core\Plug\Content\Medium;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Authenticable;
use Core\Plug\Response;

/**
 * Medium OAuth 2.0 authentication.
 */
class Auth implements Authenticable
{
    use BuildsResponse;
    use UsesHttp;

    private const AUTH_URL = 'https://medium.com/m/oauth/authorize';

    private const TOKEN_URL = 'https://api.medium.com/v1/tokens';

    private string $clientId;

    private string $clientSecret;

    private string $redirectUri;

    public function __construct(
        string $clientId = '',
        string $clientSecret = '',
        string $redirectUri = ''
    ) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUri = $redirectUri;
    }

    public static function identifier(): string
    {
        return 'medium';
    }

    public static function name(): string
    {
        return 'Medium';
    }

    public function getAuthUrl(): string
    {
        $state = bin2hex(random_bytes(16));

        $params = http_build_query([
            'client_id' => $this->clientId,
            'scope' => 'basicProfile,publishPost,listPublications',
            'state' => $state,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
        ]);

        return self::AUTH_URL.'?'.$params;
    }

    public function requestAccessToken(array $params): array
    {
        $code = $params['code'] ?? null;

        if (! $code) {
            return ['error' => 'Authorization code is required'];
        }

        $response = $this->http()->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri,
        ]);

        if (! $response->successful()) {
            return [
                'error' => $response->json('error') ?? 'Token exchange failed',
            ];
        }

        $data = $response->json();

        return [
            'access_token' => $data['access_token'],
            'token_type' => $data['token_type'] ?? 'Bearer',
            'refresh_token' => $data['refresh_token'] ?? null,
            'scope' => $data['scope'] ?? [],
            'expires_at' => $data['expires_at'] ?? null,
        ];
    }

    /**
     * Refresh access token.
     */
    public function refreshToken(string $refreshToken): array
    {
        $response = $this->http()->post(self::TOKEN_URL, [
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            return [
                'error' => $response->json('error') ?? 'Refresh failed',
            ];
        }

        return $response->json();
    }

    public function getAccount(): Response
    {
        return $this->error('Use Read::me() with access token');
    }
}
