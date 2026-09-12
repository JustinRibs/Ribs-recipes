<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * The PWA manifest is generated rather than static so the app name, colours
 * and start URL always match the running configuration.
 */
class ManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'name' => config('app.name'),
            'short_name' => 'Ribs',
            'description' => 'A personal recipe collection — good food goes further.',
            'start_url' => '/?source=pwa',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'background_color' => '#FBFAF7',
            'theme_color' => '#12263F',
            'lang' => 'en',
            'dir' => 'ltr',
            'categories' => ['food', 'lifestyle'],
            'icons' => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                [
                    'name' => 'Browse recipes',
                    'url' => '/recipes',
                    'icons' => [['src' => '/icons/icon-192.png', 'sizes' => '192x192']],
                ],
            ],
        ], options: JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
