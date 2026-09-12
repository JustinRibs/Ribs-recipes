<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\AccessDeniedException;
use App\Services\Access\AccessIdentity;
use App\Services\Access\AccessTokenVerifier;
use App\Services\Access\AdminUserResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * The security boundary for every /admin route.
 *
 * Requests must carry a Cloudflare Access application token that this
 * application independently verifies. The `Cf-Access-Authenticated-User-Email`
 * header is deliberately ignored: it is not signed, and anything able to reach
 * the container directly could set it.
 *
 * A development bypass exists, but it is gated on APP_ENV being one of the
 * environments listed in config('ribs.access.dev_environments'). In production
 * a missing or invalid token is always a 403 — there is no fallback path that
 * can leave the admin area open.
 */
class EnsureCloudflareAccess
{
    public const HEADER = 'Cf-Access-Jwt-Assertion';

    /** Request attribute holding the resolved App\Models\User. */
    public const USER_ATTRIBUTE = 'ribs.admin_user';

    public function __construct(
        private readonly AccessTokenVerifier $verifier,
        private readonly AdminUserResolver $users,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $identity = $this->identify($request);
        $user = $this->users->resolve($identity);

        $request->attributes->set(self::USER_ATTRIBUTE, $user);

        // Lets policies, gates and `$request->user()` work normally without a
        // login system. Nothing is written to the session: the identity is
        // re-derived from the Access token on every single request.
        $request->setUserResolver(fn () => $user);
        Auth::setUser($user);

        return $next($request);
    }

    /**
     * @throws AccessDeniedException
     */
    private function identify(Request $request): AccessIdentity
    {
        if ($this->devBypassAllowed()) {
            return new AccessIdentity(
                email: (string) config('ribs.access.dev_email'),
                name: (string) config('ribs.admin.name'),
                viaDevBypass: true,
            );
        }

        $token = $this->token($request);

        if ($token === null) {
            Log::channel('access')->warning('Admin request without an Access token', [
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            throw new AccessDeniedException(
                'This area is protected by Cloudflare Access. Open it through https://'
                .$request->getHost().'/admin so Cloudflare can sign you in.'
            );
        }

        return $this->verifier->verify($token);
    }

    /**
     * Cloudflare sends the token as a header, and also as a cookie on the
     * protected hostname. The header is preferred; the cookie is the fallback
     * for clients that strip unknown headers.
     */
    private function token(Request $request): ?string
    {
        $header = $request->header(self::HEADER);

        if (is_string($header) && trim($header) !== '') {
            return trim($header);
        }

        $cookie = $request->cookie('CF_Authorization');

        return is_string($cookie) && trim($cookie) !== '' ? trim($cookie) : null;
    }

    private function devBypassAllowed(): bool
    {
        if (config('ribs.access.dev_bypass') !== true) {
            return false;
        }

        $environments = (array) config('ribs.access.dev_environments', []);

        // The environment check is the hard gate. ADMIN_DEV_BYPASS=true in a
        // production .env does nothing at all.
        return app()->environment($environments);
    }
}
