<?php

use App\Models\Post;
use App\Services\Blog\ArticleBody;
use App\Services\Blog\HtmlSanitizer;

function sanitize(string $html): string
{
    return app(HtmlSanitizer::class)->sanitize($html);
}

/**
 * Payloads from the OWASP XSS Filter Evasion Cheat Sheet (and common variants).
 *
 * @return array<string, array{0: string}>
 */
dataset('xss payloads', [
    'script tag' => ['<script>alert(1)</script>'],
    'script src' => ['<SCRIPT SRC=https://xss.example/xss.js></SCRIPT>'],
    'img onerror' => ['<img src=x onerror=alert(1)>'],
    'img javascript src' => ['<IMG SRC="javascript:alert(\'XSS\');">'],
    'img no quotes' => ['<IMG SRC=javascript:alert(\'XSS\')>'],
    'case insensitive' => ['<IMG SRC=JaVaScRiPt:alert(\'XSS\')>'],
    'html entities' => ['<IMG SRC=&#106;&#97;&#118;&#97;&#115;&#99;&#114;&#105;&#112;&#116;&#58;&#97;&#108;&#101;&#114;&#116;&#40;&#39;&#88;&#83;&#83;&#39;&#41;>'],
    'hex entities' => ['<IMG SRC=&#x6A&#x61&#x76&#x61&#x73&#x63&#x72&#x69&#x70&#x74&#x3A&#x61&#x6C&#x65&#x72&#x74&#x28&#x27&#x58&#x53&#x53&#x27&#x29>'],
    'embedded tab' => ["<a href=\"jav\tascript:alert('XSS');\">x</a>"],
    'embedded newline' => ["<a href=\"jav&#x0A;ascript:alert('XSS');\">x</a>"],
    'leading spaces and meta chars' => ['<a href=" &#14;  javascript:alert(\'XSS\');">x</a>'],
    'data uri link' => ['<a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">x</a>'],
    'vbscript' => ['<a href="vbscript:msgbox(1)">x</a>'],
    'svg onload' => ['<svg/onload=alert(1)>'],
    'svg script' => ['<svg><script>alert(1)</script></svg>'],
    'body onload' => ['<BODY ONLOAD=alert(\'XSS\')>'],
    'iframe javascript' => ['<IFRAME SRC="javascript:alert(\'XSS\');"></IFRAME>'],
    'iframe other host' => ['<iframe src="https://evil.example/embed"></iframe>'],
    'object' => ['<OBJECT TYPE="text/x-scriptlet" DATA="https://xss.example/scriptlet.html"></OBJECT>'],
    'embed' => ['<EMBED SRC="data:image/svg+xml;base64,PHN2Zz48c2NyaXB0PmFsZXJ0KDEpPC9zY3JpcHQ+PC9zdmc+" type="image/svg+xml"></EMBED>'],
    'style tag' => ['<STYLE>@import\'https://xss.example/xss.css\';</STYLE>'],
    'style attribute' => ['<p style="background-image: url(javascript:alert(1))">x</p>'],
    'meta refresh' => ['<META HTTP-EQUIV="refresh" CONTENT="0;url=javascript:alert(\'XSS\');">'],
    'base href' => ['<BASE HREF="javascript:alert(\'XSS\');//">'],
    'link stylesheet' => ['<LINK REL="stylesheet" HREF="javascript:alert(\'XSS\');">'],
    'form action' => ['<form action="javascript:alert(1)"><button>x</button></form>'],
    'input autofocus' => ['<input onfocus=alert(1) autofocus>'],
    'details ontoggle' => ['<details open ontoggle=alert(1)>'],
    'malformed a' => ['\<a onmouseover="alert(document.cookie)"\>xxs link\</a\>'],
    'half open' => ['<IMG SRC="`<javascript:alert>`(\'XSS\')"'],
    'nested quote breakout' => ['<a href="https://ok.example" onclick="alert(1)">x</a>'],
    'unclosed script' => ['<SCRIPT/XSS SRC="https://xss.example/xss.js"></SCRIPT>'],
    'protocol relative' => ['<a href="//evil.example/x">x</a>'],
    'comment conditional' => ['<!--[if gte IE 4]><SCRIPT>alert(\'XSS\');</SCRIPT><![endif]-->'],
    'math href' => ['<math><mi xlink:href="javascript:alert(1)">x</mi></math>'],
    'template' => ['<template><img src=x onerror=alert(1)></template>'],
    'noscript breakout' => ['<noscript><p title="</noscript><img src=x onerror=alert(1)>"></noscript>'],
    'id clobbering' => ['<img name="cookie" id="x" src="/a.png">'],
]);

test('xss payloads leave no executable markup', function (string $payload) {
    $clean = sanitize($payload);
    $lower = strtolower($clean);

    expect($lower)->not->toContain('<script')
        ->not->toContain('javascript:')
        ->not->toContain('vbscript:')
        ->not->toContain('data:')
        ->not->toContain('<svg')
        ->not->toContain('<math')
        ->not->toContain('<style')
        ->not->toContain('<object')
        ->not->toContain('<embed')
        ->not->toContain('<form')
        ->not->toContain('<input')
        ->not->toContain('<meta')
        ->not->toContain('<base')
        ->not->toContain('<link')
        ->not->toContain('style=')
        ->not->toContain(' id=')
        ->not->toContain(' name=')
        ->not->toMatch('/<[^>]+\son[a-z]+\s*=/i')
        ->not->toMatch('/<iframe(?![^>]*src="https:\/\/(www\.youtube-nocookie\.com|player\.vimeo\.com)\/)/i')
        ->not->toContain('href="//');
})->with('xss payloads');

test('safe article markup survives', function () {
    $html = '<h2>Why Laravel</h2><p>Read <a href="/en/services">our services</a> and <a href="https://laravel.com/docs">the docs</a>.</p>'
        .'<ul><li><strong>Fast</strong></li><li><em>Secure</em></li></ul><ol start="3"><li>Three</li></ol>'
        .'<blockquote><p>Quote</p></blockquote><pre><code class="language-php">$a = 1 &lt; 2;</code></pre>'
        .'<aside data-callout="tip"><p>Rule of thumb</p></aside>'
        .'<table><tbody><tr><th scope="col">A</th><td colspan="2">B</td></tr></tbody></table>'
        .'<img src="/storage/media/a.webp" alt="Chart" width="800" height="450" data-media-id="12">';

    $clean = sanitize($html);

    expect($clean)->toContain('<h2>Why Laravel</h2>')
        ->toContain('<a href="/en/services">our services</a>')
        ->toContain('<a href="https://laravel.com/docs" rel="noopener noreferrer nofollow">the docs</a>')
        ->toContain('<ol start="3">')
        ->toContain('<code class="language-php">$a = 1 &lt; 2;</code>')
        ->toContain('<aside data-callout="tip" role="note">')
        ->toContain('<th scope="col">A</th>')
        ->toContain('<td colspan="2">B</td>')
        ->toContain('<img src="/storage/media/a.webp" alt="Chart" width="800" height="450" data-media-id="12">');
});

test('arabic text is kept as utf-8', function () {
    expect(sanitize('<p dir="rtl">مرحبا بالعالم</p>'))->toBe('<p dir="rtl">مرحبا بالعالم</p>');
});

test('unknown elements are unwrapped and headings normalised', function () {
    expect(sanitize('<div><span class="x">Hello</span></div>'))->toBe('Hello')
        ->and(sanitize('<h1>Title</h1><h6>Small</h6>'))->toBe('<h2>Title</h2><h4>Small</h4>')
        ->and(sanitize('<a href="http://insecure.example">x</a>'))->toBe('x')
        ->and(sanitize('<a href="mailto:a@b.c">mail</a>'))->toBe('mail')
        ->and(sanitize('<code class="evil onload">x</code>'))->toBe('<code>x</code>')
        ->and(sanitize('<aside data-callout="script">x</aside>'))->toBe('<aside data-callout="info" role="note">x</aside>');
});

test('video embeds are rebuilt as privacy-enhanced iframes', function () {
    $youtube = sanitize('<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=1" onload="alert(1)" width="560"></iframe>');
    $vimeo = sanitize('<iframe src="https://player.vimeo.com/video/76979871"></iframe>');

    expect($youtube)->toContain('src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"')
        ->toContain('sandbox="allow-scripts allow-same-origin allow-presentation allow-popups"')
        ->not->toContain('onload')
        ->not->toContain('autoplay')
        ->and($vimeo)->toContain('src="https://player.vimeo.com/video/76979871?dnt=1"');
});

test('article bodies get heading anchors, a table of contents and a reading time', function () {
    $prepared = app(ArticleBody::class)->prepare('<h2 id="evil">Start here</h2><p>'.str_repeat('word ', 450).'</p><h3>Details</h3><h2>Start here</h2><h2>لماذا لارافيل؟</h2>');

    expect($prepared['body'])->toContain('<h2 id="start-here">Start here</h2>')
        ->toContain('<h2 id="start-here-2">')
        ->toContain('<h2 id="لماذا-لارافيل">')
        ->and($prepared['toc'])->toBe([
            ['level' => 2, 'id' => 'start-here', 'text' => 'Start here'],
            ['level' => 3, 'id' => 'details', 'text' => 'Details'],
            ['level' => 2, 'id' => 'start-here-2', 'text' => 'Start here'],
            ['level' => 2, 'id' => 'لماذا-لارافيل', 'text' => 'لماذا لارافيل؟'],
        ])
        ->and($prepared['word_count'])->toBe(457)
        ->and($prepared['reading_minutes'])->toBe(3);
});

test('every write to an article body is sanitized by the model', function () {
    $post = Post::factory()->create();
    $translation = $post->translations()->create(['locale' => 'en', 'title' => 'T', 'slug' => 't', 'body' => '<h2>Go</h2><script>alert(1)</script><p onclick="x()">Hi</p>']);

    expect($translation->fresh()->body)->toBe('<h2 id="go">Go</h2><p>Hi</p>')
        ->and($translation->fresh()->toc)->toEqual([['level' => 2, 'id' => 'go', 'text' => 'Go']]);
});
