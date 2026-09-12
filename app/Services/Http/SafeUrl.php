<?php

declare(strict_types=1);

namespace App\Services\Http;

/**
 * A URL that has passed SSRF validation, together with the exact addresses it
 * resolved to at validation time.
 */
final readonly class SafeUrl
{
    /**
     * @param  list<string>  $addresses
     */
    public function __construct(
        public string $url,
        public string $host,
        public int $port,
        public string $scheme,
        public array $addresses,
    ) {}

    /**
     * cURL CURLOPT_RESOLVE entries pinning this host to the validated
     * addresses, so the connection cannot be re-pointed between the DNS check
     * and the request itself.
     *
     * @return list<string>
     */
    public function resolveEntries(): array
    {
        if ($this->addresses === []) {
            return [];
        }

        return [sprintf('%s:%d:%s', $this->host, $this->port, implode(',', $this->addresses))];
    }
}
