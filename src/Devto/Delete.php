<?php

declare(strict_types=1);

namespace Core\Plug\Content\Devto;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Deletable;
use Core\Plug\Response;

/**
 * Dev.to article deletion (unpublishing).
 *
 * Note: Dev.to API doesn't support full deletion, only unpublishing.
 */
class Delete implements Deletable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://dev.to/api';

    /**
     * Unpublish an article (Dev.to doesn't support full deletion via API).
     */
    public function delete(string $id): Response
    {
        // Dev.to API doesn't have a DELETE endpoint
        // We unpublish instead
        $response = $this->http()
            ->withHeaders(['api-key' => $this->accessToken()])
            ->put(self::API_URL."/articles/{$id}", [
                'article' => [
                    'published' => false,
                ],
            ]);

        return $this->fromHttp($response, fn () => [
            'unpublished' => true,
            'note' => 'Article unpublished (Dev.to API does not support deletion)',
        ]);
    }
}
