<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>429 - {{ config('app.name', 'Mediscan') }}</title>
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
        .logo svg { width: 3rem; height: auto; color: var(--fg); }
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
            <svg viewBox="0 0 40 42" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path fillRule="evenodd" clipRule="evenodd" d="M17.2 5.63325L8.6 0.855469L0 5.63325V32.1434L16.2 41.1434L32.4 32.1434V23.699L40 19.4767V9.85547L31.4 5.07769L22.8 9.85547V18.2999L17.2 21.411V5.63325ZM38 18.2999L32.4 21.411V15.2545L38 12.1434V18.2999ZM36.9409 10.4439L31.4 13.5221L25.8591 10.4439L31.4 7.36561L36.9409 10.4439ZM24.8 18.2999V12.1434L30.4 15.2545V21.411L24.8 18.2999ZM23.8 20.0323L29.3409 23.1105L16.2 30.411L10.6591 27.3328L23.8 20.0323ZM7.6 27.9212L15.2 32.1434V38.2999L2 30.9666V7.92116L7.6 11.0323V27.9212ZM8.6 9.29991L3.05913 6.22165L8.6 3.14339L14.1409 6.22165L8.6 9.29991ZM30.4 24.8101L17.2 32.1434V38.2999L30.4 30.9666V24.8101ZM9.6 11.0323L15.2 7.92117V22.5221L9.6 25.6333V11.0323Z" />
            </svg>
        </div>
        <div class="code">429</div>
        <h1 class="title">Too Many Requests</h1>
        <p class="description">Too many requests. Please try again later.</p>
        <a href="{{ url('/') }}" class="btn">Go Home</a>
    </div>
</body>
</html>
