<?php

declare(strict_types=1);

namespace Core\Plug\Content\Wordpress;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Authenticable;
use Core\Plug\Response;

/**
 * WordPress REST API authentication.
 *
 * Supports application passwords (self-hosted) and OAuth 2.0 (WordPress.com).
 */
class Auth implements Authenticable
{
    use BuildsResponse;
    use UsesHttp;

    private string $siteUrl;

    private ?string $username = null;

    private ?string $applicationPassword = null;

    public function __construct(string $siteUrl = '', ?string $username = null, ?string $applicationPassword = null)
    {
        $this->siteUrl = rtrim($siteUrl, '/');
        $this->username = $username;
        $this->applicationPassword = $applicationPassword;
    }

    public static function identifier(): string
    {
        return 'wordpress';
    }

    public static function name(): string
    {
        return 'WordPress';
    }

    /**
     * Set site URL.
     */
    public function forSite(string $siteUrl): self
    {
        $this->siteUrl = rtrim($siteUrl, '/');

        return $this;
    }

    /**
     * Application passwords setup URL.
     */
    public function getAuthUrl(): string
    {
        return $this->siteUrl.'/wp-admin/profile.php#application-passwords';
    }

    /**
     * Validate credentials.
     *
     * @param  array  $params  ['site_url' => string, 'username' => string, 'application_password' => string]
     */
    public function requestAccessToken(array $params): array
    {
        $siteUrl = rtrim($params['site_url'] ?? $this->siteUrl, '/');
        $username = $params['username'] ?? $this->username;
        $password = $params['application_password'] ?? $this->applicationPassword;

        if (! $siteUrl) {
            return ['error' => 'Site URL is required'];
        }

        if (! $username || ! $password) {
            return ['error' => 'Username and application password are required'];
        }

        // Validate by fetching user info
        $response = $this->http()
            ->withBasicAuth($username, $password)
            ->get($siteUrl.'/wp-json/wp/v2/users/me');

        if (! $response->successful()) {
            return ['error' => 'Invalid credentials or site configuration'];
        }

        $user = $response->json();

        return [
            'site_url' => $siteUrl,
            'username' => $username,
            'application_password' => $password,
            'user_id' => $user['id'],
            'user_slug' => $user['slug'],
            'user_name' => $user['name'],
        ];
    }

    public function getAccount(): Response
    {
        return $this->error('Use Read::me() with credentials');
    }

    /**
     * Check if site has REST API enabled.
     */
    public function checkSite(string $siteUrl): Response
    {
        $siteUrl = rtrim($siteUrl, '/');

        $response = $this->http()->get($siteUrl.'/wp-json');

        return $this->fromHttp($response, fn ($data) => [
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'url' => $data['url'] ?? $siteUrl,
            'home' => $data['home'] ?? null,
            'namespaces' => $data['namespaces'] ?? [],
            'has_wp_v2' => in_array('wp/v2', $data['namespaces'] ?? []),
        ]);
    }
}
