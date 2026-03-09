<?php

declare(strict_types=1);

namespace Core\Plug\Content\Wordpress;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Deletable;
use Core\Plug\Response;

/**
 * WordPress post deletion.
 */
class Delete implements Deletable
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
     * Delete a post.
     *
     * @param  string  $id  Post ID
     */
    public function delete(string $id): Response
    {
        if (! $this->siteUrl) {
            return $this->error('Site URL is required');
        }

        // force=true permanently deletes, otherwise moves to trash
        $response = $this->http()
            ->withBasicAuth($this->username, $this->applicationPassword)
            ->delete($this->siteUrl."/wp-json/wp/v2/posts/{$id}", [
                'force' => false, // Move to trash
            ]);

        return $this->fromHttp($response, fn ($data) => [
            'deleted' => true,
            'id' => $data['id'],
            'status' => $data['status'], // Should be 'trash'
        ]);
    }

    /**
     * Permanently delete a post.
     */
    public function forceDelete(int $postId): Response
    {
        if (! $this->siteUrl) {
            return $this->error('Site URL is required');
        }

        $response = $this->http()
            ->withBasicAuth($this->username, $this->applicationPassword)
            ->delete($this->siteUrl."/wp-json/wp/v2/posts/{$postId}", [
                'force' => true,
            ]);

        return $this->fromHttp($response, fn ($data) => [
            'deleted' => $data['deleted'] ?? true,
            'previous' => $data['previous'] ?? null,
        ]);
    }
}
