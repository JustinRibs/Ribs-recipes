<?php

use App\Exceptions\AccessDeniedException;
use App\Http\Middleware\EnsureCloudflareAccess;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetSecurityHeaders;
use App\Http\Responses\ErrorPageResponder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')
                ->prefix('admin')
                ->name('admin.')
                ->group(__DIR__.'/../routes/admin.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Traffic arrives as Cloudflare -> Tunnel -> Traefik -> this container,
        // so the proxy headers are what carry the real scheme and client IP.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SetSecurityHeaders::class,
        ]);

        // Cloudflare sets CF_Authorization itself, so Laravel must not try to
        // decrypt it — without this exception the cookie fallback used by
        // clients that strip unknown headers would always arrive empty.
        $middleware->encryptCookies(except: ['CF_Authorization']);

        $middleware->alias([
            'cloudflare.access' => EnsureCloudflareAccess::class,
        ]);

        // Authorise before resolving route bindings. Otherwise an
        // unauthenticated request to /admin/recipes/42 gets a 404 or a 200
        // depending on whether recipe 42 exists, which tells an anonymous
        // visitor something about the collection before Access has run.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: EnsureCloudflareAccess::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson() && ! $request->header('X-Inertia'),
        );

        $exceptions->render(function (AccessDeniedException $e) {
            abort(403, $e->getMessage());
        });

        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            return app(ErrorPageResponder::class)($response, $e, $request);
        });
    })->create();
