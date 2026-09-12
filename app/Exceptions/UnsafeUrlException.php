<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a URL supplied to a server-side fetcher fails validation.
 *
 * The message is safe to show an administrator: it never contains resolved
 * internal addresses, only the reason the URL was rejected.
 */
class UnsafeUrlException extends RuntimeException {}
