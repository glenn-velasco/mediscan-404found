<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @if (config('services.google.analytics_id') && app()->environment('production'))
            {{-- Google tag (gtag.js) --}}
            <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.google.analytics_id') }}"></script>
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag() { dataLayer.push(arguments); }
                gtag('js', new Date());
                gtag('config', '{{ config('services.google.analytics_id') }}');
            </script>
        @endif

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        {{--
            Inertia SSR isn't actually running in production (ssr.enabled is
            true in config, but no SSR server is deployed - it silently
            falls back to client-only rendering), so tags declared via a
            page's <Head> component never make it into the raw HTML that
            crawlers and non-JS link-preview bots (Slack, Twitter, etc.)
            see. These defaults render server-side unconditionally and get
            transparently replaced by the matching page-level <Head> tags
            once the client hydrates - same mechanism the plain <title>
            fallback below has always relied on.
        --}}
        @php $seo = $page['props']['seo'] ?? null; @endphp
        <x-inertia::head>
            <title>{{ $seo ? $seo['title'].' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}</title>
            @if ($seo)
                @php $baseUrl = rtrim(config('app.url'), '/'); @endphp
                <meta name="description" content="{{ $seo['description'] }}">
                <link rel="canonical" href="{{ $baseUrl.$seo['path'] }}">

                <meta property="og:type" content="website">
                <meta property="og:site_name" content="{{ config('app.name') }}">
                <meta property="og:title" content="{{ $seo['title'] }}">
                <meta property="og:description" content="{{ $seo['description'] }}">
                <meta property="og:url" content="{{ $baseUrl.$seo['path'] }}">
                <meta property="og:image" content="{{ $baseUrl.$seo['image'] }}">

                <meta name="twitter:card" content="summary_large_image">
                <meta name="twitter:title" content="{{ $seo['title'] }}">
                <meta name="twitter:description" content="{{ $seo['description'] }}">
                <meta name="twitter:image" content="{{ $baseUrl.$seo['image'] }}">

                <script type="application/ld+json">{!! json_encode([
                    '@@context' => 'https://schema.org',
                    '@graph' => [
                        ['@type' => 'Organization', 'name' => config('app.name'), 'url' => $baseUrl, 'logo' => $baseUrl.$seo['image']],
                        ['@type' => 'WebSite', 'name' => config('app.name'), 'url' => $baseUrl],
                    ],
                ]) !!}</script>
            @endif
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
