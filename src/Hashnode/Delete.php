<?php

declare(strict_types=1);

namespace Core\Plug\Content\Hashnode;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Deletable;
use Core\Plug\Response;

/**
 * Hashnode post deletion.
 */
class Delete implements Deletable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://gql.hashnode.com';

    /**
     * Delete a post.
     */
    public function delete(string $id): Response
    {
        $query = <<<'GRAPHQL'
            mutation RemovePost($input: RemovePostInput!) {
                removePost(input: $input) {
                    post {
                        id
                    }
                }
            }
        GRAPHQL;

        $response = $this->http()
            ->withHeaders(['Authorization' => $this->accessToken()])
            ->post(self::API_URL, [
                'query' => $query,
                'variables' => [
                    'input' => ['id' => $id],
                ],
            ]);

        return $this->fromHttp($response, function ($data) {
            if (isset($data['errors'])) {
                return ['error' => $data['errors'][0]['message'] ?? 'Deletion failed'];
            }

            return ['deleted' => true];
        });
    }
}
