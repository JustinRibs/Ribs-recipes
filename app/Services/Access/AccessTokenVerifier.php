<?php

declare(strict_types=1);

namespace App\Services\Access;

use App\Exceptions\AccessDeniedException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifies the Cloudflare Access application token.
 *
 * The presence of an email header proves nothing — headers are trivially
 * forged by anything that can reach the container. What is trustworthy is the
 * JWT in `Cf-Access-Jwt-Assertion`, signed by the team's Access keys. This
 * class checks, in order:
 *
 *   1. the signature, against the team's published JWKS (RS256),
 *   2. the issuer, which must be exactly https://<team-domain>,
 *   3. the audience, which must contain the configured application AUD,
 *   4. expiry / not-before, with a small configurable clock skew.
 *
 * A failure at any step is a denial. There is no "well, the header looked
 * fine" path, and no production fallback.
 */
final class AccessTokenVerifier
{
    private const JWKS_CACHE_KEY = 'ribs.cf-access.jwks';

    public function isConfigured(): bool
    {
        return filled(config('ribs.access.team_domain')) && filled(config('ribs.access.aud'));
    }

    /**
     * @throws AccessDeniedException
     */
    public function verify(string $token): AccessIdentity
    {
        if (! $this->isConfigured()) {
            throw new AccessDeniedException('Cloudflare Access is not configured on this installation.');
        }

        JWT::$leeway = (int) config('ribs.access.leeway', 30);

        try {
            $payload = JWT::decode($token, $this->keys());
        } catch (\Throwable $e) {
            $this->deny('signature or claims rejected', $e->getMessage());
        }

        $claims = (array) $payload;

        $this->assertIssuer($claims);
        $this->assertAudience($claims);

        $email = $this->extractEmail($claims);

        if ($email === null) {
            $this->deny('token carried no identity', 'missing email claim');
        }

        return new AccessIdentity(
            email: $email,
            name: isset($claims['name']) && is_string($claims['name']) ? $claims['name'] : null,
            subject: isset($claims['sub']) && is_string($claims['sub']) ? $claims['sub'] : null,
        );
    }

    /**
     * Cloudflare's signing keys, cached so admin page loads do not each make an
     * outbound HTTPS round trip.
     *
     * @return array<string, Key>
     */
    private function keys(): array
    {
        $ttl = (int) config('ribs.access.jwks_ttl', 3600);

        $jwks = Cache::remember(
            self::JWKS_CACHE_KEY,
            $ttl,
            fn (): array => $this->downloadJwks()
        );

        try {
            return JWK::parseKeySet($jwks, 'RS256');
        } catch (\Throwable $e) {
            // A cached-but-unusable key set would lock the admin out until the
            // TTL expired; drop it so the next attempt re-fetches.
            Cache::forget(self::JWKS_CACHE_KEY);

            $this->deny('key set could not be parsed', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function downloadJwks(): array
    {
        $url = $this->issuer().'/cdn-cgi/access/certs';

        $response = Http::timeout(8)->retry(2, 200)->acceptJson()->get($url);

        if (! $response->successful()) {
            $this->deny('key set unavailable', 'HTTP '.$response->status().' from Cloudflare');
        }

        $body = $response->json();

        if (! is_array($body) || ! isset($body['keys']) || ! is_array($body['keys']) || $body['keys'] === []) {
            $this->deny('key set unavailable', 'malformed JWKS document');
        }

        return $body;
    }

    private function issuer(): string
    {
        $domain = trim((string) config('ribs.access.team_domain'));
        $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;

        return 'https://'.rtrim($domain, '/');
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function assertIssuer(array $claims): void
    {
        $issuer = isset($claims['iss']) && is_string($claims['iss']) ? rtrim($claims['iss'], '/') : '';

        if (! hash_equals($this->issuer(), $issuer)) {
            $this->deny('issuer mismatch', 'token was issued by a different Access team');
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function assertAudience(array $claims): void
    {
        $expected = (string) config('ribs.access.aud');
        $audience = $claims['aud'] ?? null;
        $audiences = is_array($audience) ? $audience : [$audience];

        foreach ($audiences as $candidate) {
            if (is_string($candidate) && hash_equals($expected, $candidate)) {
                return;
            }
        }

        $this->deny('audience mismatch', 'token was issued for a different Access application');
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function extractEmail(array $claims): ?string
    {
        $candidates = [$claims['email'] ?? null];

        // Service tokens and some IdP configurations nest the identity.
        if (isset($claims['identity']) && is_array($claims['identity'])) {
            $candidates[] = $claims['identity']['email'] ?? null;
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && filter_var($candidate, FILTER_VALIDATE_EMAIL) !== false) {
                return strtolower(trim($candidate));
            }
        }

        return null;
    }

    /**
     * @return never
     *
     * @throws AccessDeniedException
     */
    private function deny(string $reason, string $detail): void
    {
        // The token itself is never logged — only why it was refused.
        Log::channel('access')->warning('Cloudflare Access token rejected', [
            'reason' => $reason,
            'detail' => $detail,
        ]);

        throw new AccessDeniedException('Your Cloudflare Access session could not be verified.');
    }
}
