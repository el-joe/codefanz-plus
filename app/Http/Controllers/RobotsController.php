<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Services\Seo\IndexingPolicy;
use Illuminate\Http\Response;

/**
 * Dynamic robots.txt: private areas and site search are disallowed and the sitemap index is announced. When
 * indexing is blocked (Settings → SEO, default outside production) every crawler is told `Disallow: /`.
 */
class RobotsController extends Controller
{
    public function __invoke(IndexingPolicy $policy): Response
    {
        $lines = ['User-agent: *'];

        if ($policy->blocksIndexing()) {
            $lines[] = 'Disallow: /';
        } else {
            foreach (['/admin', '/portal', '/pay', '/webhooks', '/login', '/dashboard'] as $path) {
                $lines[] = 'Disallow: '.$path;
            }

            foreach (Language::active()->keys() as $locale) {
                $lines[] = 'Disallow: /'.$locale.'/search';
            }

            $lines[] = '';
            $lines[] = 'Sitemap: '.route('sitemap.index');
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
