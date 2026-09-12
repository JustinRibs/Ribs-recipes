<?php

declare(strict_types=1);

namespace App\Services\Http;

use App\Exceptions\UnsafeUrlException;

/**
 * SSRF protection for server-side URL fetching.
 *
 * Everything the recipe importer and the remote-image downloader touch passes
 * through here first. A URL is only accepted when:
 *
 *   - the scheme is http or https,
 *   - there are no credentials embedded in the authority,
 *   - the port is a normal web port,
 *   - the host resolves, and *every* address it resolves to is publicly
 *     routable — loopback, private, link-local, CGNAT, multicast and reserved
 *     space are all rejected, for both IPv4 and IPv6 (including IPv4-mapped
 *     IPv6 addresses, which are a classic bypass).
 *
 * The resolved addresses are returned so the caller can pin the connection to
 * them with CURLOPT_RESOLVE. That pinning is what closes the DNS rebinding
 * window: without it, a hostname can resolve to a public address during
 * validation and to 127.0.0.1 microseconds later when cURL resolves it again.
 */
final class UrlGuard
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    private const ALLOWED_PORTS = [80, 443, 8080, 8443];

    public function __construct(private readonly bool $allowPrivateNetworks = false) {}

    public static function fromConfig(): self
    {
        return new self((bool) config('ribs.fetch.allow_private_networks', false));
    }

    /**
     * @throws UnsafeUrlException
     */
    public function validate(string $url): SafeUrl
    {
        $url = trim($url);

        if ($url === '' || mb_strlen($url) > 2048) {
            throw new UnsafeUrlException('That does not look like a usable URL.');
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new UnsafeUrlException('That URL could not be parsed. Include the full address, starting with https://.');
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new UnsafeUrlException('Only http and https addresses can be fetched.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafeUrlException('URLs containing credentials are not allowed.');
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        if (! in_array($port, self::ALLOWED_PORTS, true)) {
            throw new UnsafeUrlException('Only standard web ports can be fetched.');
        }

        $host = $this->normaliseHost($parts['host']);

        if ($host === '') {
            throw new UnsafeUrlException('That URL has no host.');
        }

        if ($this->allowPrivateNetworks) {
            // Development escape hatch. Never enabled in production.
            return new SafeUrl($url, $host, $port, $scheme, []);
        }

        if ($this->isBlockedHostname($host)) {
            throw new UnsafeUrlException('That host is not publicly reachable.');
        }

        $addresses = $this->resolve($host);

        if ($addresses === []) {
            throw new UnsafeUrlException('That host could not be resolved.');
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicAddress($address)) {
                throw new UnsafeUrlException('That host resolves to a private or reserved address.');
            }
        }

        return new SafeUrl($url, $host, $port, $scheme, $addresses);
    }

    /**
     * A cheap, DNS-free sanity check.
     *
     * Used where a URL is only going to be *shown* — the importer's preview
     * offers the source image, but nothing is fetched until an administrator
     * chooses to, and that fetch goes through validate() in full. Doing a DNS
     * lookup here would mean a slow import and a hero image silently dropped
     * whenever resolution happened to be slow.
     */
    public function isPlausible(string $url): bool
    {
        $parts = parse_url(trim($url));

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (! in_array(strtolower($parts['scheme']), self::ALLOWED_SCHEMES, true)) {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = $this->normaliseHost($parts['host']);

        if ($host === '') {
            return false;
        }

        // The development escape hatch applies here too, so the importer's
        // behaviour is consistent with what it will actually fetch.
        if ($this->allowPrivateNetworks) {
            return true;
        }

        if ($this->isBlockedHostname($host)) {
            return false;
        }

        // A literal address can be judged without asking a resolver.
        return filter_var($host, FILTER_VALIDATE_IP) === false || $this->isPublicAddress($host);
    }

    /**
     * Resolve a redirect target against its source and validate it.
     *
     * @throws UnsafeUrlException
     */
    public function validateRedirect(string $location, string $base): SafeUrl
    {
        return $this->validate($this->absolutise($location, $base));
    }

    public function absolutise(string $location, string $base): string
    {
        $location = trim($location);

        if ($location === '') {
            return $location;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $location) === 1) {
            return $location;
        }

        $parts = parse_url($base);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return $location;
        }

        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($location, '//')) {
            return $parts['scheme'].':'.$location;
        }

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $directory = rtrim(dirname($parts['path'] ?? '/'), '/');

        return $origin.$directory.'/'.$location;
    }

    private function normaliseHost(string $host): string
    {
        $host = trim(strtolower($host), " \t\n\r\0\x0B.");
        $host = trim($host, '[]');

        if ($host !== '' && function_exists('idn_to_ascii')) {
            // Reject homograph tricks by normalising to punycode up front.
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            if (is_string($ascii) && $ascii !== '') {
                $host = $ascii;
            }
        }

        return $host;
    }

    private function isBlockedHostname(string $host): bool
    {
        // Names that never point anywhere useful on a public recipe site, and
        // that short-circuit ahead of DNS so nothing is even looked up.
        $blocked = ['localhost', 'localhost.localdomain', 'ip6-localhost', 'ip6-loopback'];

        if (in_array($host, $blocked, true)) {
            return true;
        }

        foreach (['.localhost', '.local', '.internal', '.intranet', '.lan', '.home.arpa'] as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        // Cloud instance metadata services.
        return in_array($host, ['metadata.google.internal', 'metadata.goog', 'instance-data'], true);
    }

    /**
     * @return list<string>
     */
    private function resolve(string $host): array
    {
        // A literal address needs no lookup — but still needs validating.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $addresses = [];

        $v4 = @gethostbynamel($host);

        if (is_array($v4)) {
            $addresses = $v4;
        }

        $records = @dns_get_record($host, DNS_AAAA);

        if (is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($addresses));
    }

    public function isPublicAddress(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        // ::ffff:127.0.0.1 and friends must be judged as the IPv4 they embed.
        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $address, $m) === 1) {
            $address = $m[1];
        }

        $isPrivateOrReserved = filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;

        if ($isPrivateOrReserved) {
            return false;
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $this->isPublicIpv4($address);
        }

        return $this->isPublicIpv6($address);
    }

    private function isPublicIpv4(string $address): bool
    {
        $long = ip2long($address);

        if ($long === false) {
            return false;
        }

        // Ranges PHP's reserved-range filter does not cover.
        $blocks = [
            ['100.64.0.0', 10],   // CGNAT
            ['192.0.0.0', 24],    // IETF protocol assignments
            ['192.0.2.0', 24],    // TEST-NET-1
            ['192.88.99.0', 24],  // 6to4 relay anycast
            ['198.18.0.0', 15],   // benchmarking
            ['198.51.100.0', 24], // TEST-NET-2
            ['203.0.113.0', 24],  // TEST-NET-3
            ['224.0.0.0', 4],     // multicast
            ['240.0.0.0', 4],     // reserved / broadcast
        ];

        foreach ($blocks as [$network, $bits]) {
            $mask = -1 << (32 - $bits);

            if ((ip2long($network) & $mask) === ($long & $mask)) {
                return false;
            }
        }

        return true;
    }

    private function isPublicIpv6(string $address): bool
    {
        $packed = @inet_pton($address);

        if ($packed === false) {
            return false;
        }

        $blocks = [
            ['::', 128],          // unspecified
            ['::1', 128],         // loopback
            ['64:ff9b::', 96],    // NAT64
            ['100::', 64],        // discard-only
            ['2001:db8::', 32],   // documentation
            ['fc00::', 7],        // unique local
            ['fe80::', 10],       // link local
            ['ff00::', 8],        // multicast
        ];

        foreach ($blocks as [$network, $bits]) {
            if ($this->ipv6InNetwork($packed, (string) inet_pton($network), $bits)) {
                return false;
            }
        }

        return true;
    }

    private function ipv6InNetwork(string $address, string $network, int $bits): bool
    {
        $wholeBytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if ($wholeBytes > 0 && substr($address, 0, $wholeBytes) !== substr($network, 0, $wholeBytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = 0xFF << (8 - $remainder) & 0xFF;

        return (ord($address[$wholeBytes]) & $mask) === (ord($network[$wholeBytes]) & $mask);
    }
}
