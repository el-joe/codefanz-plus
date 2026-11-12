<?php

namespace App\Http\Controllers;

use App\Services\Seo\SitemapBuilder;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(SitemapBuilder $sitemaps): Response
    {
        return $this->xml($sitemaps->index());
    }

    public function show(string $name, SitemapBuilder $sitemaps): Response
    {
        return $this->xml($sitemaps->child($name) ?? abort(404));
    }

    private function xml(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
