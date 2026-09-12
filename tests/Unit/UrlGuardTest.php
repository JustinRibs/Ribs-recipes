<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\UnsafeUrlException;
use App\Services\Http\UrlGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The importer fetches URLs an administrator pastes in, which makes it the one
 * place in the application capable of being turned into a probe of the home
 * network. These tests are the contract for what it will and will not touch.
 */
class UrlGuardTest extends TestCase
{
    private UrlGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new UrlGuard(allowPrivateNetworks: false);
    }

    #[Test]
    #[DataProvider('blockedUrls')]
    public function it_refuses_unsafe_targets(string $url): void
    {
        $this->expectException(UnsafeUrlException::class);

        $this->guard->validate($url);
    }

    public static function blockedUrls(): array
    {
        return [
            'no scheme' => ['example.com/recipe'],
            'file scheme' => ['file:///etc/passwd'],
            'gopher scheme' => ['gopher://example.com/'],
            'javascript scheme' => ['javascript:alert(1)'],
            'ftp scheme' => ['ftp://example.com/recipe'],
            'localhost by name' => ['http://localhost/admin'],
            'loopback v4' => ['http://127.0.0.1/'],
            'loopback alternate' => ['http://127.1.2.3/'],
            'loopback v6' => ['http://[::1]/'],
            'ipv4 mapped loopback' => ['http://[::ffff:127.0.0.1]/'],
            'private 10/8' => ['http://10.0.0.5/'],
            'private 172.16/12' => ['http://172.16.4.4/'],
            'private 192.168/16' => ['http://192.168.1.10/'],
            'link local' => ['http://169.254.169.254/latest/meta-data/'],
            'cgnat' => ['http://100.64.0.1/'],
            'unspecified' => ['http://0.0.0.0/'],
            'multicast' => ['http://224.0.0.1/'],
            'reserved' => ['http://240.0.0.1/'],
            'unique local v6' => ['http://[fd00::1]/'],
            'link local v6' => ['http://[fe80::1]/'],
            'gcp metadata name' => ['http://metadata.google.internal/'],
            'internal tld' => ['http://nas.internal/'],
            'lan tld' => ['http://printer.lan/'],
            'mdns tld' => ['http://server.local/'],
            'credentials in authority' => ['http://user:pass@example.com/'],
            'non web port' => ['http://example.com:22/'],
            'empty' => [''],
        ];
    }

    #[Test]
    public function it_recognises_public_addresses(): void
    {
        $this->assertTrue($this->guard->isPublicAddress('93.184.216.34'));
        $this->assertTrue($this->guard->isPublicAddress('2606:2800:220:1:248:1893:25c8:1946'));

        $this->assertFalse($this->guard->isPublicAddress('127.0.0.1'));
        $this->assertFalse($this->guard->isPublicAddress('10.1.1.1'));
        $this->assertFalse($this->guard->isPublicAddress('::1'));
        $this->assertFalse($this->guard->isPublicAddress('not-an-ip'));
    }

    #[Test]
    public function it_resolves_redirect_targets_against_their_source(): void
    {
        $base = 'https://example.com/recipes/oats?page=2';

        $this->assertSame('https://example.com/other', $this->guard->absolutise('/other', $base));
        $this->assertSame('https://example.com/recipes/next', $this->guard->absolutise('next', $base));
        $this->assertSame('https://cdn.example.com/x', $this->guard->absolutise('//cdn.example.com/x', $base));
        $this->assertSame('https://elsewhere.test/x', $this->guard->absolutise('https://elsewhere.test/x', $base));
    }

    #[Test]
    public function a_redirect_to_a_private_address_is_still_refused(): void
    {
        $this->expectException(UnsafeUrlException::class);

        $this->guard->validateRedirect('http://169.254.169.254/', 'https://example.com/recipe');
    }

    #[Test]
    public function the_development_escape_hatch_only_applies_when_it_is_enabled(): void
    {
        $permissive = new UrlGuard(allowPrivateNetworks: true);

        $safe = $permissive->validate('http://127.0.0.1:8080/recipe');
        $this->assertSame('127.0.0.1', $safe->host);

        // With pinning disabled there is nothing to pin, which is exactly why
        // this must never be switched on in production.
        $this->assertSame([], $safe->resolveEntries());
    }
}
