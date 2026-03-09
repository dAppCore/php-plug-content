<?php

declare(strict_types=1);

namespace Core\Plug\Content\Hashnode;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Readable;
use Core\Plug\Response;

/**
 * Hashnode post and profile reading via GraphQL API.
 */
class Read implements Readable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://gql.hashnode.com';

    /**
     * Get a post by ID.
     */
    public function get(string $id): Response
    {
        $query = <<<'GRAPHQL'
            query GetPost($id: ID!) {
                post(id: $id) {
                    id
                    title
                    slug
                    url
                    content {
                        markdown
                        html
                    }
                    brief
                    coverImage {
                        url
                    }
                    tags {
                        name
                        slug
                    }
                    publishedAt
                    views
                    reactionCount
                    responseCount
                }
            }
        GRAPHQL;

        $response = $this->http()
            ->withHeaders(['Authorization' => $this->accessToken()])
            ->post(self::API_URL, [
                'query' => $query,
                'variables' => ['id' => $id],
            ]);

        return $this->fromHttp($response, function ($data) {
            $post = $data['data']['post'] ?? null;

            if (! $post) {
                return ['error' => 'Post not found'];
            }

            return [
                'id' => $post['id'],
                'title' => $post['title'],
                'slug' => $post['slug'],
                'url' => $post['url'],
                'content_markdown' => $post['content']['markdown'] ?? null,
                'content_html' => $post['content']['html'] ?? null,
                'brief' => $post['brief'] ?? null,
                'cover_image' => $post['coverImage']['url'] ?? null,
                'tags' => $post['tags'] ?? [],
                'published_at' => $post['publishedAt'] ?? null,
                'views' => $post['views'] ?? 0,
                'reactions' => $post['reactionCount'] ?? 0,
                'responses' => $post['responseCount'] ?? 0,
            ];
        });
    }

    /**
     * Get the authenticated user's profile.
     */
    public function me(): Response
    {
        $query = <<<'GRAPHQL'
            query {
                me {
                    id
                    username
                    name
                    bio {
                        markdown
                    }
                    profilePicture
                    publications(first: 10) {
                        edges {
                            node {
                                id
                                title
                                url
                            }
                        }
                    }
                    followersCount
                    followingsCount
                }
            }
        GRAPHQL;

        $response = $this->http()
            ->withHeaders(['Authorization' => $this->accessToken()])
            ->post(self::API_URL, ['query' => $query]);

        return $this->fromHttp($response, function ($data) {
            $user = $data['data']['me'] ?? null;

            if (! $user) {
                return ['error' => 'Not authenticated'];
            }

            return [
                'id' => $user['id'],
                'username' => $user['username'],
                'name' => $user['name'],
                'image' => $user['profilePicture'] ?? null,
                'bio' => $user['bio']['markdown'] ?? null,
                'followers_count' => $user['followersCount'] ?? 0,
                'following_count' => $user['followingsCount'] ?? 0,
                'publications' => array_map(fn ($edge) => [
                    'id' => $edge['node']['id'],
                    'title' => $edge['node']['title'],
                    'url' => $edge['node']['url'],
                ], $user['publications']['edges'] ?? []),
            ];
        });
    }

    /**
     * List posts from a publication.
     */
    public function list(array $params = []): Response
    {
        $publicationId = $params['publication_id'] ?? null;

        if (! $publicationId) {
            return $this->error('publication_id is required');
        }

        $query = <<<'GRAPHQL'
            query GetPublicationPosts($publicationId: ObjectId!, $first: Int!, $after: String) {
                publication(id: $publicationId) {
                    posts(first: $first, after: $after) {
                        edges {
                            node {
                                id
                                title
                                slug
                                url
                                brief
                                publishedAt
                                views
                                reactionCount
                            }
                            cursor
                        }
                        pageInfo {
                            hasNextPage
                            endCursor
                        }
                    }
                }
            }
        GRAPHQL;

        $response = $this->http()
            ->withHeaders(['Authorization' => $this->accessToken()])
            ->post(self::API_URL, [
                'query' => $query,
                'variables' => [
                    'publicationId' => $publicationId,
                    'first' => $params['limit'] ?? 20,
                    'after' => $params['cursor'] ?? null,
                ],
            ]);

        return $this->fromHttp($response, function ($data) {
            $posts = $data['data']['publication']['posts'] ?? null;

            if (! $posts) {
                return ['error' => 'Publication not found'];
            }

            return [
                'posts' => array_map(fn ($edge) => [
                    'id' => $edge['node']['id'],
                    'title' => $edge['node']['title'],
                    'slug' => $edge['node']['slug'],
                    'url' => $edge['node']['url'],
                    'brief' => $edge['node']['brief'],
                    'published_at' => $edge['node']['publishedAt'],
                    'views' => $edge['node']['views'] ?? 0,
                    'reactions' => $edge['node']['reactionCount'] ?? 0,
                ], $posts['edges'] ?? []),
                'has_next_page' => $posts['pageInfo']['hasNextPage'] ?? false,
                'cursor' => $posts['pageInfo']['endCursor'] ?? null,
            ];
        });
    }
}
