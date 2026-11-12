{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/">
    <channel>
        <title>{{ __('CodeFanz blog') }}</title>
        <link>{{ route('blog.index') }}</link>
        <description>{{ __('Engineering, design, SEO and AI — practical guides from our team.') }}</description>
        <language>{{ $locale }}</language>
        <atom:link href="{{ route('blog.feed') }}" rel="self" type="application/rss+xml"/>
        @if ($posts->isNotEmpty())
            <lastBuildDate>{{ $posts->first()->published_at->toRssString() }}</lastBuildDate>
        @endif
        @foreach ($posts as $post)
            @php($item = $post->exactTranslation($locale))
            @php($link = route('blog.show', ['slug' => $item->slug]))
            <item>
                <title>{{ $item->title }}</title>
                <link>{{ $link }}</link>
                <guid isPermaLink="false">{{ $post->ulid }}-{{ $locale }}</guid>
                <pubDate>{{ $post->published_at->toRssString() }}</pubDate>
                <dc:creator>{{ $post->author?->name ?? 'CodeFanz' }}</dc:creator>
                @if ($categoryName = $post->category?->exactTranslation($locale)?->name)
                    <category>{{ $categoryName }}</category>
                @endif
                <description>{{ $item->summary() }}</description>
                <content:encoded>{{ (string) $item->body }}</content:encoded>
            </item>
        @endforeach
    </channel>
</rss>
