<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when an /admin request cannot be tied to a verified Cloudflare
 * Access identity. Always surfaces to the visitor as a 403.
 */
class AccessDeniedException extends RuntimeException {}
