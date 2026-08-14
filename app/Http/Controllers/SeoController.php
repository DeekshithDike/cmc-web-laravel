<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function robots(): Response
    {
        $base = rtrim((string) config('app.url'), '/');
        $body = <<<TXT
User-agent: *
Allow: /
Allow: /customer/login
Disallow: /admin
Disallow: /admin/
Disallow: /customer/
Disallow: /webhooks/
Disallow: /credentials/

Sitemap: {$base}/sitemap.xml

TXT;

        return response($body, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function sitemap(): Response
    {
        $base = rtrim((string) config('app.url'), '/');
        $lastmod = now()->toDateString();
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>{$base}/</loc>
    <lastmod>{$lastmod}</lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
  </url>
</urlset>
XML;

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
