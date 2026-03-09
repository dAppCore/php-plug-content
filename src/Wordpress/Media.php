<?php

declare(strict_types=1);

namespace Core\Plug\Content\Wordpress;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\MediaUploadable;
use Core\Plug\Response;

/**
 * WordPress media upload.
 */
class Media implements MediaUploadable
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

    public function upload(array $item): Response
    {
        if (! $this->siteUrl) {
            return $this->error('Site URL is required');
        }

        $path = $item['path'] ?? null;

        if (! $path || ! file_exists($path)) {
            return $this->error('File not found');
        }

        $filename = $item['name'] ?? basename($path);
        $mimeType = mime_content_type($path);

        $response = $this->http()
            ->withBasicAuth($this->username, $this->applicationPassword)
            ->withHeaders([
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Content-Type' => $mimeType,
            ])
            ->withBody(file_get_contents($path), $mimeType)
            ->post($this->siteUrl.'/wp-json/wp/v2/media');

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['id'],
            'title' => $data['title']['rendered'] ?? $filename,
            'url' => $data['source_url'] ?? $data['guid']['rendered'],
            'link' => $data['link'],
            'mime_type' => $data['mime_type'],
            'media_details' => $data['media_details'] ?? null,
        ]);
    }

    /**
     * Update media item metadata.
     */
    public function update(int $mediaId, array $params): Response
    {
        if (! $this->siteUrl) {
            return $this->error('Site URL is required');
        }

        $response = $this->http()
            ->withBasicAuth($this->username, $this->applicationPassword)
            ->post($this->siteUrl."/wp-json/wp/v2/media/{$mediaId}", array_filter([
                'title' => $params['title'] ?? null,
                'caption' => $params['caption'] ?? null,
                'alt_text' => $params['alt_text'] ?? $params['alt'] ?? null,
                'description' => $params['description'] ?? null,
            ]));

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['id'],
            'title' => $data['title']['rendered'] ?? null,
            'url' => $data['source_url'] ?? null,
        ]);
    }

    /**
     * Delete a media item.
     */
    public function delete(int $mediaId, bool $force = false): Response
    {
        if (! $this->siteUrl) {
            return $this->error('Site URL is required');
        }

        $response = $this->http()
            ->withBasicAuth($this->username, $this->applicationPassword)
            ->delete($this->siteUrl."/wp-json/wp/v2/media/{$mediaId}", [
                'force' => $force,
            ]);

        return $this->fromHttp($response, fn ($data) => [
            'deleted' => $data['deleted'] ?? true,
        ]);
    }
}
