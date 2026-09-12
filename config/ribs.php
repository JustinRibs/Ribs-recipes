<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Zero Trust Access
    |--------------------------------------------------------------------------
    |
    | Every /admin route is protected by Cloudflare Access. The application does
    | not trust the Cf-Access-Authenticated-User-Email header; it verifies the
    | signed JWT in Cf-Access-Jwt-Assertion against Cloudflare's public JWKS.
    |
    */

    'access' => [
        // e.g. "ribarichh.cloudflareaccess.com" (host only, no scheme).
        'team_domain' => env('CLOUDFLARE_ACCESS_TEAM_DOMAIN'),

        // The Application Audience (AUD) tag of the Access application.
        'aud' => env('CLOUDFLARE_ACCESS_AUD'),

        // How long (seconds) to cache Cloudflare's JWKS document.
        'jwks_ttl' => (int) env('CLOUDFLARE_ACCESS_JWKS_TTL', 3600),

        // Permitted clock skew (seconds) for exp / iat / nbf.
        'leeway' => (int) env('CLOUDFLARE_ACCESS_LEEWAY', 30),

        // Environments in which the development bypass may be honoured.
        'dev_environments' => ['local', 'development', 'testing'],

        // Development bypass. Ignored outside the environments listed above.
        'dev_bypass' => (bool) env('ADMIN_DEV_BYPASS', false),
        'dev_email' => env('ADMIN_DEV_EMAIL', 'dev@localhost'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin identity mapping
    |--------------------------------------------------------------------------
    */

    'admin' => [
        'email' => env('ADMIN_EMAIL'),
        'name' => env('ADMIN_NAME', 'Owner'),

        // Auto-create a contributor user for any verified Access identity that
        // has no matching row. Off by default (single-user install).
        'auto_provision' => (bool) env('ADMIN_AUTO_PROVISION', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Images
    |--------------------------------------------------------------------------
    |
    | Uploads are re-encoded (which also strips EXIF/GPS metadata), downscaled
    | and emitted as a small ladder of WebP variants plus a JPEG fallback.
    |
    */

    'images' => [
        'disk' => 'media',
        'driver' => env('IMAGE_DRIVER', 'gd'),
        'max_upload_kb' => (int) env('IMAGE_MAX_UPLOAD_KB', 25600),
        'max_edge' => (int) env('IMAGE_MAX_EDGE', 2400),
        'quality' => (int) env('IMAGE_QUALITY', 82),

        // Widths generated for responsive srcset. Variants larger than the
        // source image are skipped rather than upscaled.
        'widths' => [320, 640, 960, 1440, 2048],

        // Square-ish thumbnail used in admin lists and compact cards.
        'thumb_width' => 240,

        'accepted_mimes' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'image/avif',
            'image/heic',
            'image/heif',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Server-side URL fetching (recipe importer + remote image download)
    |--------------------------------------------------------------------------
    */

    'fetch' => [
        'timeout' => (int) env('IMPORT_FETCH_TIMEOUT', 12),
        'connect_timeout' => 5,
        'max_bytes' => (int) env('IMPORT_FETCH_MAX_BYTES', 3 * 1024 * 1024),
        'max_redirects' => (int) env('IMPORT_FETCH_MAX_REDIRECTS', 3),

        // DANGEROUS. Allows loopback/private targets. Development only.
        'allow_private_networks' => (bool) env('IMPORT_ALLOW_PRIVATE_NETWORKS', false),

        'user_agent' => 'RibsRecipes/1.0 (+https://recipes.ribarichh.com; recipe importer)',
    ],

    /*
    |--------------------------------------------------------------------------
    | CSV import
    |--------------------------------------------------------------------------
    */

    'csv' => [
        'max_rows' => (int) env('CSV_IMPORT_MAX_ROWS', 250),
        'max_upload_kb' => 4096,
    ],

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    |
    | "auto" uses SQLite FTS5 when the virtual table is present, and otherwise
    | falls back to portable indexed LIKE matching (which is also what a future
    | PostgreSQL/MariaDB migration would land on until a driver is added).
    |
    */

    'search' => [
        'driver' => env('SEARCH_DRIVER', 'auto'),
    ],
];
