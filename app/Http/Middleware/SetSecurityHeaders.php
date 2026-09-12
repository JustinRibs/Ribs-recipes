<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\SecurityHeaders;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        return SecurityHeaders::apply($request, $next($request));
    }
}
