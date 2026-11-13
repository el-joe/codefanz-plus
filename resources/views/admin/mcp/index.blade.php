@php
    $snippet = "claude mcp add codefanz \\\n  --env CODEFANZ_API_URL={$apiUrl} \\\n  --env CODEFANZ_MCP_TOKEN=<token> \\\n  -- node ".base_path('mcp-server/dist/index.js');
@endphp

<x-layouts.admin :title="__('MCP server · clients & activity')" :crumb="__('AI & Automation')">
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <x-ui.card padding="p-0" class="overflow-x-auto">
            <div class="flex items-center justify-between gap-3 px-5 pt-5 pb-4">
                <h2 class="text-base font-semibold text-fg">{{ __('MCP clients') }}</h2>
                <x-ui.button size="sm" :href="route('admin.mcp.clients.create')">{{ __('New client') }}</x-ui.button>
            </div>
            @if ($clients->isEmpty())
                <x-ui.empty-state :title="__('No MCP clients yet')" :description="__('Create a client to let Claude Code or Claude Desktop read content and propose changes for approval.')" />
            @else
                <table class="w-full text-sm">
                    <thead class="bg-subtle text-xs text-fg-tertiary">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Client') }}</th>
                            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Scopes') }}</th>
                            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Owner') }}</th>
                            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Last used') }}</th>
                            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Expires') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($clients as $client)
                            <tr class="border-t border-line-subtle align-top" data-mcp-client="{{ $client->id }}">
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.mcp.clients.edit', $client) }}" class="font-mono text-sm font-semibold text-fg hover:text-fg-brand" dir="ltr">{{ $client->name }}</a>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @if (! $client->tokens_exists)
                                            <x-ui.badge tone="neutral">{{ __('No token') }}</x-ui.badge>
                                        @elseif ($client->isExpired())
                                            <x-ui.badge tone="danger">{{ __('Expired') }}</x-ui.badge>
                                        @else
                                            <x-ui.badge :tone="$client->status === \App\Enums\McpClientStatus::Active ? 'success' : 'neutral'">{{ $client->status->label() }}</x-ui.badge>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="flex max-w-xs flex-wrap gap-1" dir="ltr">
                                        @foreach ($client->scopes as $scope)
                                            <x-ui.badge :tone="\App\Enums\McpScope::tryFrom($scope)?->isWrite() ? 'warning' : 'info'" :dot="false" class="font-mono">{{ $scope }}</x-ui.badge>
                                        @endforeach
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-fg-secondary">{{ $client->owner?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-fg-secondary">{{ $client->last_used_at?->diffForHumans() ?? __('Never') }}</td>
                                <td class="px-4 py-3 text-fg-secondary">{{ $client->expires_at?->translatedFormat('M j, Y') ?? __('Never') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('Safety controls')">
            <div class="flex flex-col gap-4">
                @can('automation.manage')
                    @foreach ($switches as $switch)
                        @include('admin._kill-switch-toggle', ['key' => $switch['key'], 'active' => $switch['active']])
                    @endforeach
                @else
                    @foreach ($switches as $switch)
                        <p class="flex items-center justify-between gap-3 text-sm text-fg">
                            {{ $switch['key']->label() }}
                            <x-ui.badge :tone="$switch['active'] ? 'danger' : 'success'">{{ $switch['active'] ? __('Paused') : __('Running') }}</x-ui.badge>
                        </p>
                    @endforeach
                @endcan
                <p class="text-xs text-fg-tertiary">{{ __('Each client has its own per-minute rate limit. MCP has no tools for SQL, shell, files, arbitrary URLs, payments, users, roles or credentials. Proposals always wait for a human in the approval queue.') }}</p>
                <div class="flex flex-col gap-2">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-fg">{{ __('Connect Claude Code') }}</h3>
                        <button type="button" data-copy="{{ $snippet }}" data-copied-label="{{ __('Copied') }}" class="rounded-md border border-line px-2 py-1 text-xs font-semibold text-fg hover:bg-subtle">{{ __('Copy') }}</button>
                    </div>
                    <pre class="overflow-x-auto rounded-md bg-inverse p-3 text-xs leading-relaxed text-fg-inverse" dir="ltr"><code>{{ $snippet }}</code></pre>
                    <p class="text-xs text-fg-tertiary">{{ __('Replace <token> with the client token. Setup, every tool and the Claude Desktop config are in mcp-server/README.md.') }}</p>
                </div>
            </div>
        </x-ui.card>
    </div>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        <div class="flex flex-col gap-4 px-5 pt-5 pb-4">
            <h2 class="text-base font-semibold text-fg">{{ __('Activity log') }}</h2>
            <form method="GET" class="flex flex-wrap items-end gap-3" data-auto-submit>
                <div class="w-48"><x-ui.select name="client" :label="__('Client')" :options="$clients->pluck('name', 'id')->all()" :value="$filters['client'] ?? null" :placeholder="__('All clients')" /></div>
                <div class="w-56"><x-ui.select name="tool" :label="__('Tool')" :options="$tools" :value="$filters['tool'] ?? null" :placeholder="__('All tools')" /></div>
                <div class="w-44"><x-ui.select name="result" :label="__('Result')" :options="\App\Enums\McpCallResult::options()" :value="$filters['result'] ?? null" :placeholder="__('All results')" /></div>
                <x-ui.button variant="secondary" size="sm">{{ __('Filter') }}</x-ui.button>
            </form>
        </div>
        @if ($calls->isEmpty())
            <x-ui.empty-state :title="__('No tool calls yet')" :description="__('Every MCP call, allowed or denied, is listed here with sanitized arguments.')" />
        @else
            @include('admin.mcp._calls', ['calls' => $calls, 'showClient' => true])
        @endif
    </x-ui.card>

    {{ $calls->links() }}
</x-layouts.admin>
