<?php

declare(strict_types=1);

namespace Core\Plug\Content\Devto;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Postable;
use Core\Plug\Response;
use Illuminate\Support\Collection;

/**
 * Dev.to article publishing.
 */
class Post implements Postable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://dev.to/api';

    /**
     * Publish an article.
     *
     * @param  string  $text  Markdown content (body_markdown)
     * @param  Collection  $media  Not used - Dev.to handles images via URLs
     * @param  array  $params  title (required), tags, canonical_url, series, published, main_image, description
     */
    public function publish(string $text, Collection $media, array $params = []): Response
    {
        $title = $params['title'] ?? null;

        if (! $title) {
            return $this->error('Title is required');
        }

        $articleData = [
            'article' => array_filter([
                'title' => $title,
                'body_markdown' => $text,
                'published' => $params['published'] ?? false,
                'tags' => isset($params['tags']) ? array_slice((array) $params['tags'], 0, 4) : null, // Max 4 tags
                'series' => $params['series'] ?? null,
                'canonical_url' => $params['canonical_url'] ?? null,
                'main_image' => $params['main_image'] ?? ($media->first()['url'] ?? null),
                'description' => $params['description'] ?? null,
                'organization_id' => $params['organization_id'] ?? null,
            ]),
        ];

        $response = $this->http()
            ->withHeaders(['api-key' => $this->accessToken()])
            ->post(self::API_URL.'/articles', $articleData);

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['id'],
            'title' => $data['title'],
            'url' => $data['url'],
            'slug' => $data['slug'],
            'published' => $data['published'] ?? false,
            'published_at' => $data['published_at'] ?? null,
        ]);
    }

    /**
     * Update an existing article.
     */
    public function update(int $articleId, array $params): Response
    {
        $articleData = [
            'article' => array_filter([
                'title' => $params['title'] ?? null,
                'body_markdown' => $params['body_markdown'] ?? $params['content'] ?? null,
                'published' => $params['published'] ?? null,
                'tags' => isset($params['tags']) ? array_slice((array) $params['tags'], 0, 4) : null,
                'series' => $params['series'] ?? null,
                'canonical_url' => $params['canonical_url'] ?? null,
                'main_image' => $params['main_image'] ?? null,
            ]),
        ];

        $response = $this->http()
            ->withHeaders(['api-key' => $this->accessToken()])
            ->put(self::API_URL."/articles/{$articleId}", $articleData);

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['id'],
            'title' => $data['title'],
            'url' => $data['url'],
            'published' => $data['published'] ?? false,
        ]);
    }

    /**
     * Get external URL to an article.
     */
    public static function externalPostUrl(string $username, string $slug): string
    {
        return "https://dev.to/{$username}/{$slug}";
    }

    /**
     * Get external URL to a profile.
     */
    public static function externalAccountUrl(string $username): string
    {
        return "https://dev.to/{$username}";
    }
}
