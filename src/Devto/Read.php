<?php

declare(strict_types=1);

namespace Core\Plug\Content\Devto;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Readable;
use Core\Plug\Response;

/**
 * Dev.to article and profile reading.
 */
class Read implements Readable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://dev.to/api';

    /**
     * Get an article by ID.
     */
    public function get(string $id): Response
    {
        $response = $this->http()
            ->withHeaders(['api-key' => $this->accessToken()])
            ->get(self::API_URL."/articles/{$id}");

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'body_markdown' => $data['body_markdown'] ?? null,
            'body_html' => $data['body_html'] ?? null,
            'url' => $data['url'],
            'slug' => $data['slug'],
            'tags' => $data['tags'] ?? [],
            'published' => $data['published'] ?? false,
            'published_at' => $data['published_at'] ?? null,
            'reading_time' => $data['reading_time_minutes'] ?? null,
            'reactions_count' => $data['public_reactions_count'] ?? 0,
            'comments_count' => $data['comments_count'] ?? 0,
        ]);
    }

    /**
     * Get the authenticated user's profile.
     */
    public function me(): Response
    {
        $response = $this->http()
            ->withHeaders(['api-key' => $this->accessToken()])
            ->get(self::API_URL.'/users/me');

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['id'],
            'username' => $data['username'],
            'name' => $data['name'],
            'image' => $data['profile_image'],
            'bio' => $data['summary'] ?? null,
            'location' => $data['location'] ?? null,
            'website_url' => $data['website_url'] ?? null,
            'twitter_username' => $data['twitter_username'] ?? null,
            'github_username' => $data['github_username'] ?? null,
            'joined_at' => $data['joined_at'] ?? null,
        ]);
    }

    /**
     * List user's articles.
     */
    public function list(array $params = []): Response
    {
        $queryParams = [
            'page' => $params['page'] ?? 1,
            'per_page' => $params['per_page'] ?? 30,
        ];

        // Can filter by username, tag, state, etc.
        if (isset($params['username'])) {
            $queryParams['username'] = $params['username'];
        }

        if (isset($params['tag'])) {
            $queryParams['tag'] = $params['tag'];
        }

        if (isset($params['state'])) {
            $queryParams['state'] = $params['state']; // fresh, rising, all
        }

        $response = $this->http()
            ->withHeaders(['api-key' => $this->accessToken()])
            ->get(self::API_URL.'/articles', $queryParams);

        return $this->fromHttp($response, fn ($data) => [
            'articles' => array_map(fn ($article) => [
                'id' => $article['id'],
                'title' => $article['title'],
                'description' => $article['description'],
                'url' => $article['url'],
                'tags' => $article['tag_list'] ?? [],
                'published_at' => $article['published_at'] ?? null,
                'reading_time' => $article['reading_time_minutes'] ?? null,
                'reactions_count' => $article['public_reactions_count'] ?? 0,
                'comments_count' => $article['comments_count'] ?? 0,
            ], $data),
        ]);
    }

    /**
     * List user's published articles.
     */
    public function myArticles(array $params = []): Response
    {
        $queryParams = [
            'page' => $params['page'] ?? 1,
            'per_page' => $params['per_page'] ?? 30,
        ];

        $response = $this->http()
            ->withHeaders(['api-key' => $this->accessToken()])
            ->get(self::API_URL.'/articles/me', $queryParams);

        return $this->fromHttp($response, fn ($data) => [
            'articles' => array_map(fn ($article) => [
                'id' => $article['id'],
                'title' => $article['title'],
                'url' => $article['url'],
                'published' => $article['published'] ?? false,
                'published_at' => $article['published_at'] ?? null,
                'page_views_count' => $article['page_views_count'] ?? 0,
            ], $data),
        ]);
    }

    /**
     * List user's unpublished (draft) articles.
     */
    public function myDrafts(array $params = []): Response
    {
        $queryParams = [
            'page' => $params['page'] ?? 1,
            'per_page' => $params['per_page'] ?? 30,
        ];

        $response = $this->http()
            ->withHeaders(['api-key' => $this->accessToken()])
            ->get(self::API_URL.'/articles/me/unpublished', $queryParams);

        return $this->fromHttp($response, fn ($data) => [
            'articles' => array_map(fn ($article) => [
                'id' => $article['id'],
                'title' => $article['title'],
                'body_markdown' => $article['body_markdown'] ?? null,
            ], $data),
        ]);
    }
}
