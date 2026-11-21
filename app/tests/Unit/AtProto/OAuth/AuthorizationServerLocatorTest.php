<?php

declare(strict_types=1);

namespace App\Tests\Unit\AtProto\OAuth;

use App\AtProto\Identity\Exception\ProviderUnreachable;
use App\AtProto\OAuth\AuthorizationServerLocator;
use App\Tests\Fake\FakeAtProtoNetwork;
use App\Tests\Fake\FakeAuthorizationServer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

final class AuthorizationServerLocatorTest extends TestCase
{
    private AuthorizationServerLocator $locator;

    protected function setUp(): void
    {
        FakeAtProtoNetwork::reset();
        $this->locator = new AuthorizationServerLocator(new MockHttpClient(new FakeAtProtoNetwork()));
    }

    public function testAnIssuerWhoseMetadataNamesAnotherIssuerIsRefused(): void
    {
        FakeAtProtoNetwork::publish(
            'https://impostor.fake.test/.well-known/oauth-authorization-server',
            FakeAuthorizationServer::metadata(),
        );

        $this->expectException(ProviderUnreachable::class);

        $this->locator->forIssuer('https://impostor.fake.test');
    }

    public function testAnEndpointOutsideHttpsIsRefused(): void
    {
        FakeAtProtoNetwork::publish(FakeAuthorizationServer::ISSUER . '/.well-known/oauth-authorization-server', [
            ...FakeAuthorizationServer::metadata(),
            'pushed_authorization_request_endpoint' => 'http://auth.fake.test/oauth/par',
        ]);

        $this->expectException(ProviderUnreachable::class);

        $this->locator->forIssuer(FakeAuthorizationServer::ISSUER);
    }

    public function testAPdsThatNamesSeveralAuthorizationServersIsRefused(): void
    {
        FakeAtProtoNetwork::publish(FakeAtProtoNetwork::PDS . '/.well-known/oauth-protected-resource', [
            'authorization_servers' => [FakeAuthorizationServer::ISSUER, 'https://other.fake.test'],
        ]);

        $this->expectException(ProviderUnreachable::class);

        $this->locator->forPds(FakeAtProtoNetwork::PDS);
    }
}
