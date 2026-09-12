<?php

declare(strict_types=1);

namespace App\Services\Http;

use App\Exceptions\FetchFailedException;
use App\Exceptions\UnsafeUrlException;

/**
 * The seam through which this application reaches the open internet.
 *
 * Everything that fetches an administrator-supplied URL depends on this
 * interface, and the container binds it to SafeHttpClient — the one
 * implementation, which validates every hop against the SSRF guard. Having it
 * as an interface makes the boundary explicit, and lets tests exercise the
 * parsers against fixed pages without a network.
 */
interface UrlFetcher
{
    /**
     * @throws UnsafeUrlException|FetchFailedException
     */
    public function fetch(string $url, ?int $maxBytes = null, ?string $acceptHeader = null): FetchedResource;

    /**
     * @throws UnsafeUrlException|FetchFailedException
     */
    public function fetchHtml(string $url): FetchedResource;

    /**
     * @throws UnsafeUrlException|FetchFailedException
     */
    public function fetchImage(string $url, int $maxBytes = 15728640): FetchedResource;
}
