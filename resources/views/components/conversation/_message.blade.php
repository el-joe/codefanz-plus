{{-- One message bubble; also used as the <template> the polling script clones (data-field hooks). --}}
@php($mine = $message['mine'])
<li class="flex items-end gap-2 {{ $mine ? 'flex-row-reverse' : '' }}" data-message-id="{{ $message['id'] }}" @isset($template) data-template-root @endisset>
    <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-subtle text-xs font-semibold text-fg-brand {{ $mine ? 'hidden' : '' }}" aria-hidden="true" data-field="initials">{{ $message['initials'] }}</span>
    <div class="max-w-[80%] rounded-xl px-4 py-3 text-sm {{ $mine ? 'bg-brand text-fg-on-brand' : 'border border-line bg-surface text-fg' }}" data-field="bubble">
        <p class="mb-1 text-xs font-semibold {{ $mine ? 'hidden' : 'text-fg-brand' }}" dir="auto" data-field="author">{{ $message['author'] }}</p>
        <p class="whitespace-pre-line break-words" dir="auto" data-field="body">{{ $message['body'] }}</p>
        <ul class="mt-2 flex flex-col gap-1 empty:hidden" data-field="attachments">@foreach ($message['attachments'] as $attachment)<li><a href="{{ $attachment['url'] }}" class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold {{ $mine ? 'bg-white/15' : 'bg-subtle text-fg' }}"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg><span dir="auto">{{ $attachment['name'] }}</span> <span class="opacity-90">{{ $attachment['size'] }}</span></a></li>@endforeach</ul>
        <p class="mt-1 text-[11px]" data-field="time">{{ isset($message['date']) && $message['date'] !== __('Today') ? $message['date'].' · ' : '' }}{{ $message['time'] }}</p>
    </div>
</li>
