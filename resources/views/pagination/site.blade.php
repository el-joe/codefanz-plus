{{--
    Figma "Pagination" for public listings: "Showing x–y of n" + 36px page squares (current = brand fill), prev/next
    chevrons mirrored in RTL, rel=prev/next. Use with ->onEachSide(1)->links('pagination.site'); the summary shows even on one page.
--}}
@php
    $square = 'inline-flex size-9 items-center justify-center rounded-md type-label-md';
    $idle = $square.' border border-line bg-surface text-fg transition-colors hover:border-line-brand hover:text-fg-brand';
    $disabled = $square.' border border-line-subtle bg-surface text-fg-disabled';
    $chevron = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="rtl:-scale-x-100"><path d="%s"/></svg>';
    $previousIcon = sprintf($chevron, 'm15 6-6 6 6 6');
    $nextIcon = sprintf($chevron, 'm9 6 6 6-6 6');
@endphp

<div class="flex flex-col items-center gap-4 sm:flex-row sm:justify-between">
    <p class="type-body-sm text-fg-secondary">{{ __('Showing :from–:to of :total', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()]) }}</p>

    @if ($paginator->hasPages())
        <nav aria-label="{{ __('Pagination') }}">
            <ul class="flex flex-wrap items-center gap-1">
                <li>
                    @if ($paginator->onFirstPage())
                        <span class="{{ $disabled }}" aria-disabled="true"><span class="sr-only">{{ __('Previous page') }}</span>{!! $previousIcon !!}</span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $idle }}"><span class="sr-only">{{ __('Previous page') }}</span>{!! $previousIcon !!}</a>
                    @endif
                </li>

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li><span class="{{ $square }} border border-line bg-surface text-fg-tertiary" aria-hidden="true">…</span></li>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            <li>
                                @if ($page == $paginator->currentPage())
                                    <span class="{{ $square }} bg-brand text-fg-on-brand" aria-current="page"><span class="sr-only">{{ __('Page') }} </span>{{ $page }}</span>
                                @else
                                    <a href="{{ $url }}" class="{{ $idle }}"><span class="sr-only">{{ __('Page') }} </span>{{ $page }}</a>
                                @endif
                            </li>
                        @endforeach
                    @endif
                @endforeach

                <li>
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $idle }}"><span class="sr-only">{{ __('Next page') }}</span>{!! $nextIcon !!}</a>
                    @else
                        <span class="{{ $disabled }}" aria-disabled="true"><span class="sr-only">{{ __('Next page') }}</span>{!! $nextIcon !!}</span>
                    @endif
                </li>
            </ul>
        </nav>
    @endif
</div>
