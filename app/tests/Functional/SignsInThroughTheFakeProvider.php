<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Fake\FakeAuthorizationServer;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Drives a login against the fake network: the form of RssBase, then the answer of the provider,
 * which a test gives without its approval page.
 */
trait SignsInThroughTheFakeProvider
{
    /**
     * @return string the URL of the provider the visitor is sent to
     */
    private static function submitHandle(
        KernelBrowser $client,
        string $handle,
        string $site = 'http://localhost',
    ): string {
        $client->request('GET', uri: "{$site}/login");
        $client->submitForm(self::submitLabel($site), ['handle' => $handle]);

        return (string) $client->getResponse()->headers->get('Location');
    }

    /**
     * The provider answers: it approves the account of the DID, or the visitor refuses when there is none.
     * The answer goes through the loopback callback on 127.0.0.1, which sends it to the site of the visitor.
     */
    private static function answer(KernelBrowser $client, string $authorizationUrl, ?string $did): void
    {
        $client->request('GET', FakeAuthorizationServer::answer($authorizationUrl, $did));

        while ($client->getResponse()->isRedirection()) {
            $client->followRedirect();
        }
    }

    private static function submitLabel(string $site): string
    {
        return str_contains($site, '//fr.') ? 'Continuer' : 'Continue';
    }
}
