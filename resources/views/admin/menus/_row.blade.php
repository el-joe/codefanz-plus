@php
    $target = match ($item->link_type) {
        \App\Enums\MenuLinkType::Route => __(\App\Models\MenuItem::ROUTES[$item->route_name] ?? $item->route_name).($item->route_params ? ' ?'.http_build_query($item->route_params) : ''),
        \App\Enums\MenuLinkType::Linkable => $item->linkable ? (__(\App\Models\MenuItem::LINKABLE_TYPES[$item->linkable_type] ?? $item->linkable_type).': '.($item->linkable->translations->first()?->title ?? $item->linkable->translations->first()?->name ?? '#'.$item->linkable_id)) : __('Missing content'),
        \App\Enums\MenuLinkType::Url => $item->url,
    };
@endphp
<tr class="border-t border-line-subtle">
    <td class="px-4 py-3 {{ $child ? 'ps-10' : '' }}">
        <a href="{{ route('admin.menus.items.edit', [$menu, $item]) }}" class="font-semibold text-fg hover:text-fg-brand" dir="auto">{{ $child ? '↳ ' : '' }}{{ $item->translation()?->label ?? __('Untitled') }}</a>
        @unless ($item->is_visible)
            <x-ui.badge tone="warning" :dot="false">{{ __('Hidden') }}</x-ui.badge>
        @endunless
    </td>
    <td class="px-4 py-3 text-fg-secondary"><span class="text-xs text-fg-tertiary">{{ $item->link_type->label() }}</span><br><span dir="auto" class="break-all">{{ $target }}</span></td>
    <td class="px-4 py-3">@include('admin._languages', ['model' => $item])</td>
    <td class="px-4 py-3">
        <div class="flex flex-wrap justify-end gap-1">
            @foreach (['up' => __('Move up'), 'down' => __('Move down')] as $direction => $label)
                <form method="POST" action="{{ route('admin.menus.items.move', [$menu, $item]) }}">
                    @csrf
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    <button type="submit" class="rounded-md px-2.5 py-1 text-xs font-semibold text-fg-secondary hover:bg-subtle">{{ $label }}</button>
                </form>
            @endforeach
        </div>
    </td>
</tr>
