@php
    use App\Models\PlanItem;

    $may = auth()->user()?->can('manage-events') ?? false;
    $ini = fn ($n) => \Illuminate\Support\Str::of($n)->explode(' ')->filter()
        ->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
@endphp

<div class="space-y-4">

    {{-- ══ the bar ══ --}}
    <div class="flex flex-wrap items-center gap-2">
        <div class="relative">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-navy-300" />
            <input type="search" wire:model.live.debounce.300ms="q" placeholder="Deliverable, event, track…"
                   class="input h-10 w-56 !rounded-2xl !py-0 !ps-9 text-xs xl:w-72">
        </div>

        {{-- Event --}}
        <details class="relative" data-menu>
            <summary class="flex h-10 cursor-pointer list-none items-center gap-1.5 rounded-2xl border border-line bg-white px-3.5 text-[12px] font-semibold text-navy-700 shadow-sm transition hover:border-gold-300 [&::-webkit-details-marker]:hidden">
                <x-icon name="calendar" class="h-3.5 w-3.5 text-navy-400" />
                {{ $eventId ? \Illuminate\Support\Str::limit($events->firstWhere('id', $eventId)?->name ?? 'Event', 22) : 'All events' }}
                @if ($eventId)<span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span>@endif
            </summary>
            <div class="absolute z-30 mt-2 max-h-72 w-64 overflow-y-auto rounded-2xl border border-line bg-white p-1.5 shadow-xl">
                <button type="button" wire:click="setEvent(0)"
                        @class(['flex w-full rounded-xl px-3 py-2 text-start text-[12px] font-semibold transition',
                            'bg-navy-950 text-white' => ! $eventId, 'text-navy-600 hover:bg-page' => (bool) $eventId])>All events</button>
                @foreach ($events as $e)
                    <button type="button" wire:click="setEvent({{ $e->id }})"
                            @class(['flex w-full truncate rounded-xl px-3 py-2 text-start text-[12px] font-semibold transition',
                                'bg-navy-950 text-white' => $eventId === $e->id, 'text-navy-600 hover:bg-page' => $eventId !== $e->id])>{{ $e->name }}</button>
                @endforeach
            </div>
        </details>

        {{-- Owner --}}
        <details class="relative" data-menu>
            <summary class="flex h-10 cursor-pointer list-none items-center gap-1.5 rounded-2xl border border-line bg-white px-3.5 text-[12px] font-semibold text-navy-700 shadow-sm transition hover:border-gold-300 [&::-webkit-details-marker]:hidden">
                <x-icon name="users" class="h-3.5 w-3.5 text-navy-400" />
                {{ $ownerId === -1 ? 'Unassigned' : ($ownerId ? \Illuminate\Support\Str::before($people->firstWhere('id', $ownerId)?->name ?? 'Owner', ' ') : 'Anyone') }}
                @if ($ownerId)<span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span>@endif
            </summary>
            <div class="absolute z-30 mt-2 max-h-72 w-56 overflow-y-auto rounded-2xl border border-line bg-white p-1.5 shadow-xl">
                <button type="button" wire:click="setOwner(0)"
                        @class(['flex w-full rounded-xl px-3 py-2 text-start text-[12px] font-semibold transition',
                            'bg-navy-950 text-white' => $ownerId === 0, 'text-navy-600 hover:bg-page' => $ownerId !== 0])>Anyone</button>
                <button type="button" wire:click="setOwner(-1)"
                        @class(['flex w-full rounded-xl px-3 py-2 text-start text-[12px] font-semibold transition',
                            'bg-navy-950 text-white' => $ownerId === -1, 'text-navy-600 hover:bg-page' => $ownerId !== -1])>Unassigned</button>
                @foreach ($people as $u)
                    <button type="button" wire:click="setOwner({{ $u->id }})"
                            @class(['flex w-full truncate rounded-xl px-3 py-2 text-start text-[12px] font-semibold transition',
                                'bg-navy-950 text-white' => $ownerId === $u->id, 'text-navy-600 hover:bg-page' => $ownerId !== $u->id])>{{ $u->name }}</button>
                @endforeach
            </div>
        </details>

        {{-- The two ways work goes missing: nobody on it, or nobody looking. --}}
        <button type="button" wire:click="toggleAttention"
                @class(['flex h-10 items-center gap-1.5 rounded-2xl border px-3.5 text-[12px] font-bold transition',
                    'border-red-300 bg-red-50 text-red-700' => $attentionOnly,
                    'border-line bg-white text-navy-600 shadow-sm hover:border-gold-300' => ! $attentionOnly])>
            <x-icon name="bell" class="h-3.5 w-3.5" /> Needs attention
        </button>

        <p class="ms-auto text-[11.5px] text-muted">{{ $items->count() }} {{ str('deliverable')->plural($items->count()) }} in view</p>
    </div>

    <x-figure-strip :figures="$figures" dense />

    @if ($items->isEmpty())
        <x-empty icon="grid" title="Nothing here"
                 hint="Clear the filters, or open an event's Planning tab to build its plan." />
    @else
        {{-- ══ THE BOARD ══
             A board rather than a list because the gate is the thing you are
             managing: an item sitting in Need Approval is a person, not a date.
             Lanes scroll horizontally on a narrow screen rather than wrapping,
             which is what a board is. ══ --}}
        <div class="scrollbar-none flex items-start gap-3 overflow-x-auto pb-2">
            @foreach ($lanes as $lane)
                <div wire:key="lane-{{ $lane['key'] }}"
                     class="flex w-[286px] shrink-0 flex-col rounded-2xl border border-line bg-white shadow-sm">

                    <div class="flex items-center gap-2 border-b border-line px-3 py-2">
                        <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $lane['hex'] }}"></span>
                        <span class="text-eyebrow font-bold uppercase tracking-[0.14em] text-navy-600">{{ $lane['label'] }}</span>
                        <span class="ms-auto text-[11px] font-black tabular-nums text-navy-300">{{ $lane['items']->count() }}</span>
                    </div>

                    <div class="scrollbar-none max-h-[62vh] space-y-2 overflow-y-auto p-2">
                        @forelse ($lane['items'] as $item)
                            @php
                                $late = $item->isOverdue();
                                [$sd, $st] = $item->subtaskProgress();
                            @endphp

                            <div wire:key="pi-{{ $item->id }}"
                                 @class(['rounded-xl border bg-page/40 p-2.5 transition hover:bg-white',
                                     'border-red-300 bg-red-50/50' => $late,
                                     'border-line' => ! $late])>

                                <a href="{{ route('events.hub', [$item->event, 'tab' => 'planning']) }}"
                                   class="block text-[12.5px] font-bold leading-snug text-navy-900 transition hover:text-gold-700">
                                    <x-record-title :record="$item" fallback="Untitled deliverable"
                                                    :muted="in_array($item->status, ['done', 'cancelled'])" />
                                </a>

                                <p class="mt-0.5 truncate text-[10.5px] text-muted">
                                    {{ $item->event?->name }}@if ($item->track) · {{ $item->track->name }} @endif
                                </p>

                                <div class="mt-2 flex items-center gap-2">
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full"
                                          style="background: {{ $item->priorityHex() }}" title="{{ $item->priorityLabel() }}"></span>

                                    @if ($item->due_on)
                                        <span @class(['text-[10.5px] font-semibold tabular-nums',
                                            'text-red-600' => $late, 'text-navy-500' => ! $late])>
                                            {{ $item->due_on->format('j M') }}
                                        </span>
                                    @else
                                        <span class="text-[10.5px] italic text-navy-300">No date</span>
                                    @endif

                                    @if ($st)
                                        <span class="text-[10.5px] tabular-nums text-navy-400">{{ $sd }}/{{ $st }}</span>
                                    @endif

                                    <span class="ms-auto flex -space-x-1.5">
                                        @forelse ($item->owners->take(3) as $o)
                                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-gradient-to-br from-navy-800 to-navy-950 text-eyebrow font-bold text-gold-300 ring-2 ring-white"
                                                  title="{{ $o->name }}">{{ $ini($o->name) }}</span>
                                        @empty
                                            <span class="text-[10px] italic text-navy-300">Unassigned</span>
                                        @endforelse
                                    </span>
                                </div>

                                {{-- Moving a gate is the one thing the board does.
                                     Approval carries a signature and stays in the
                                     studio, where the approver can see what they
                                     are approving. --}}
                                @if ($may && $item->isOpen())
                                    <div class="mt-2 flex flex-wrap gap-1 border-t border-line/60 pt-2">
                                        @foreach (PlanItem::STATUSES as $key => [$label, $hex])
                                            @continue ($key === $item->status || $key === 'cancelled')
                                            <button type="button" wire:click="moveTo({{ $item->id }}, '{{ $key }}')"
                                                    title="Move to {{ $label }}"
                                                    class="rounded-md px-1.5 py-0.5 text-[9.5px] font-bold text-navy-400 transition hover:bg-navy-50 hover:text-navy-800">
                                                {{ $label }}
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="px-1 py-6 text-center text-[11px] italic text-navy-300">Nothing here.</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
