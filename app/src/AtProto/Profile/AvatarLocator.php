<?php

declare(strict_types=1);

namespace App\AtProto\Profile;

use App\AtProto\HttpsUrl;
use App\AtProto\Identity\Identity;
use App\AtProto\JsonObject;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Finds the avatar of an account in its own PDS, not through the API of Bluesky: the profile record is
 * public, and the PDS serves the image.
 *
 * @see https://atproto.com/specs/xrpc
 */
final readonly class AvatarLocator
{
    public function __construct(
        #[Autowire(service: 'atproto.client')]
        private HttpClientInterface $client,
    ) {}

    /**
     * @return ?HttpsUrl the address of the image, or null when the account has no profile or no avatar
     */
    public function locate(Identity $identity): ?HttpsUrl
    {
        try {
            $response = $this->client->request(
                'GET',
                $identity->pds->url('/xrpc/com.atproto.repo.getRecord')->value,
                ['query' => [
                    'repo' => $identity->did->value,
                    'collection' => 'app.bsky.actor.profile',
                    'rkey' => 'self',
                ]],
            );
            $record = 200 === $response->getStatusCode() ? new JsonObject($response->toArray()) : new JsonObject([]);
        } catch (ExceptionInterface) {
            // No avatar is no reason to refuse a login: a neutral image stands for it.
            return null;
        }

        $cid = $record->object('value')?->object('avatar')?->object('ref')?->string('$link') ?? '';

        if (1 !== preg_match('/^[a-z0-9]+$/', $cid)) {
            return null;
        }

        return $identity
            ->pds
            ->url('/xrpc/com.atproto.sync.getBlob')
            ->withQuery(http_build_query(['did' => $identity->did->value, 'cid' => $cid]));
    }
}
