<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Broadcasting Architecture — Mediscan</title>
    <style>
        :root {
            color-scheme: light dark;
        }

        body {
            margin: 0;
            padding: 2.5rem 1.5rem 4rem;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #1f2328;
            background: #ffffff;
        }

        @media (prefers-color-scheme: dark) {
            body {
                color: #e6edf3;
                background: #0d1117;
            }

            a {
                color: #58a6ff;
            }

            code, pre {
                background: #161b22 !important;
                border-color: #30363d !important;
            }

            table th, table td {
                border-color: #30363d !important;
            }

            table th {
                background: #161b22 !important;
            }

            blockquote {
                border-left-color: #30363d !important;
                color: #9198a1 !important;
            }
        }

        .content {
            max-width: 860px;
            margin: 0 auto;
        }

        h1, h2, h3 {
            line-height: 1.25;
        }

        h1 {
            border-bottom: 1px solid rgba(128, 128, 128, 0.3);
            padding-bottom: 0.3em;
        }

        h2 {
            border-bottom: 1px solid rgba(128, 128, 128, 0.2);
            padding-bottom: 0.3em;
            margin-top: 2em;
        }

        code {
            font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
            background: rgba(128, 128, 128, 0.15);
            padding: 0.15em 0.4em;
            border-radius: 4px;
            font-size: 0.9em;
        }

        pre {
            background: rgba(128, 128, 128, 0.1);
            border: 1px solid rgba(128, 128, 128, 0.2);
            border-radius: 8px;
            padding: 1em;
            overflow-x: auto;
        }

        pre code {
            background: none;
            padding: 0;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin: 1em 0;
            display: block;
            overflow-x: auto;
        }

        table th, table td {
            border: 1px solid rgba(128, 128, 128, 0.3);
            padding: 0.5em 0.8em;
            text-align: left;
        }

        table th {
            background: rgba(128, 128, 128, 0.1);
        }
    </style>
</head>
<body>
    <div class="content">
        {!! $content !!}
    </div>
</body>
</html>
