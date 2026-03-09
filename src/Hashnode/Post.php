<?php

declare(strict_types=1);

namespace Core\Plug\Content\Hashnode;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Postable;
use Core\Plug\Response;
use Illuminate\Support\Collection;

/**
 * Hashnode article publishing via GraphQL API.
 */
class Post implements Postable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://gql.hashnode.com';

    private ?string $publicationId = null;

    /**
     * Set publication ID for posting.
     */
    public function forPublication(string $publicationId): self
    {
        $this->publicationId = $publicationId;

        return $this;
    }

    /**
     * Publish an article.
     *
     * @param  string  $text  Markdown content
     * @param  Collection  $media  Cover image (first item used)
     * @param  array  $params  title (required), subtitle, slug, tags, canonicalUrl, publishedAt
     */
    public function publish(string $text, Collection $media, array $params = []): Response
    {
        $title = $params['title'] ?? null;
        $publicationId = $params['publication_id'] ?? $this->publicationId;

        if (! $title) {
            return $this->error('Title is required');
        }

        if (! $publicationId) {
            return $this->error('Publication ID is required');
        }

        $input = [
            'title' => $title,
            'contentMarkdown' => $text,
            'publicationId' => $publicationId,
        ];

        // Optional fields
        if (isset($params['subtitle'])) {
            $input['subtitle'] = $params['subtitle'];
        }

        if (isset($params['slug'])) {
            $input['slug'] = $params['slug'];
        }

        if (isset($params['canonicalUrl'])) {
            $input['originalArticleURL'] = $params['canonicalUrl'];
        }

        if ($media->isNotEmpty()) {
            $input['coverImageOptions'] = [
                'coverImageURL' => $media->first()['url'] ?? null,
            ];
        }

        if (isset($params['tags'])) {
            $input['tags'] = array_map(fn ($tag) => [
                'slug' => is_array($tag) ? $tag['slug'] : $tag,
                'name' => is_array($tag) ? ($tag['name'] ?? $tag['slug']) : $tag,
            ], (array) $params['tags']);
        }

        if (isset($params['publishedAt'])) {
            $input['publishedAt'] = $params['publishedAt'];
        }

        // Determine if draft or published
        $mutation = isset($params['draft']) && $params['draft']
            ? 'createDraft'
            : 'publishPost';

        $query = <<<'GRAPHQL'
            mutation PublishPost($input: PublishPostInput!) {
                publishPost(input: $input) {
                    post {
                        id
                        title
                        slug
                        url
                        publication {
                            id
                        }
                    }
                }
            }
        GRAPHQL;

        $response = $this->http()
            ->withHeaders(['Authorization' => $this->accessToken()])
            ->post(self::API_URL, [
                'query' => $query,
                'variables' => ['input' => $input],
            ]);

        return $this->fromHttp($response, function ($data) {
            $post = $data['data']['publishPost']['post'] ?? null;

            if (! $post) {
                $errors = $data['errors'] ?? [];

                return ['error' => $errors[0]['message'] ?? 'Failed to publish'];
            }

            return [
                'id' => $post['id'],
                'title' => $post['title'],
                'slug' => $post['slug'],
                'url' => $post['url'],
            ];
        });
    }

    /**
     * Update an existing post.
     */
    public function update(string $postId, array $params): Response
    {
        $input = array_filter([
            'id' => $postId,
            'title' => $params['title'] ?? null,
            'contentMarkdown' => $params['content'] ?? $params['body'] ?? null,
            'subtitle' => $params['subtitle'] ?? null,
            'slug' => $params['slug'] ?? null,
        ]);

        $query = <<<'GRAPHQL'
            mutation UpdatePost($input: UpdatePostInput!) {
                updatePost(input: $input) {
                    post {
                        id
                        title
                        slug
                        url
                    }
                }
            }
        GRAPHQL;

        $response = $this->http()
            ->withHeaders(['Authorization' => $this->accessToken()])
            ->post(self::API_URL, [
                'query' => $query,
                'variables' => ['input' => $input],
            ]);

        return $this->fromHttp($response, function ($data) {
            $post = $data['data']['updatePost']['post'] ?? null;

            return $post ? [
                'id' => $post['id'],
                'title' => $post['title'],
                'url' => $post['url'],
            ] : ['error' => 'Failed to update'];
        });
    }

    /**
     * Get external URL to an article.
     */
    public static function externalPostUrl(string $publicationHost, string $slug): string
    {
        return "https://{$publicationHost}/{$slug}";
    }

    /**
     * Get external URL to a profile.
     */
    public static function externalAccountUrl(string $username): string
    {
        return "https://hashnode.com/@{$username}";
    }
}
