<?php

declare(strict_types=1);

namespace Core\Plug\Content\Wordpress;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Readable;
use Core\Plug\Response;

/**
 * WordPress post and profile reading.
 */
class Read implements Readable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private string $siteUrl = '';

    private ?string $username = null;

    private ?string $applicationPassword = null;

    /**
     * Set site URL.
     */
    public function forSite(string $siteUrl): self
    {
        $this->siteUrl = rtrim($siteUrl, '/');

        return $this;
    }

    /**
     * Set basic auth credentials.
     */
    public function withCredentials(string $username, string $password): self
    {
        $this->username = $username;
        $this->applicationPassword = $password;

        return $this;
    }

    /**
     * Get a post by ID.
     */
    public function get(string $id): Response
    {
        if (! $this->siteUrl) {
            return $this->error('Site URL is required');
        }

        $response = $this->http()
            ->withBasicAuth($this->username, $this->applicationPassword)
            ->get($this->siteUrl."/wp-json/wp/v2/posts/{$id}");

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['id'],
            'title' => $data['title']['rendered'] ?? '',
            'content' => $data['content']['rendered'] ?? '',
            'excerpt' => $data['excerpt']['rendered'] ?? '',
            'slug' => $data['slug'],
            'url' => $data['link'],
            'status' => $data['status'],
            'date' => $data['date'],
            'modified' => $data['modified'],
            'author' => $data['author'],
            'categories' => $data['categories'] ?? [],
            'tags' => $data['tags'] ?? [],
            'featured_media' => $data['featured_media'] ?? null,
        ]);
    }

    /**
     * Get the authenticated user's profile.
     */
    public function me(): Response
    {
        if (! $this->siteUrl) {
            return $this->error('Site URL is required');
        }

        $response = $this->http()
            ->withBasicAuth($this->username, $this->applicationPassword)
            ->get($this->siteUrl.'/wp-json/wp/v2/users/me');

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['id'],
            'username' => $data['slug'],
            'name' => $data['name'],
            'image' => $data['avatar_urls']['96'] ?? $data['avatar_urls']['48'] ?? null,
            'description' => $data['description'] ?? null,
            'url' => $data['url'] ?? null,
            'link' => $data['link'] ?? null,
            'roles' => $data['roles'] ?? [],
        ]);
    }

    /**
     * List posts.
     */
    public function list(array $params = []): Response
    {
        if (! $this->siteUrl) {
            return $this->error('Site URL is required');
        }

        $queryParams = [
            'per_page' => $params['per_page'] ?? 10,
            'page' => $params['page'] ?? 1,
            'status' => $params['status'] ?? 'publish',
            'orderby' => $params['orderby'] ?? 'date',
            'order' => $params['order'] ?? 'desc',
        ];

        if (isset($params['author'])) {
            $queryParams['author'] = $params['author'];
        }

        if (isset($params['categories'])) {
            $queryParams['categories'] = is_array($params['categories'])
                ? implode(',', $params['categories'])
                : $params['categories'];
        }

        if (isset($params['tags'])) {
            $queryParams['tags'] = is_array($params['tags'])
                ? implode(',', $params['tags'])
                : $params['tags'];
        }

        if (isset($params['search'])) {
            $queryParams['search'] = $params['search'];
        }

        $response = $this->http()
            ->withBasicAuth($this->username, $this->applicationPassword)
            ->get($this->siteUrl.'/wp-json/wp/v2/posts', $queryParams);

        return $this->fromHttp($response, fn ($data) => [
            'posts' => array_map(fn ($post) => [
                'id' => $post['id'],
                'title' => $post['title']['rendered'] ?? '',
                'excerpt' => strip_tags($post['excerpt']['rendered'] ?? ''),
                'slug' => $post['slug'],
                'url' => $post['link'],
                'status' => $post['status'],
                'date' => $post['date'],
            ], $data),
            'total' => (int) ($response->header('X-WP-Total') ?? count($data)),
            'total_pages' => (int) ($response->header('X-WP-TotalPages') ?? 1),
        ]);
    }

    /**
     * Get categories.
     */
    public function categories(array $params = []): Response
    {
        if (! $this->siteUrl) {
            return $this->error('Site URL is required');
        }

        $response = $this->http()
            ->withBasicAuth($this->username, $this->applicationPassword)
            ->get($this->siteUrl.'/wp-json/wp/v2/categories', [
                'per_page' => $params['per_page'] ?? 100,
            ]);

        return $this->fromHttp($response, fn ($data) => [
            'categories' => array_map(fn ($cat) => [
                'id' => $cat['id'],
                'name' => $cat['name'],
                'slug' => $cat['slug'],
                'count' => $cat['count'],
            ], $data),
        ]);
    }

    /**
     * Get tags.
     */
    public function tags(array $params = []): Response
    {
        if (! $this->siteUrl) {
            return $this->error('Site URL is required');
        }

        $response = $this->http()
            ->withBasicAuth($this->username, $this->applicationPassword)
            ->get($this->siteUrl.'/wp-json/wp/v2/tags', [
                'per_page' => $params['per_page'] ?? 100,
            ]);

        return $this->fromHttp($response, fn ($data) => [
            'tags' => array_map(fn ($tag) => [
                'id' => $tag['id'],
                'name' => $tag['name'],
                'slug' => $tag['slug'],
                'count' => $tag['count'],
            ], $data),
        ]);
    }
}
