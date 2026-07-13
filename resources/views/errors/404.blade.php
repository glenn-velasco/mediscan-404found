<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 - {{ config('app.name', 'Mediscan') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: oklch(1 0 0);
            --fg: oklch(0.145 0 0);
            --muted-bg: oklch(0.97 0 0);
            --muted-fg: oklch(0.556 0 0);
            --border: oklch(0.922 0 0);
            --primary: oklch(0.205 0 0);
            --primary-fg: oklch(0.985 0 0);
            --radius: 0.625rem;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: oklch(0.145 0 0);
                --fg: oklch(0.985 0 0);
                --muted-bg: oklch(0.269 0 0);
                --muted-fg: oklch(0.708 0 0);
                --border: oklch(0.269 0 0);
                --primary: oklch(0.985 0 0);
                --primary-fg: oklch(0.205 0 0);
                --radius: 0.625rem;
            }
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { height: 100%; }
        body {
            height: 100%;
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji';
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            background: var(--bg);
            color: var(--fg);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .container { text-align: center; max-width: 28rem; }
        .logo { margin-bottom: 2rem; }
        .logo img { width: 10rem; height: auto; }
        .code {
            font-size: 5rem;
            font-weight: 300;
            line-height: 1;
            letter-spacing: -0.025em;
            margin-bottom: 0.75rem;
        }
        .title {
            font-size: 1.25rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        .description {
            font-size: 0.875rem;
            color: var(--muted-fg);
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 500;
            font-family: inherit;
            background: var(--primary);
            color: var(--primary-fg);
            border: none;
            border-radius: var(--radius);
            text-decoration: none;
            transition: opacity 0.15s;
            cursor: pointer;
        }
        .btn:hover { opacity: 0.8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <img src="/favicon.svg" alt="{{ config('app.name', 'Mediscan') }}">
        </div>
        <div class="code">404</div>
        <h1 class="title">Page Not Found</h1>
        <p class="description">The page you're looking for doesn't exist.</p>
        <a href="{{ url('/') }}" class="btn">Go Home</a>
    </div>
</body>
</html>
