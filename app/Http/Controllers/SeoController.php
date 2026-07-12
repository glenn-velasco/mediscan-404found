<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SeoController extends Controller
{
    /**
     * Paths that exist but shouldn't show up in search results — auth
     * flows, settings, admin, and anything gated by a one-time token.
     */
    private const DISALLOWED_PATHS = [
        '/admin',
        '/settings',
        '/professional-application',
        '/invite',
        '/verify-email',
        '/reset-password',
        '/login',
        '/register',
        '/docs',
    ];

    public function robots(): Response
    {
        if (! app()->environment('production')) {
            return response("User-agent: *\nDisallow: /\n", 200, [
                'Content-Type' => 'text/plain',
            ]);
        }

        $lines = ['User-agent: *'];

        foreach (self::DISALLOWED_PATHS as $path) {
            $lines[] = "Disallow: {$path}";
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.route('sitemap');

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain',
        ]);
    }

    public function sitemap(): Response
    {
        // One entry today — the landing page. Add another ['loc' => ..., ...]
        // here when a second public page exists.
        $urls = [
            ['loc' => route('home'), 'changefreq' => 'monthly', 'priority' => '1.0'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>{$url['loc']}</loc>\n";
            $xml .= "    <changefreq>{$url['changefreq']}</changefreq>\n";
            $xml .= "    <priority>{$url['priority']}</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }
}
