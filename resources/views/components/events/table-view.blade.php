@props(['rows', 'selectedIds', 'active', 'favoriteIds'])

<div class="space-y-4">
    @if ($selectedIds)
        <div class="flex flex-wrap items-center gap-3 rounded-lg border border-danger/25 bg-danger-soft px-4 py-2.5">
            <p class="text-[12.5px] font-bold text-ink">
                {{ count($selectedIds) }} {{ str('event')->plural(count($selectedIds)) }} selected
            </p>
            <button type="button" wire:click="selectAllMatching"
                    class="text-[11.5px] font-semibold text-danger-ink underline-offset-2 hover:underline">Select everything matching these filters</button>
            <button type="button" wire:click="clearSelection"
                    class="text-[11.5px] font-semibold text-muted hover:text-ink">Clear</button>

            <x-confirm
                    title="Delete {{ count($selectedIds) }} {{ str('event')->plural(count($selectedIds)) }} permanently?"
                    :body="'Their tasks, budgets, documents, contracts and bookings go with them. Invoices and proposals are kept, unattached.'.PHP_EOL.PHP_EOL.'This cannot be undone.'"
                    confirm="Delete permanently"
                    run="$wire.deleteSelected()"
                    class="ms-auto rounded-full bg-danger px-3.5 py-2 text-[11.5px] font-bold text-white transition hover:brightness-95">
                Delete permanently
            </x-confirm>
        </div>
    @endif

    <div class="overflow-hidden rounded-lg border border-line bg-white shadow-raise">
        <div class="scrollbar-none overflow-x-auto">
            <div class="min-w-[1080px]">
                @php
                    $cols = 'grid-cols-[28px_minmax(220px,2.2fr)_100px_130px_150px_78px_130px_92px_112px_128px_36px]';
                    $pageIds = $rows->pluck('id')->all();
                    $pageAllOn = $pageIds !== [] && ! array_diff($pageIds, $selectedIds);
                @endphp

                <div class="grid {{ $cols }} items-center gap-3 border-b border-line bg-page px-4 py-3 text-eyebrow font-bold uppercase tracking-[0.1em] text-muted">
                    <span>
                        @can('manage-events')
                            <input type="checkbox" @checked($pageAllOn)
                                   wire:click="toggleSelectPage({{ \Illuminate\Support\Js::from($pageIds) }})"
                                   class="h-3.5 w-3.5 cursor-pointer rounded border-line">
                        @endcan
                    </span>
                    <span>Event</span><span>Status</span><span>Dates</span><span>Location</span>
                    <span class="text-center">Progress</span><span>Budget</span>
                    <span class="text-center">Attendees</span><span>Health</span><span>Next action</span><span></span>
                </div>

                <div class="divide-y divide-line">
                    @foreach ($rows as $m)
                        @php $on = $active && $m['id'] === $active['id']; @endphp
                        @php $ticked = in_array($m['id'], $selectedIds, true); @endphp
                        <div wire:key="row-{{ $m['id'] }}" wire:click="activate({{ $m['id'] }})"
                             role="button" tabindex="0" aria-label="Open {{ $m['name'] }}" @if ($on && ! $ticked) aria-current="true" @endif
                             wire:keydown.enter="activate({{ $m['id'] }})" wire:keydown.space.prevent="activate({{ $m['id'] }})"
                             @class(['relative grid cursor-pointer '.$cols.' items-center gap-3 px-4 py-3 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-gold-400',
                                 'bg-gold-50' => $on && ! $ticked,
                                 'bg-danger-soft' => $ticked,
                                 'hover:bg-page' => ! $on && ! $ticked])>
                            @unless ($on && ! $ticked)
                                <span class="absolute inset-y-2 left-0 w-[3px] rounded-full opacity-70" style="background: {{ $m['statusHex'] }}"></span>
                            @endunless

                            <span wire:click.stop>
                                @can('manage-events')
                                    <input type="checkbox" @checked($ticked) wire:click="toggleSelect({{ $m['id'] }})"
                                           class="h-3.5 w-3.5 cursor-pointer rounded border-line">
                                @endcan
                            </span>

                            <div class="min-w-0">
                                <span class="block truncate text-[13px] font-bold text-ink">{{ $m['name'] }}</span>
                                <span class="block truncate text-[10.5px] text-muted">{{ $m['client'] ?: ($m['description'] ?: '—') }}</span>
                            </div>

                            <span>@if ($m['statusLabel'])<span class="rounded-full px-2 py-0.5 text-[9.5px] font-bold bg-info-soft text-info-ink">{{ $m['statusLabel'] }}</span>@endif</span>

                            <span class="min-w-0">
                                <span class="block truncate text-[11.5px] font-semibold text-ink">{{ $m['shortDates'] }}</span>
                                <span class="block text-[10px] text-muted">{{ $m['duration'] }}</span>
                            </span>

                            <span class="block truncate text-[11.5px] text-muted">{{ $m['where'] }}</span>

                            <span class="text-center text-[12px] font-bold tabular-nums text-ink">{{ $m['progress'] }}%</span>

                            <span class="min-w-0">
                                <span class="block truncate text-[11.5px] font-bold text-ink">{{ $m['budgetLabel'] }}</span>
                                <span class="mt-1 block h-[3px] overflow-hidden rounded-full bg-page">
                                    <span class="block h-full rounded-full bg-gold-500" style="width: {{ $m['budgetPct'] ?? 0 }}%"></span>
                                </span>
                            </span>

                            <span class="text-center">
                                <span class="block text-[13px] font-bold text-ink">{{ number_format($m['attendees']) }}</span>
                                <span class="block text-[9.5px] text-muted">{{ $m['attendeeWord'] }}</span>
                            </span>

                            <span class="flex min-w-0 items-center gap-1.5 text-[11.5px] font-semibold">
                                <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $m['riskHex'] }}"></span>
                                <span class="truncate text-ink">{{ $m['health'] }}</span>
                            </span>

                            <span class="min-w-0">
                                <span class="block truncate text-[11px] font-semibold text-ink">{{ $m['milestone']['title'] }}</span>
                                <span class="block truncate text-[10px] {{ $m['milestone']['overdue'] ? 'text-danger-ink' : 'text-muted' }}">{{ $m['milestone']['due'] }}</span>
                            </span>

                            <details class="relative justify-self-end" data-menu>
                                <summary class="grid h-7 w-7 cursor-pointer list-none place-items-center rounded-lg text-[15px] leading-none text-muted transition hover:bg-page hover:text-ink [&::-webkit-details-marker]:hidden">⋮</summary>
                                <div class="absolute end-0 z-30 mt-1 w-44 overflow-hidden rounded-lg border border-line bg-white py-1 shadow-float">
                                    <a href="{{ route('events.hub', $m['event']) }}" wire:navigate class="block px-3 py-2 text-[11.5px] font-semibold text-ink transition hover:bg-page">Open event</a>
                                    <button type="button" wire:click="toggleFavorite({{ $m['id'] }})" class="block w-full px-3 py-2 text-start text-[11.5px] font-semibold text-ink transition hover:bg-page">{{ in_array($m['id'], $favoriteIds, true) ? 'Unstar' : 'Star' }}</button>
                                    <button type="button" wire:click="duplicate({{ $m['id'] }})" class="block w-full px-3 py-2 text-start text-[11.5px] font-semibold text-ink transition hover:bg-page">Duplicate</button>
                                    <x-confirm title="Archive “{{ $m['name'] }}”?" body="It leaves every board and list." confirm="Archive" tone="warn" run="$wire.archive({{ $m['id'] }})"
                                               class="block w-full border-t border-line px-3 py-2 text-start text-[11.5px] font-semibold text-danger-ink transition hover:bg-danger-soft">Archive</x-confirm>
                                    @can('manage-events')
                                        <x-confirm
                                                title="Delete “{{ $m['name'] }}” permanently?"
                                                :body="'Its tasks, budget, documents, contracts and bookings go with it. Invoices and proposals are kept, unattached.'.PHP_EOL.PHP_EOL.'This cannot be undone.'"
                                                confirm="Delete permanently"
                                                run="$wire.deleteEvent({{ $m['id'] }})"
                                                class="block w-full px-3 py-2 text-start text-[11.5px] font-semibold text-danger-ink transition hover:bg-danger-soft">Delete permanently</x-confirm>
                                    @endcan
                                </div>
                            </details>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($rows->hasPages())
            <div class="flex flex-wrap items-center gap-3 border-t border-line px-4 py-2.5">
                <p class="text-[11px] text-muted">{{ $rows->firstItem() }}–{{ $rows->lastItem() }} of {{ $rows->total() }}</p>
                <div class="ms-auto">{{ $rows->links() }}</div>
            </div>
        @endif
    </div>
</div>
