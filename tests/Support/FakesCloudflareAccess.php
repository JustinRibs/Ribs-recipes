<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Http\Middleware\EnsureCloudflareAccess;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;

/**
 * Issues real Cloudflare Access tokens for tests.
 *
 * A locally generated RSA key pair signs the tokens and its public half is
 * planted in the JWKS cache, so the middleware performs its genuine signature,
 * issuer, audience and expiry checks — the tests exercise the actual
 * verification path rather than stubbing past it.
 */
trait FakesCloudflareAccess
{
    private static ?array $keyPair = null;

    /**
     * @return array{0: \OpenSSLAsymmetricKey, 1: string} private key, kid
     */
    private function accessKeyPair(): array
    {
        if (self::$keyPair === null) {
            $resource = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);

            if ($resource === false) {
                $this->fail('Could not generate an RSA key pair for the Access token tests.');
            }

            self::$keyPair = [$resource, 'test-key-1'];
        }

        return self::$keyPair;
    }

    /**
     * Publish the matching public key where the verifier will look for it.
     */
    private function publishJwks(): void
    {
        [$privateKey, $kid] = $this->accessKeyPair();

        $details = openssl_pkey_get_details($privateKey);

        Cache::forever('ribs.cf-access.jwks', [
            'keys' => [[
                'kty' => 'RSA',
                'use' => 'sig',
                'alg' => 'RS256',
                'kid' => $kid,
                'n' => $this->base64Url($details['rsa']['n']),
                'e' => $this->base64Url($details['rsa']['e']),
            ]],
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function accessToken(array $overrides = []): string
    {
        [$privateKey, $kid] = $this->accessKeyPair();
        $this->publishJwks();

        $claims = array_merge([
            'iss' => 'https://'.config('ribs.access.team_domain'),
            'aud' => [config('ribs.access.aud')],
            'email' => 'owner@example.test',
            'sub' => 'test-subject',
            'iat' => time() - 60,
            'exp' => time() + 3600,
        ], $overrides);

        return JWT::encode($claims, $privateKey, 'RS256', $kid);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, string>
     */
    private function accessHeaders(array $overrides = []): array
    {
        return [EnsureCloudflareAccess::HEADER => $this->accessToken($overrides)];
    }

    private function base64Url(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
