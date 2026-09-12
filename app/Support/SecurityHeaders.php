<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The response headers this application always sets.
 *
 * Applied from two places: the normal middleware, and the exception responder.
 * A thrown exception unwinds past the middleware's post-processing, so without
 * the second call a 403 or 500 would go out bare — and /admin's 403 is one of
 * the most common responses the admin area produces.
 *
 * No Content-Security-Policy is set: recipes may legitimately hot-link images
 * from anywhere the author chooses, and a CSP tight enough to be worth having
 * would have to be reopened for `img-src` anyway. These are the headers that
 * cost nothing and always apply.
 */
final class SecurityHeaders
{
    public static function apply(Request $request, Response $response): Response
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=(), interest-cohort=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value, false);
        }

        // Admin responses must never be stored by a shared cache, a service
        // worker, or a search engine.
        if ($request->is('admin', 'admin/*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
