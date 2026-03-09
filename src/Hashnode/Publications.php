<?php

declare(strict_types=1);

namespace Core\Plug\Content\Hashnode;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Listable;
use Core\Plug\Response;

/**
 * Hashnode publications listing.
 */
class Publications implements Listable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://gql.hashnode.com';

    /**
     * List user's publications.
     */
    public function listEntities(): Response
    {
        $query = <<<'GRAPHQL'
            query {
                me {
                    publications(first: 50) {
                        edges {
                            node {
                                id
                                title
                                displayTitle
                                url
                                about {
                                    markdown
                                }
                                favicon
                                isTeam
                                postsCount
                            }
                        }
                    }
                }
            }
        GRAPHQL;

        $response = $this->http()
            ->withHeaders(['Authorization' => $this->accessToken()])
            ->post(self::API_URL, ['query' => $query]);

        return $this->fromHttp($response, function ($data) {
            $publications = $data['data']['me']['publications']['edges'] ?? [];

            return [
                'publications' => array_map(fn ($edge) => [
                    'id' => $edge['node']['id'],
                    'title' => $edge['node']['title'],
                    'display_title' => $edge['node']['displayTitle'] ?? $edge['node']['title'],
                    'url' => $edge['node']['url'],
                    'about' => $edge['node']['about']['markdown'] ?? null,
                    'favicon' => $edge['node']['favicon'] ?? null,
                    'is_team' => $edge['node']['isTeam'] ?? false,
                    'posts_count' => $edge['node']['postsCount'] ?? 0,
                ], $publications),
            ];
        });
    }

    /**
     * Get publication by ID.
     */
    public function get(string $publicationId): Response
    {
        $query = <<<'GRAPHQL'
            query GetPublication($id: ObjectId!) {
                publication(id: $id) {
                    id
                    title
                    displayTitle
                    url
                    about {
                        markdown
                    }
                    favicon
                    isTeam
                    postsCount
                    author {
                        id
                        username
                        name
                    }
                }
            }
        GRAPHQL;

        $response = $this->http()
            ->withHeaders(['Authorization' => $this->accessToken()])
            ->post(self::API_URL, [
                'query' => $query,
                'variables' => ['id' => $publicationId],
            ]);

        return $this->fromHttp($response, function ($data) {
            $pub = $data['data']['publication'] ?? null;

            if (! $pub) {
                return ['error' => 'Publication not found'];
            }

            return [
                'id' => $pub['id'],
                'title' => $pub['title'],
                'display_title' => $pub['displayTitle'] ?? $pub['title'],
                'url' => $pub['url'],
                'about' => $pub['about']['markdown'] ?? null,
                'favicon' => $pub['favicon'] ?? null,
                'is_team' => $pub['isTeam'] ?? false,
                'posts_count' => $pub['postsCount'] ?? 0,
                'author' => $pub['author'] ?? null,
            ];
        });
    }
}
