<?php

declare(strict_types=1);

namespace App\Services\Access;

/**
 * A verified Cloudflare Access identity.
 *
 * Only ever constructed after a JWT has passed signature, issuer, audience and
 * expiry validation — or, in local development, by the explicit bypass.
 */
final readonly class AccessIdentity
{
    public function __construct(
        public string $email,
        public ?string $name = null,
        public ?string $subject = null,
        public bool $viaDevBypass = false,
    ) {}
}
