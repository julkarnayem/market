<?php
namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Category;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function robots(): Response
    {
        $content = implode("\n", [
            'User-agent: *',
            '',
            '# Public — allow indexing',
            'Allow: /$',
            'Allow: /marketplace',
            'Allow: /asset/',
            'Allow: /users/',
            'Allow: /legal/',
            'Allow: /faq',
            'Allow: /contact',
            '',
            '# Private — block crawlers',
            'Disallow: /admin/',
            'Disallow: /dashboard/',
            'Disallow: /checkout/',
            'Disallow: /messages/',
            'Disallow: /tickets/',
            'Disallow: /verification/',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /forgot-password',
            'Disallow: /reset-password/',
            'Disallow: /api/',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ]);

        return response($content, 200, ['Content-Type' => 'text/plain']);
    }

    public function sitemap(): Response
    {
        $urls = [];

        // Static public pages
        foreach (['/', '/marketplace', '/faq', '/contact'] as $path) {
            $urls[] = ['loc' => url($path), 'priority' => ($path === '/' ? '1.0' : '0.8'), 'changefreq' => 'daily'];
        }

        // Categories
        Category::roots()->active()->orderBy('position')->get()->each(function ($cat) use (&$urls) {
            $urls[] = ['loc' => route('marketplace.index', ['category' => $cat->slug]), 'priority' => '0.8', 'changefreq' => 'daily'];
        });

        // Published listings (paginated to avoid huge memory usage)
        Asset::where('status', 'published')
            ->select(['id','slug','updated_at'])
            ->orderByDesc('updated_at')
            ->chunk(200, function ($listings) use (&$urls) {
                foreach ($listings as $listing) {
                    $urls[] = [
                        'loc'        => route('marketplace.show', $listing->slug),
                        'lastmod'    => $listing->updated_at->toAtomString(),
                        'priority'   => '0.6',
                        'changefreq' => 'weekly',
                    ];
                }
            });

        $xml = $this->buildSitemap($urls);
        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    private function buildSitemap(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= "\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($urls as $u) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($u['loc']) . "</loc>\n";
            if (!empty($u['lastmod']))    $xml .= '    <lastmod>'    . $u['lastmod']    . "</lastmod>\n";
            if (!empty($u['changefreq'])) $xml .= '    <changefreq>' . $u['changefreq'] . "</changefreq>\n";
            if (!empty($u['priority']))   $xml .= '    <priority>'   . $u['priority']   . "</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>';
        return $xml;
    }
}
