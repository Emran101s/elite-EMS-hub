{{-- ══════════════════════════════════════════════════════════════
     SELECTED EVENT DETAIL PANEL — the one panel Mission Board, Radar,
     Timeline, Table and Calendar all share. Desktop: sticky right column.
     Narrower than xl: stacks below the workspace, same content, same
     order — never at the bottom of a scroll you have to hunt for, always
     the next thing after the view you were reading.
     ══════════════════════════════════════════════════════════════ --}}
<div class="flex flex-col gap-4 xl:sticky xl:top-4 xl:self-start">
    @if ($active)
        @php
            $a = $active;
            $healthTone = match ($a['healthGroup'] ?? null) {
                'risk' => 'risk', 'warn' => 'warn', 'ok' => 'ok', default => null,
            };
            $typeLabel = $a['event']->type ? str($a['event']->type)->replace('_', ' ')->title() : null;
            $ownerRaw = $a['milestone']['owner'] ?? null;
            $ownerName = is_object($ownerRaw) ? ($ownerRaw->name ?? null) : $ownerRaw;
            $urgencyTone = $a['milestone']['tone'] ?? (($a['milestone']['overdue'] ?? false) ? 'risk' : null);
        @endphp
        <x-eo.detail-panel title="{{ $a['name'] }}" :subtitle="$a['client'] ?: 'No client on file'">
            <x-slot:header>
                @if ($a['statusLabel'])<x-eo.status-pill tone="live">{{ $a['statusLabel'] }}</x-eo.status-pill>@endif
            </x-slot:header>

            <div class="grid grid-cols-2 gap-3">
                <x-eo.metric-pill label="Health" :value="$a['healthScore'] ?? '—'" :tone="$healthTone" />
                <x-eo.metric-pill label="Readiness" :value="$a['progress'].'%'" tone="live" />
            </div>

            <div class="mt-4 space-y-2 text-[13px]">
                @foreach ([
                    ['Event type', $typeLabel ?? '—'],
                    ['Date', $a['dates']],
                    ['Location', $a['where'] ?: '—'],
                    ['Budget', $a['budgetLabel'] ? $a['budgetLabel'].' '.$a['budgetOf'] : '—'],
                    ['Owner', $ownerName ?: 'Unassigned'],
                ] as [$k, $v])
                    <div class="flex justify-between gap-3 border-b border-eo-line/70 pb-2 last:border-0 last:pb-0">
                        <span class="text-eo-muted">{{ $k }}</span>
                        <span class="min-w-0 truncate text-end font-semibold text-eo-text">{{ $v }}</span>
                    </div>
                @endforeach
            </div>

            @if ($a['milestone'])
                <div class="mt-4 rounded-xl bg-eo-workspace px-3 py-2.5">
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="eo-label">Next action</span>
                        @if ($urgencyTone)<x-eo.status-pill :tone="$urgencyTone" class="!text-[9.5px]">{{ $urgencyTone === 'risk' ? 'Overdue' : 'Due today' }}</x-eo.status-pill>@endif
                    </div>
                    <p class="mt-1 truncate text-[12.5px] font-semibold text-eo-text">{{ $a['milestone']['title'] }}</p>
                    <p class="text-[11px] text-eo-muted">{{ $a['milestone']['due'] }}</p>
                </div>
            @endif
        </x-eo.detail-panel>

        <x-eo.action-panel title="Mission Actions">
            <x-eo.button href="{{ route('events.hub', $a['event']) }}" wire:navigate class="w-full justify-center" size="sm">Open Event Hub →</x-eo.button>
        </x-eo.action-panel>
    @else
        {{-- Nothing selected: the portfolio's own summary, not an empty box —
             the panel always has something true to say. --}}
        <x-eo.detail-panel title="Portfolio Summary" subtitle="Select a mission to inspect">
            <div class="grid grid-cols-2 gap-3">
                @foreach ($figures as $f)
                    <x-eo.metric-pill :label="$f['label']" :value="$f['value']" :hint="$f['note'] ?? null" />
                @endforeach
            </div>
            <p class="mt-4 text-[12.5px] leading-relaxed text-eo-muted">
                Pick a mission from the board, radar, timeline or table — its detail opens here, in this same panel, whichever view you're reading.
            </p>
        </x-eo.detail-panel>
    @endif
</div>
