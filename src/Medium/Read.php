<?php

declare(strict_types=1);

namespace Core\Plug\Content\Medium;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Readable;
use Core\Plug\Response;

/**
 * Medium profile and publication reading.
 */
class Read implements Readable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://api.medium.com/v1';

    /**
     * Get a post by ID.
     *
     * Note: Medium API doesn't provide a direct post retrieval endpoint.
     */
    public function get(string $id): Response
    {
        return $this->error('Medium API does not support retrieving individual posts');
    }

    /**
     * Get the authenticated user's profile.
     */
    public function me(): Response
    {
        $response = $this->http()
            ->withToken($this->accessToken())
            ->get(self::API_URL.'/me');

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['data']['id'],
            'username' => $data['data']['username'],
            'name' => $data['data']['name'],
            'image' => $data['data']['imageUrl'] ?? null,
            'url' => $data['data']['url'],
        ]);
    }

    /**
     * List not supported for Medium (no feed API).
     */
    public function list(array $params = []): Response
    {
        return $this->error('Medium API does not support listing posts');
    }
}
