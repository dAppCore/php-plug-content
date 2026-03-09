<?php

declare(strict_types=1);

namespace Core\Plug\Content\Hashnode;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Authenticable;
use Core\Plug\Response;

/**
 * Hashnode Personal Access Token authentication.
 *
 * Uses PAT (no OAuth).
 */
class Auth implements Authenticable
{
    use BuildsResponse;
    use UsesHttp;

    private const API_URL = 'https://gql.hashnode.com';

    private string $accessToken;

    public function __construct(string $accessToken = '')
    {
        $this->accessToken = $accessToken;
    }

    public static function identifier(): string
    {
        return 'hashnode';
    }

    public static function name(): string
    {
        return 'Hashnode';
    }

    /**
     * PAT generation URL.
     */
    public function getAuthUrl(): string
    {
        return 'https://hashnode.com/settings/developer';
    }

    /**
     * Validate access token.
     *
     * @param  array  $params  ['access_token' => string]
     */
    public function requestAccessToken(array $params): array
    {
        $token = $params['access_token'] ?? $this->accessToken;

        if (! $token) {
            return ['error' => 'Access token is required'];
        }

        // Validate by fetching user info
        $response = $this->http()
            ->withHeaders(['Authorization' => $token])
            ->post(self::API_URL, [
                'query' => 'query { me { id username name } }',
            ]);

        if (! $response->successful()) {
            return ['error' => 'Invalid access token'];
        }

        $data = $response->json('data.me');

        if (! $data) {
            return ['error' => 'Invalid access token'];
        }

        return [
            'access_token' => $token,
            'user_id' => $data['id'],
            'username' => $data['username'],
            'name' => $data['name'],
        ];
    }

    public function getAccount(): Response
    {
        return $this->error('Use Read::me() with access token');
    }
}
