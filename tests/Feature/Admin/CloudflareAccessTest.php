<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Http\Middleware\EnsureCloudflareAccess;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakesCloudflareAccess;
use Tests\TestCase;

/**
 * The admin's entire security model in one file.
 *
 * The tokens here are real: signed with a generated RSA key whose public half
 * is planted in the JWKS cache, so the middleware runs its genuine signature,
 * issuer, audience and expiry checks. Nothing is stubbed past.
 */
class CloudflareAccessTest extends TestCase
{
    use FakesCloudflareAccess;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::create(['name' => 'Owner', 'email' => 'owner@example.test', 'role' => UserRole::Owner]);
    }

    #[Test]
    #[DataProvider('adminRoutes')]
    public function admin_routes_refuse_requests_with_no_token(string $method, string $url): void
    {
        // 403 even for ids that do not exist: the Access check runs before
        // route-model binding, so nothing about the collection is revealed.
        $this->call($method, $url)->assertForbidden();
    }

    public static function adminRoutes(): array
    {
        return [
            'dashboard' => ['GET', '/admin'],
            'recipe list' => ['GET', '/admin/recipes'],
            'recipe create form' => ['GET', '/admin/recipes/create'],
            'recipe store' => ['POST', '/admin/recipes'],
            'recipe update' => ['PUT', '/admin/recipes/1'],
            'recipe delete' => ['DELETE', '/admin/recipes/1'],
            'recipe duplicate' => ['POST', '/admin/recipes/1/duplicate'],
            'recipe favorite' => ['POST', '/admin/recipes/1/favorite'],
            'recipe publish' => ['POST', '/admin/recipes/1/publish'],
            'categories' => ['GET', '/admin/categories'],
            'category store' => ['POST', '/admin/categories'],
            'category delete' => ['DELETE', '/admin/categories/1'],
            'tags' => ['GET', '/admin/tags'],
            'tag store' => ['POST', '/admin/tags'],
            'media upload' => ['POST', '/admin/media'],
            'media remote' => ['POST', '/admin/media/remote'],
            'media delete' => ['DELETE', '/admin/media/1'],
            'url import' => ['GET', '/admin/import/url'],
            'url import run' => ['POST', '/admin/import/url'],
            'csv import' => ['GET', '/admin/import/csv'],
            'csv preview' => ['POST', '/admin/import/csv/preview'],
            'csv store' => ['POST', '/admin/import/csv'],
            'csv template' => ['GET', '/admin/import/csv/template'],
            'settings' => ['GET', '/admin/settings'],
            'settings update' => ['PUT', '/admin/settings'],
            'reindex' => ['POST', '/admin/settings/reindex'],
        ];
    }

    #[Test]
    public function a_valid_token_is_admitted(): void
    {
        $this->withHeaders($this->accessHeaders())
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Dashboard')
                ->where('auth.email', 'owner@example.test')
            );
    }

    #[Test]
    public function the_token_may_also_arrive_as_the_cloudflare_cookie(): void
    {
        $this->withUnencryptedCookie('CF_Authorization', $this->accessToken())
            ->get('/admin')
            ->assertOk();
    }

    #[Test]
    public function an_unsigned_email_header_alone_proves_nothing(): void
    {
        // The classic mistake: trusting Cf-Access-Authenticated-User-Email,
        // which anything able to reach the container can simply set.
        $this->withHeaders(['Cf-Access-Authenticated-User-Email' => 'owner@example.test'])
            ->get('/admin')
            ->assertForbidden();
    }

    #[Test]
    public function a_malformed_token_is_rejected(): void
    {
        foreach (['not-a-jwt', 'a.b.c', base64_encode('{"alg":"none"}').'..', ''] as $token) {
            $this->withHeaders([EnsureCloudflareAccess::HEADER => $token])
                ->get('/admin')
                ->assertForbidden();
        }
    }

    #[Test]
    public function a_token_signed_by_a_different_key_is_rejected(): void
    {
        $this->publishJwks();

        $imposter = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $token = JWT::encode([
            'iss' => 'https://'.config('ribs.access.team_domain'),
            'aud' => [config('ribs.access.aud')],
            'email' => 'owner@example.test',
            'exp' => time() + 3600,
        ], $imposter, 'RS256', 'test-key-1');

        $this->withHeaders([EnsureCloudflareAccess::HEADER => $token])
            ->get('/admin')
            ->assertForbidden();
    }

    #[Test]
    public function an_expired_token_is_rejected(): void
    {
        $this->withHeaders($this->accessHeaders(['exp' => time() - 3600, 'iat' => time() - 7200]))
            ->get('/admin')
            ->assertForbidden();
    }

    #[Test]
    public function a_token_from_another_access_team_is_rejected(): void
    {
        $this->withHeaders($this->accessHeaders(['iss' => 'https://someone-else.cloudflareaccess.com']))
            ->get('/admin')
            ->assertForbidden();
    }

    #[Test]
    public function a_token_for_another_application_is_rejected(): void
    {
        $this->withHeaders($this->accessHeaders(['aud' => ['a-different-application']]))
            ->get('/admin')
            ->assertForbidden();
    }

    #[Test]
    public function a_token_with_no_identity_is_rejected(): void
    {
        $this->withHeaders($this->accessHeaders(['email' => null]))
            ->get('/admin')
            ->assertForbidden();
    }

    #[Test]
    public function a_verified_identity_with_no_matching_user_is_refused(): void
    {
        config()->set('ribs.admin.auto_provision', false);
        config()->set('ribs.admin.email', 'owner@example.test');

        $this->withHeaders($this->accessHeaders(['email' => 'stranger@example.test']))
            ->get('/admin')
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'stranger@example.test']);
    }

    #[Test]
    public function auto_provisioning_creates_a_contributor_when_it_is_enabled(): void
    {
        config()->set('ribs.admin.auto_provision', true);

        $this->withHeaders($this->accessHeaders(['email' => 'family@example.test']))
            ->get('/admin')
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'family@example.test',
            'role' => UserRole::Contributor->value,
        ]);
    }

    #[Test]
    public function the_configured_owner_is_provisioned_on_first_sign_in(): void
    {
        User::query()->delete();
        config()->set('ribs.admin.email', 'owner@example.test');

        $this->withHeaders($this->accessHeaders())->get('/admin')->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'owner@example.test',
            'role' => UserRole::Owner->value,
        ]);
    }

    #[Test]
    public function the_development_bypass_is_ignored_outside_development(): void
    {
        config()->set('ribs.access.dev_bypass', true);
        // The environment gate is what actually matters: even with the flag on,
        // an environment that is not in the allow-list gets no bypass.
        config()->set('ribs.access.dev_environments', ['local']);

        $this->assertSame('testing', app()->environment());

        $this->get('/admin')->assertForbidden();
    }

    #[Test]
    public function the_development_bypass_works_when_the_environment_allows_it(): void
    {
        config()->set('ribs.access.dev_bypass', true);
        config()->set('ribs.access.dev_environments', ['testing']);
        config()->set('ribs.access.dev_email', 'owner@example.test');

        $this->get('/admin')->assertOk();
    }

    #[Test]
    public function admin_responses_are_never_cached_or_indexed(): void
    {
        $response = $this->withHeaders($this->accessHeaders())->get('/admin')->assertOk();

        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
    }

    #[Test]
    public function a_refused_admin_request_still_carries_the_security_headers(): void
    {
        // A thrown exception unwinds past the middleware that normally adds
        // these, so the 403 is the case most likely to go out bare.
        $response = $this->get('/admin')->assertForbidden();

        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
    }

    #[Test]
    public function the_token_is_never_written_to_the_logs(): void
    {
        // Well past the configured clock-skew allowance, so it is genuinely
        // rejected rather than leniently accepted.
        $token = $this->accessToken(['exp' => time() - 600, 'iat' => time() - 1200]);

        $logFile = storage_path('logs/access-'.now()->format('Y-m-d').'.log');
        @unlink($logFile);

        $this->withHeaders([EnsureCloudflareAccess::HEADER => $token])->get('/admin')->assertForbidden();

        if (file_exists($logFile)) {
            $this->assertStringNotContainsString($token, file_get_contents($logFile));
            @unlink($logFile);
        }
    }
}
