@php
    /**
     * Server-rendered head.
     *
     * Meta tags and Schema.org JSON-LD are emitted here from the Inertia page
     * props rather than injected by React, so crawlers and link unfurlers see
     * a complete document on the first response without needing SSR.
     */
    $props = $page['props'] ?? [];
    $meta = $props['meta'] ?? [];

    $title = $meta['title'] ?? config('app.name');
    $description = $meta['description'] ?? 'A personal recipe collection — good food goes further.';
    $canonical = $meta['canonical'] ?? url()->current();
    $image = $meta['image'] ?? null;
    $noindex = ($meta['noindex'] ?? false) || request()->is('admin', 'admin/*');

    if ($image && ! str_starts_with($image, 'http')) {
        $image = url($image);
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    {{-- Read by the admin's direct fetch() uploads; Inertia adds its own. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags((string) $description), 200) }}">
    <link rel="canonical" href="{{ $canonical }}">

    @if ($noindex)
        <meta name="robots" content="noindex, nofollow">
    @endif

    {{-- Resolve the theme before first paint so there is never a white flash
         on a dark-mode phone. Kept inline and tiny on purpose. --}}
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('ribs:theme');
                var mode = stored === 'light' || stored === 'dark' || stored === 'system' ? stored : 'system';
                var dark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.dataset.theme = dark ? 'dark' : 'light';
                document.documentElement.dataset.themeMode = mode;
            } catch (e) {
                document.documentElement.dataset.theme = 'light';
                document.documentElement.dataset.themeMode = 'system';
            }
        })();
    </script>

    <meta name="theme-color" content="#FBFAF7" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0D1015" media="(prefers-color-scheme: dark)">

    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:type" content="{{ $meta['type'] ?? 'website' }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags((string) $description), 200) }}">
    <meta property="og:url" content="{{ $canonical }}">
    @if ($image)
        <meta property="og:image" content="{{ $image }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:image" content="{{ $image }}">
    @else
        <meta name="twitter:card" content="summary">
    @endif
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ \Illuminate\Support\Str::limit(strip_tags((string) $description), 200) }}">

    {{-- PWA / iOS home screen --}}
    <link rel="manifest" href="{{ route('manifest') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Ribs">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <link rel="icon" href="/icons/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/icons/favicon-32.png" sizes="32x32" type="image/png">
    <link rel="mask-icon" href="/icons/favicon.svg" color="#12263F">

    @if (! empty($meta['jsonLd']))
        <script type="application/ld+json">{!! json_encode($meta['jsonLd'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endif

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="min-h-dvh bg-canvas text-ink antialiased">
    @inertia
</body>
</html>
