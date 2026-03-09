<?php

declare(strict_types=1);

namespace Core\Plug\Content\Medium;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Listable;
use Core\Plug\Response;

/**
 * Medium publications listing.
 */
class Publications implements Listable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://api.medium.com/v1';

    private ?string $userId = null;

    /**
     * Set user ID for listing publications.
     */
    public function forUser(string $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * List publications the user can write to.
     */
    public function listEntities(): Response
    {
        if (! $this->userId) {
            // Get user ID first
            $meResponse = $this->http()
                ->withToken($this->accessToken())
                ->get(self::API_URL.'/me');

            if (! $meResponse->successful()) {
                return $this->fromHttp($meResponse);
            }

            $this->userId = $meResponse->json('data.id');
        }

        $response = $this->http()
            ->withToken($this->accessToken())
            ->get(self::API_URL."/users/{$this->userId}/publications");

        return $this->fromHttp($response, fn ($data) => [
            'publications' => array_map(fn ($pub) => [
                'id' => $pub['id'],
                'name' => $pub['name'],
                'description' => $pub['description'] ?? null,
                'url' => $pub['url'],
                'imageUrl' => $pub['imageUrl'] ?? null,
            ], $data['data'] ?? []),
        ]);
    }

    /**
     * Get contributors to a publication.
     */
    public function contributors(string $publicationId): Response
    {
        $response = $this->http()
            ->withToken($this->accessToken())
            ->get(self::API_URL."/publications/{$publicationId}/contributors");

        return $this->fromHttp($response, fn ($data) => [
            'contributors' => array_map(fn ($contributor) => [
                'userId' => $contributor['userId'],
                'publicationId' => $contributor['publicationId'],
                'role' => $contributor['role'],
            ], $data['data'] ?? []),
        ]);
    }
}
