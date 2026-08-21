{{-- ══════════════════════════════════════════════════════════════
     SELECTED EVENT DETAIL PANEL — the one panel Mission Board, Timeline,
     Table and Calendar all share. Desktop: sticky right column.
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
        <x-cc.briefing-panel title="{{ $a['name'] }}" subtitle="{{ $a['client'] ?: 'No client on file' }}">
            <x-slot:header>
                @if ($a['statusLabel'])<span class="rounded-full px-2 py-0.5 text-[10px] font-bold bg-info-soft text-info-ink">{{ $a['statusLabel'] }}</span>@endif
            </x-slot:header>

            <div class="grid grid-cols-2 gap-3">
                <x-cc.kpi-tile label="Health" :value="$a['healthScore'] ?? '—'" :tone="$healthTone" />
                <x-cc.kpi-tile label="Readiness" value="{{ $a['progress'] }}%" tone="live" />
            </div>

            <div class="mt-4 space-y-2 text-[13px]">
                @foreach ([
                    ['Event type', $typeLabel ?? '—'],
                    ['Date', $a['dates']],
                    ['Location', $a['where'] ?: '—'],
                    ['Budget', $a['budgetLabel'] ? $a['budgetLabel'].' '.$a['budgetOf'] : '—'],
                    ['Owner', $ownerName ?: 'Unassigned'],
                ] as [$k, $v])
                    <div class="flex justify-between gap-3 border-b border-line/70 pb-2 last:border-0 last:pb-0">
                        <span class="text-muted">{{ $k }}</span>
                        <span class="min-w-0 truncate text-end font-semibold text-ink">{{ $v }}</span>
                    </div>
                @endforeach
            </div>

            @if ($a['milestone'])
                <div class="mt-4 rounded-md bg-page px-3 py-2.5">
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Next action</span>
                        @if ($urgencyTone)
                            <span class="rounded-full px-2 py-0.5 text-[9.5px] font-bold {{ $urgencyTone === 'risk' ? 'bg-danger-soft text-danger-ink' : 'bg-warning-soft text-warning-ink' }}">{{ $urgencyTone === 'risk' ? 'Overdue' : 'Due today' }}</span>
                        @endif
                    </div>
                    <p class="mt-1 truncate text-[12.5px] font-semibold text-ink">{{ $a['milestone']['title'] }}</p>
                    <p class="text-[11px] text-muted">{{ $a['milestone']['due'] }}</p>
                </div>
            @endif
        </x-cc.briefing-panel>

        <div class="rounded-lg border border-line bg-white p-4">
            <p class="mb-3 text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Mission Actions</p>
            <a href="{{ route('events.hub', $a['event']) }}" wire:navigate
               class="flex w-full items-center justify-center rounded-full bg-gold-500 px-3.5 py-2 text-[12px] font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">Open Event Hub →</a>
        </div>
    @else
        {{-- Nothing selected: the portfolio's own summary, not an empty box —
             the panel always has something true to say. --}}
        <x-cc.briefing-panel title="Portfolio Summary" subtitle="Select a mission to inspect">
            <div class="grid grid-cols-2 gap-3">
                @foreach ($figures as $f)
                    <x-cc.kpi-tile :label="$f['label']" :value="$f['value']" :hint="$f['note'] ?? null" />
                @endforeach
            </div>
            <p class="mt-4 text-[12.5px] leading-relaxed text-muted">
                Pick a mission from the board, timeline or table — its detail opens here, in this same panel, whichever view you're reading.
            </p>
        </x-cc.briefing-panel>
    @endif
</div>
