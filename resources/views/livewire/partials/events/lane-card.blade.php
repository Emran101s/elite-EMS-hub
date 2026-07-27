@php
    // A card on the board. Deliberately quiet: it rests on the workspace rather
    // than fighting the page, so it needs a hairline and a small lift, not a
    // dark header and a heavy shadow.
    //
    // Expects: $event, $h (health breakdown|null), $m (metrics), $isFav, $isOpen
    $score = $h['score'] ?? null;
    $group = $h['group'] ?? 'neutral';
    $ring  = ['ok' => 'border-track', 'warn' => 'border-warn', 'risk' => 'border-risk', 'neutral' => 'border-line'][$group] ?? 'border-line';
    $days  = $event->starts_at ? (int) now()->startOfDay()->diffInDays($event->starts_at->startOfDay(), false) : null;

    // The four meters every event carries, in fixed order so they line up
    // down a lane and you can read a column instead of a card.
    $meters = [
        ['Budget', $h['components']['budget'] ?? null],
        ['Tasks', $h['components']['tasks'] ?? null],
        ['Suppliers', $h['components']['suppliers'] ?? null],
        ['Risk', $h['components']['risk'] ?? null],
    ];
    $team = $event->teamMembers->take(3);
@endphp

<div wire:key="lane-{{ $event->id }}"
     data-card="{{ $event->id }}"
     @class([
         'group mb-3 rounded-2xl border bg-white p-3.5 transition duration-200',
         'border-line hover:-translate-y-0.5 hover:border-gold-300 hover:shadow-[0_16px_32px_-22px_rgba(11,31,58,0.55)]' => ! $isOpen,
         'border-gold-400 shadow-[0_16px_32px_-22px_rgba(11,31,58,0.55)] ring-1 ring-gold-300' => $isOpen,
     ])>

    <button type="button" wire:click="toggleExpand({{ $event->id }})" class="block w-full text-left">
        <span class="flex items-start gap-2.5">
            <span class="min-w-0 flex-1">
                <span class="block truncate text-[13.5px] font-bold leading-tight text-navy-900">{{ $event->name }}</span>
                <span class="mt-1 block truncate text-[10.5px] text-muted">
                    {{ str($event->stage)->replace('_', ' ')->title() }} · {{ $event->venue?->name ?? ($event->city ?: 'Venue TBC') }}
                </span>
            </span>

            {{-- the score, or an honest dash for an unscored proposal --}}
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full border-2 {{ $ring }} text-[11.5px] font-bold text-navy-900 tabular-nums">
                {{ $score ?? '—' }}
            </span>
        </span>

        <span class="mt-3 grid grid-cols-4 gap-2">
            @foreach ($meters as [$label, $val])
                <span class="block">
                    <span class="block text-[8.5px] font-bold uppercase tracking-[0.07em] text-navy-300">{{ $label }}</span>
                    <span class="mt-1 flex gap-[2px]">
                        @for ($i = 0; $i < 5; $i++)
                            <span @class([
                                'h-1 flex-1 rounded-full',
                                'bg-navy-50' => $val === null || $i >= round($val / 100 * 5),
                                'bg-track' => $val !== null && $i < round($val / 100 * 5) && $val >= 80,
                                'bg-warn' => $val !== null && $i < round($val / 100 * 5) && $val >= 50 && $val < 80,
                                'bg-risk' => $val !== null && $i < round($val / 100 * 5) && $val < 50,
                            ])></span>
                        @endfor
                    </span>
                </span>
            @endforeach
        </span>

        <span class="mt-3 flex items-center gap-2 border-t border-line pt-2.5">
            @forelse ($team as $member)
                <span class="-ml-1.5 first:ml-0">
                    <x-user-avatar :user="$member" size="h-6 w-6" class="ring-2 ring-white" />
                </span>
            @empty
                <span class="text-[10px] text-navy-300">No team yet</span>
            @endforelse
            @if ($event->teamMembers->count() > 3)
                <span class="-ml-1.5 grid h-6 w-6 place-items-center rounded-full bg-navy-50 text-[8.5px] font-bold text-navy-500 ring-2 ring-white">+{{ $event->teamMembers->count() - 3 }}</span>
            @endif

            <span class="ml-auto text-[10.5px] font-semibold tabular-nums {{ $days !== null && $days < 14 ? 'text-gold-700' : 'text-muted' }}">
                {{ $days === null ? '—' : ($days >= 0 ? $days.'d' : abs($days).'d ago') }}
            </span>
        </span>
    </button>

    <div class="mt-2.5 flex items-center gap-1 border-t border-line pt-2.5">
        <a href="{{ route('events.hub', $event) }}"
           class="flex-1 rounded-lg bg-navy-50 py-1.5 text-center text-[11px] font-bold text-navy-700 transition hover:bg-navy-900 hover:text-white">Open hub</a>
        <button type="button" wire:click="toggleFavorite({{ $event->id }})"
                title="{{ $isFav ? 'Unstar' : 'Star' }}"
                @class([
                    'grid h-7 w-7 place-items-center rounded-lg transition',
                    'text-gold-500 hover:bg-gold-50' => $isFav,
                    'text-navy-300 hover:bg-navy-50 hover:text-navy-600' => ! $isFav,
                ])>★</button>
        <label class="grid h-7 w-7 cursor-pointer place-items-center rounded-lg transition hover:bg-navy-50" title="Select">
            <input type="checkbox" wire:click="toggleSelect({{ $event->id }})"
                   @checked(in_array($event->id, $selectedIds, true))
                   class="h-3.5 w-3.5 rounded border-line text-navy-900 focus:ring-gold-400">
        </label>
    </div>
</div>
