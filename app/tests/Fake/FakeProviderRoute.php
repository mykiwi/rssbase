<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use Playwright\Network\RouteInterface;

/**
 * What the browser of the E2E tests sees of the fake network: the approval page of the authorization
 * server, and the images of the PDS. A Playwright route serves them, as the browser runs out of the
 * process of the HTTP client that the fake network answers.
 */
final class FakeProviderRoute
{
    /** A PNG of 1×1 pixel, in the colors of the brand. */
    private const string AVATAR = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/39mPQAJSAOYYwvM8AAAAABJRU5ErkJggg==';

    public function __invoke(RouteInterface $route): void
    {
        $request = $route->request();
        $path = (string) parse_url($request->url(), \PHP_URL_PATH);

        $route->fulfill(match (true) {
            '/oauth/authorize' === $path => self::approvalPage($request->url()),
            '/xrpc/com.atproto.sync.getBlob' === $path => self::avatar(),
            default => ['status' => 404, 'body' => ''],
        });
    }

    /**
     * Each answer is a link to the callback, as a real provider redirects the browser there.
     *
     * @return array<string, mixed>
     */
    private static function approvalPage(string $authorizationUrl): array
    {
        $hint = FakeAuthorizationServer::pushedRequest($authorizationUrl)['login_hint'] ?? '';
        $approve = htmlspecialchars(FakeAuthorizationServer::answer(
            $authorizationUrl,
            FakeAtProtoNetwork::didOf($hint),
        ));
        $deny = htmlspecialchars(FakeAuthorizationServer::answer($authorizationUrl, did: null));
        $hint = htmlspecialchars($hint);

        return [
            'status' => 200,
            'headers' => ['content-type' => 'text/html; charset=utf-8'],
            'body' => <<<HTML
                <!DOCTYPE html>
                <html lang="en"><head><title>Fake provider</title></head><body>
                <h1>Approve RssBase as {$hint}?</h1>
                <a href="{$approve}">Approve</a> <a href="{$deny}">Deny</a>
                </body></html>
                HTML,
        ];
    }

    /**
     * With the headers of the reference PDS, which a page of another origin must still be able to show.
     *
     * @return array<string, mixed>
     */
    private static function avatar(): array
    {
        return [
            'status' => 200,
            'headers' => [
                'content-type' => 'image/png',
                'content-disposition' => 'attachment; filename="avatar"',
                'content-security-policy' => "default-src 'none'; sandbox",
                'x-content-type-options' => 'nosniff',
            ],
            'body' => self::AVATAR,
            'isBase64' => true,
        ];
    }
}
