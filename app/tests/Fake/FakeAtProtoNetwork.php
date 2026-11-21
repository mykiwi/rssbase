<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\AtProto\Identity\DnsTxtRecords;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The AT Protocol network of the tests: DNS records, DID documents and the servers of the accounts.
 * Its state is static, so that it survives the reboots of the kernel between the requests of a test.
 * Each test starts from reset().
 */
final class FakeAtProtoNetwork implements DnsTxtRecords
{
    public const string PDS = 'https://pds.fake.test';

    /** Resolved through its DNS record. */
    public const string ALICE = 'alice.fake.test';
    public const string ALICE_DID = 'did:plc:alice234567abcdefghijklm';

    public const string ALICE_AVATAR_CID = 'bafkreialiceavatar234567abcdefghijklmnopqrstuvwxyz2345';

    /** Resolved through the well-known file of its domain, without a profile. */
    public const string BOB = 'bob.bsky.social';
    public const string BOB_DID = 'did:plc:bob234567abcdefghijklmno';

    /** A did:web account. */
    public const string CAROL = 'carol.fake.test';
    public const string CAROL_DID = 'did:web:carol.fake.test';

    /** @var array<string, list<string>> */
    private static array $dns = [];

    /** @var array<string, array{status: int, body: string, headers: array<string, string>}> */
    private static array $documents = [];

    /** @var list<string> */
    private static array $unreachableHosts = [];

    /** @var list<string> */
    private static array $requests = [];

    public static function reset(): void
    {
        self::$dns = [];
        self::$documents = [];
        self::$unreachableHosts = [];
        self::$requests = [];
        FakeAuthorizationServer::reset();

        self::publish(self::PDS . '/.well-known/oauth-protected-resource', [
            'resource' => self::PDS,
            'authorization_servers' => [FakeAuthorizationServer::ISSUER],
        ]);
        self::publish(
            FakeAuthorizationServer::ISSUER . '/.well-known/oauth-authorization-server',
            FakeAuthorizationServer::metadata(),
        );

        self::addAccount(self::ALICE, self::ALICE_DID);
        self::publish(self::PDS
        . '/xrpc/com.atproto.repo.getRecord?repo='
        . self::ALICE_DID
        . '&collection=app.bsky.actor.profile&rkey=self', [
            'uri' => 'at://' . self::ALICE_DID . '/app.bsky.actor.profile/self',
            'value' => [
                '$type' => 'app.bsky.actor.profile',
                'displayName' => 'Alice',
                'avatar' => [
                    '$type' => 'blob',
                    'ref' => ['$link' => self::ALICE_AVATAR_CID],
                    'mimeType' => 'image/png',
                    'size' => 68,
                ],
            ],
        ]);
        self::addAccount(self::BOB, self::BOB_DID, resolution: 'well-known');
        self::addAccount(self::CAROL, self::CAROL_DID);
    }

    /**
     * Publishes the handle of an account and its DID document.
     *
     * @param 'dns'|'well-known' $resolution where the domain of the handle declares the DID
     * @param ?string             $declaredHandle the handle the DID document declares, when it differs from the domain
     */
    public static function addAccount(
        string $handle,
        string $did,
        string $resolution = 'dns',
        ?string $declaredHandle = null,
        string $pds = self::PDS,
    ): void {
        match ($resolution) {
            'dns' => self::$dns["_atproto.{$handle}"] = ["did={$did}"],
            'well-known' => self::publish("https://{$handle}/.well-known/atproto-did", $did, contentType: 'text/plain'),
        };

        $documentUrl = str_starts_with($did, 'did:web:')
            ? 'https://' . substr($did, \strlen('did:web:')) . '/.well-known/did.json'
            : "https://plc.directory/{$did}";

        self::publish($documentUrl, [
            '@context' => ['https://www.w3.org/ns/did/v1'],
            'id' => $did,
            'alsoKnownAs' => ['at://' . ($declaredHandle ?? $handle)],
            'verificationMethod' => [],
            'service' => [['id' => '#atproto_pds', 'type' => 'AtprotoPersonalDataServer', 'serviceEndpoint' => $pds]],
        ]);
    }

    /**
     * @param string|array<array-key, mixed> $body an array is sent as JSON
     * @param array<string, string>          $headers
     */
    public static function publish(
        string $url,
        string|array $body,
        int $status = 200,
        string $contentType = 'application/json',
        array $headers = [],
    ): void {
        self::$documents[rawurldecode($url)] = [
            'status' => $status,
            'body' => \is_array($body) ? json_encode($body, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES) : $body,
            'headers' => ['content-type' => $contentType, ...$headers],
        ];
    }

    public static function makeUnreachable(string $host): void
    {
        self::$unreachableHosts[] = $host;
    }

    /**
     * @return list<string> the method and URL of each request the network received, in order
     */
    public static function requests(): array
    {
        return self::$requests;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options): MockResponse
    {
        self::$requests[] = "{$method} {$url}";

        if (\in_array(parse_url($url, \PHP_URL_HOST), self::$unreachableHosts, strict: true)) {
            return new MockResponse(info: ['error' => 'Connection refused.']);
        }

        if ('POST' === $method && str_starts_with($url, FakeAuthorizationServer::ISSUER . '/')) {
            return FakeAuthorizationServer::handle($url, $options);
        }

        // Clients differ in what they encode in a query string.
        $document = self::$documents[rawurldecode($url)] ?? null;
        if (null !== $document) {
            return new MockResponse($document['body'], [
                'http_code' => $document['status'],
                'response_headers' => $document['headers'],
            ]);
        }

        return new MockResponse('{"error":"NotFound"}', [
            'http_code' => 404,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    }

    /**
     * @return ?string the DID the DNS record of the handle declares
     */
    public static function didOf(string $handle): ?string
    {
        $record = self::$dns["_atproto.{$handle}"][0] ?? '';

        return str_starts_with($record, 'did=') ? substr($record, offset: 4) : null;
    }

    public function lookup(string $name): array
    {
        return self::$dns[$name] ?? [];
    }
}
