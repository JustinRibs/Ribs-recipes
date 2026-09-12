<?php

declare(strict_types=1);

namespace App\Services\Http;

use App\Exceptions\FetchFailedException;
use App\Exceptions\UnsafeUrlException;
use Illuminate\Support\Facades\Log;

/**
 * The only way this application fetches a user-supplied URL.
 *
 * Redirects are followed manually so that every hop is re-validated by the
 * UrlGuard (cURL's own follower would happily walk from a public URL to
 * http://169.254.169.254/). The response body is capped mid-download by the
 * write callback rather than after the fact, so a hostile server cannot stream
 * gigabytes at the home server.
 */
final class SafeHttpClient implements UrlFetcher
{
    public function __construct(private readonly UrlGuard $guard) {}

    /**
     * @throws UnsafeUrlException|FetchFailedException
     */
    public function fetch(string $url, ?int $maxBytes = null, ?string $acceptHeader = null): FetchedResource
    {
        $maxBytes ??= (int) config('ribs.fetch.max_bytes');
        $maxRedirects = (int) config('ribs.fetch.max_redirects');

        $safe = $this->guard->validate($url);
        $hops = 0;

        while (true) {
            $response = $this->request($safe, $maxBytes, $acceptHeader);

            if (! in_array($response['status'], [301, 302, 303, 307, 308], true)) {
                break;
            }

            if ($hops >= $maxRedirects) {
                throw new FetchFailedException('That page redirected too many times.');
            }

            $location = $response['location'];

            if ($location === null || $location === '') {
                throw new FetchFailedException('That page returned a redirect with no destination.');
            }

            // Re-validate the destination: the guard is applied per hop.
            $safe = $this->guard->validateRedirect($location, $safe->url);
            $hops++;
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new FetchFailedException("That page responded with HTTP {$response['status']}.");
        }

        return new FetchedResource(
            finalUrl: $safe->url,
            body: $response['body'],
            contentType: strtolower(trim(explode(';', $response['content_type'])[0])),
            status: $response['status'],
        );
    }

    /**
     * Fetch a page of HTML for recipe extraction.
     *
     * @throws UnsafeUrlException|FetchFailedException
     */
    public function fetchHtml(string $url): FetchedResource
    {
        $resource = $this->fetch(
            $url,
            acceptHeader: 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.1'
        );

        if (! $resource->isHtml()) {
            throw new FetchFailedException('That address did not return a web page.');
        }

        return $resource;
    }

    /**
     * Fetch image bytes, capped separately from HTML so a hero photo may be
     * larger than a page of markup.
     *
     * @throws UnsafeUrlException|FetchFailedException
     */
    public function fetchImage(string $url, int $maxBytes = 15728640): FetchedResource
    {
        $resource = $this->fetch($url, maxBytes: $maxBytes, acceptHeader: 'image/*');

        if (! $resource->isImage()) {
            throw new FetchFailedException('That address did not return an image.');
        }

        return $resource;
    }

    /**
     * @return array{status:int, body:string, content_type:string, location:string|null}
     *
     * @throws FetchFailedException
     */
    private function request(SafeUrl $safe, int $maxBytes, ?string $acceptHeader): array
    {
        $handle = curl_init();

        if ($handle === false) {
            throw new FetchFailedException('Could not start the request.');
        }

        $body = '';
        $exceeded = false;

        $options = [
            CURLOPT_URL => $safe->url,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            // Redirects are handled by the caller so each hop is re-validated.
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => (int) config('ribs.fetch.connect_timeout', 5),
            CURLOPT_TIMEOUT => (int) config('ribs.fetch.timeout', 12),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => (string) config('ribs.fetch.user_agent'),
            CURLOPT_ACCEPT_ENCODING => '',
            CURLOPT_HTTPHEADER => array_values(array_filter([
                $acceptHeader === null ? null : 'Accept: '.$acceptHeader,
                'Accept-Language: en;q=0.9,*;q=0.5',
            ])),
            CURLOPT_WRITEFUNCTION => function ($_, string $chunk) use (&$body, &$exceeded, $maxBytes): int {
                $body .= $chunk;

                if (strlen($body) > $maxBytes) {
                    $exceeded = true;

                    // Returning a short count aborts the transfer immediately.
                    return 0;
                }

                return strlen($chunk);
            },
        ];

        if (defined('CURLOPT_PROTOCOLS_STR')) {
            $options[CURLOPT_PROTOCOLS_STR] = 'http,https';
            $options[CURLOPT_REDIR_PROTOCOLS_STR] = 'http,https';
        } else {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
            $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }

        // Pin the connection to the addresses the guard already approved.
        $resolve = $safe->resolveEntries();

        if ($resolve !== []) {
            $options[CURLOPT_RESOLVE] = $resolve;
        }

        curl_setopt_array($handle, $options);
        curl_exec($handle);

        $errno = curl_errno($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $contentType = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        $redirectUrl = (string) curl_getinfo($handle, CURLINFO_REDIRECT_URL);
        $error = curl_error($handle);

        curl_close($handle);

        if ($exceeded) {
            throw new FetchFailedException('That page is larger than this importer will download.');
        }

        if ($errno !== 0 && $status === 0) {
            Log::channel('import')->warning('Fetch failed', [
                'host' => $safe->host,
                'curl_errno' => $errno,
            ]);

            throw new FetchFailedException('Could not reach that address: '.$this->friendlyCurlError($errno, $error));
        }

        return [
            'status' => $status,
            'body' => $body,
            'content_type' => $contentType,
            'location' => $redirectUrl !== '' ? $redirectUrl : null,
        ];
    }

    private function friendlyCurlError(int $errno, string $message): string
    {
        return match ($errno) {
            CURLE_OPERATION_TIMEDOUT => 'the request timed out.',
            CURLE_COULDNT_RESOLVE_HOST => 'the host could not be resolved.',
            CURLE_COULDNT_CONNECT => 'the connection was refused.',
            CURLE_SSL_CONNECT_ERROR, CURLE_PEER_FAILED_VERIFICATION => 'the TLS certificate could not be verified.',
            default => $message !== '' ? rtrim($message, '.').'.' : 'the request failed.',
        };
    }
}
