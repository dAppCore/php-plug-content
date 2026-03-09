<?php

declare(strict_types=1);

namespace Core\Plug\Content\Medium;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Postable;
use Core\Plug\Response;
use Illuminate\Support\Collection;

/**
 * Medium article publishing.
 */
class Post implements Postable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://api.medium.com/v1';

    private ?string $authorId = null;

    private ?string $publicationId = null;

    /**
     * Set the author ID for posting.
     */
    public function forAuthor(string $authorId): self
    {
        $this->authorId = $authorId;

        return $this;
    }

    /**
     * Set publication ID for posting to a publication.
     */
    public function forPublication(string $publicationId): self
    {
        $this->publicationId = $publicationId;

        return $this;
    }

    /**
     * Publish an article.
     *
     * @param  string  $text  The HTML or Markdown content
     * @param  Collection  $media  Not used - Medium handles media within content
     * @param  array  $params  title (required), contentFormat (html/markdown), tags, canonicalUrl, publishStatus
     */
    public function publish(string $text, Collection $media, array $params = []): Response
    {
        $title = $params['title'] ?? null;

        if (! $title) {
            return $this->error('Title is required');
        }

        $postData = [
            'title' => $title,
            'contentFormat' => $params['contentFormat'] ?? 'html', // html or markdown
            'content' => $text,
            'publishStatus' => $params['publishStatus'] ?? 'public', // public, draft, unlisted
        ];

        // Optional parameters
        if (isset($params['tags'])) {
            $postData['tags'] = array_slice((array) $params['tags'], 0, 5); // Max 5 tags
        }

        if (isset($params['canonicalUrl'])) {
            $postData['canonicalUrl'] = $params['canonicalUrl'];
        }

        if (isset($params['license'])) {
            $postData['license'] = $params['license']; // all-rights-reserved, cc-40-by, etc.
        }

        if (isset($params['notifyFollowers'])) {
            $postData['notifyFollowers'] = $params['notifyFollowers'];
        }

        // Determine endpoint
        $endpoint = $this->publicationId
            ? self::API_URL."/publications/{$this->publicationId}/posts"
            : self::API_URL."/users/{$this->authorId}/posts";

        if (! $this->publicationId && ! $this->authorId) {
            return $this->error('Author ID or Publication ID required');
        }

        $response = $this->http()
            ->withToken($this->accessToken())
            ->post($endpoint, $postData);

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['data']['id'],
            'title' => $data['data']['title'],
            'url' => $data['data']['url'],
            'publishStatus' => $data['data']['publishStatus'],
            'publishedAt' => $data['data']['publishedAt'] ?? null,
            'authorId' => $data['data']['authorId'],
        ]);
    }

    /**
     * Get external URL to an article.
     */
    public static function externalPostUrl(string $url): string
    {
        return $url;
    }

    /**
     * Get external URL to a profile.
     */
    public static function externalAccountUrl(string $username): string
    {
        return "https://medium.com/@{$username}";
    }
}
