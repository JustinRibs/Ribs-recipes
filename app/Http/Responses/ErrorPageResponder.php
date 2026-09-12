<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Http\Middleware\HandleInertiaRequests;
use App\Support\SecurityHeaders;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Renders branded error pages through Inertia so a 404 still looks like Ribs
 * Recipes rather than a bare framework stack.
 *
 * Validation errors (422) and Inertia's own redirect conventions are left
 * alone; only genuine error screens are taken over. Messages are only passed
 * through when the framework author set them deliberately — never exception
 * text from an unexpected failure, which could leak internals.
 */
final class ErrorPageResponder
{
    /** Statuses that get a designed page rather than the default response. */
    private const HANDLED = [403, 404, 419, 429, 500, 503];

    /** Page titles, matching the headings the React page renders. */
    private const TITLES = [
        403 => 'Not your kitchen',
        404 => 'Page not found',
        419 => 'Page expired',
        429 => 'Too many requests',
        500 => 'Something went wrong',
        503 => 'Back shortly',
    ];

    public function __invoke(Response $response, \Throwable $e, Request $request): Response
    {
        // An exception unwinds past the middleware that would normally add
        // these, so they are applied to every error response here instead.
        SecurityHeaders::apply($request, $response);

        $status = $response->getStatusCode();

        if (! in_array($status, self::HANDLED, true)) {
            return $response;
        }

        // Debug mode should still show Laravel's exception page locally.
        if (config('app.debug') && $status === 500) {
            return $response;
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return $response;
        }

        // An unmatched URL never enters the web middleware group, so Inertia's
        // shared props were never registered. The error page still renders the
        // site chrome, so they are supplied here.
        Inertia::share(app(HandleInertiaRequests::class)->share($request));

        $rendered = Inertia::render('Errors/ErrorPage', [
            'status' => $status,
            'detail' => $this->detail($e, $status),
            'isAdmin' => $request->is('admin', 'admin/*'),
            'meta' => [
                'title' => self::TITLES[$status].' — '.config('app.name'),
                'description' => 'Something went wrong.',
                'noindex' => true,
            ],
        ])
            ->toResponse($request)
            ->setStatusCode($status);

        return SecurityHeaders::apply($request, $rendered);
    }

    private function detail(\Throwable $e, int $status): ?string
    {
        if (! $e instanceof HttpExceptionInterface) {
            return null;
        }

        $message = trim($e->getMessage());

        // Symfony fills in a generic message when abort() was called without
        // one; there is no value in echoing "Forbidden" back at the visitor.
        if ($message === '' || $message === (Response::$statusTexts[$status] ?? '')) {
            return null;
        }

        return $message;
    }
}
