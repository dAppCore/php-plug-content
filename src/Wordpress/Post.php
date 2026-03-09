<?php

declare(strict_types=1);

namespace Core\Plug\Content\Wordpress;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Postable;
use Core\Plug\Response;
use Illuminate\Support\Collection;

/**
 * WordPress post publishing via REST API.
 */
class Post implements Postable
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
     * Publish a post.
     *
     * @param  string  $text  HTML content
     * @param  Collection  $media  Featured image (first item used)
     * @param  array  $params  title, status, excerpt, categories, tags, format, featured_media, slug
     */
    public function publish(string $text, Collection $media, array $params = []): Response
    {
        if (! $this->siteUrl) {
            return $this->error('Site URL is required');
        }

        $title = $params['title'] ?? null;

        if (! $title) {
            return $this->error('Title is required');
        }

        $postData = [
            'title' => $title,
            'content' => $text,
            'status' => $params['status'] ?? 'publish', // publish, draft, pending, private
        ];

        // Optional parameters
        if (isset($params['excerpt'])) {
            $postData['excerpt'] = $params['excerpt'];
        }

        if (isset($params['slug'])) {
            $postData['slug'] = $params['slug'];
        }

        if (isset($params['categories'])) {
            $postData['categories'] = (array) $params['categories'];
        }

        if (isset($params['tags'])) {
            $postData['tags'] = (array) $params['tags'];
        }

        if (isset($params['format'])) {
            $postData['format'] = $params['format']; // standard, aside, gallery, link, etc.
        }

        if (isset($params['featured_media'])) {
            $postData['featured_media'] = $params['featured_media'];
        }

        // Handle media upload for featured image
        if ($media->isNotEmpty() && ! isset($params['featured_media'])) {
            $mediaUploader = (new Media)
                ->forSite($this->siteUrl)
                ->withCredentials($this->username, $this->applicationPassword);

            $uploadResult = $mediaUploader->upload($media->first());

            if ($uploadResult->isOk()) {
                $postData['featured_media'] = $uploadResult->get('id');
            }
        }

        $response = $this->http()
            ->withBasicAuth($this->username, $this->applicationPassword)
            ->post($this->siteUrl.'/wp-json/wp/v2/posts', $postData);

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['id'],
            'title' => $data['title']['rendered'] ?? $title,
            'slug' => $data['slug'],
            'url' => $data['link'],
            'status' => $data['status'],
            'guid' => $data['guid']['rendered'] ?? null,
        ]);
    }

    /**
     * Update an existing post.
     */
    public function update(int $postId, array $params): Response
    {
        if (! $this->siteUrl) {
            return $this->error('Site URL is required');
        }

        $postData = array_filter([
            'title' => $params['title'] ?? null,
            'content' => $params['content'] ?? null,
            'excerpt' => $params['excerpt'] ?? null,
            'status' => $params['status'] ?? null,
            'slug' => $params['slug'] ?? null,
            'categories' => $params['categories'] ?? null,
            'tags' => $params['tags'] ?? null,
            'featured_media' => $params['featured_media'] ?? null,
        ]);

        $response = $this->http()
            ->withBasicAuth($this->username, $this->applicationPassword)
            ->post($this->siteUrl."/wp-json/wp/v2/posts/{$postId}", $postData);

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['id'],
            'title' => $data['title']['rendered'] ?? null,
            'url' => $data['link'],
            'status' => $data['status'],
        ]);
    }

    /**
     * Get external URL to a post.
     */
    public static function externalPostUrl(string $siteUrl, string $slug): string
    {
        return rtrim($siteUrl, '/').'/'.$slug;
    }

    /**
     * Get external URL to site.
     */
    public static function externalAccountUrl(string $siteUrl): string
    {
        return $siteUrl;
    }
}
