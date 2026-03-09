<?php

declare(strict_types=1);

namespace Core\Plug\Content\Devto;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Authenticable;
use Core\Plug\Response;

/**
 * Dev.to API key authentication.
 *
 * Uses API key (no OAuth).
 */
class Auth implements Authenticable
{
    use BuildsResponse;
    use UsesHttp;

    private const API_URL = 'https://dev.to/api';

    private string $apiKey;

    public function __construct(string $apiKey = '')
    {
        $this->apiKey = $apiKey;
    }

    public static function identifier(): string
    {
        return 'devto';
    }

    public static function name(): string
    {
        return 'DEV Community';
    }

    /**
     * API key generation URL.
     */
    public function getAuthUrl(): string
    {
        return 'https://dev.to/settings/extensions';
    }

    /**
     * Validate API key.
     *
     * @param  array  $params  ['api_key' => string]
     */
    public function requestAccessToken(array $params): array
    {
        $apiKey = $params['api_key'] ?? $this->apiKey;

        if (! $apiKey) {
            return ['error' => 'API key is required'];
        }

        // Validate by fetching user info
        $response = $this->http()
            ->withHeaders(['api-key' => $apiKey])
            ->get(self::API_URL.'/users/me');

        if (! $response->successful()) {
            return ['error' => 'Invalid API key'];
        }

        $user = $response->json();

        return [
            'api_key' => $apiKey,
            'user_id' => $user['id'],
            'username' => $user['username'],
        ];
    }

    public function getAccount(): Response
    {
        return $this->error('Use Read::me() with API key');
    }
}
