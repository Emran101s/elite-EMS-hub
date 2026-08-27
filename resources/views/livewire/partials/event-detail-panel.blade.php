{{-- ══════════════════════════════════════════════════════════════
     SELECTED EVENT DETAIL PANEL — the one panel Board, Timeline, Table
     and Calendar all share. Desktop: sticky right rail. Below the board
     grid's breakpoint it stacks under the workspace, same content, same
     order. Concourse-styled to match the portfolio it sits beside.
     ══════════════════════════════════════════════════════════════ --}}
@php
    $cxHex = fn ($g) => match ($g) {
        'risk' => 'var(--cx-risk)', 'warn' => 'var(--cx-warn)', 'ok' => 'var(--cx-ok)', default => 'var(--cx-info)',
    };
@endphp
<aside class="cx-rail cx-reveal cx-d4" aria-label="Mission detail">
    @if ($active)
        @php
            $a = $active;
            $typeLabel = $a['event']->type ? str($a['event']->type)->replace('_', ' ')->title() : null;
            $ownerRaw = $a['milestone']['owner'] ?? null;
            $ownerName = is_object($ownerRaw) ? ($ownerRaw->name ?? null) : $ownerRaw;
            $urgencyTone = $a['milestone']['tone'] ?? (($a['milestone']['overdue'] ?? false) ? 'risk' : null);
        @endphp
        <div class="cx-card">
            <div class="cx-dp-head">
                <div style="min-width:0">
                    <h2 class="cx-dp-name">{{ $a['name'] }}</h2>
                    <p class="cx-dp-sub">{{ $a['client'] ?: 'No client on file' }}</p>
                </div>
                @if ($a['statusLabel'])<span class="cx-tag tone-info">{{ $a['statusLabel'] }}</span>@endif
            </div>

            <div class="cx-dp-stats">
                <div class="cx-dp-stat"><span class="cx-sv"><span class="cx-hd" style="background:{{ $cxHex($a['healthGroup'] ?? null) }}"></span>{{ $a['healthScore'] ?? '—' }}</span><span class="cx-sl">Health</span></div>
                <div class="cx-dp-stat"><span class="cx-sv">{{ $a['progress'] }}%</span><span class="cx-sl">Readiness</span></div>
            </div>

            <div>
                @foreach ([
                    ['Event type', $typeLabel ?? '—'],
                    ['Date', $a['dates']],
                    ['Location', $a['where'] ?: '—'],
                    ['Budget', $a['budgetLabel'] ? $a['budgetLabel'].' '.$a['budgetOf'] : '—'],
                    ['Owner', $ownerName ?: 'Unassigned'],
                ] as [$k, $v])
                    <div class="cx-field"><span class="cx-fk">{{ $k }}</span><span class="cx-fv">{{ $v }}</span></div>
                @endforeach
            </div>

            @if ($a['milestone'])
                <div class="cx-next">
                    <div class="cx-nh">
                        <span class="cx-nk">Next action</span>
                        @if ($urgencyTone)<span class="cx-tag {{ $urgencyTone === 'risk' ? 'tone-risk' : 'tone-warn' }}">{{ $urgencyTone === 'risk' ? 'Overdue' : 'Due today' }}</span>@endif
                    </div>
                    <p class="cx-nt">{{ $a['milestone']['title'] }}</p>
                    <p class="cx-nd">{{ $a['milestone']['due'] }}</p>
                </div>
            @endif

            <a href="{{ route('events.hub', $a['event']) }}" wire:navigate class="cx-btn cx-btn-accent" style="width:100%;justify-content:center;margin-top:14px">Open Event Hub →</a>
        </div>
    @else
        {{-- Nothing selected: the portfolio's own summary, not an empty box.
             Leads with the worst-scored mission so the panel is doing something
             before you've clicked anything. --}}
        @php
            $groupRank = fn ($m) => match ($m['healthGroup'] ?? null) {
                'risk' => 0, 'warn' => 1, 'live' => 2, 'ok' => 3, default => 4,
            };
            $worst = collect($deck)->sortBy([fn ($m) => $groupRank($m), fn ($m) => $m['healthScore'] ?? 999])->first();
        @endphp
        <div class="cx-card">
            <div class="cx-dp-head">
                <div>
                    <h2 class="cx-dp-name">Portfolio Summary</h2>
                    <p class="cx-dp-sub">Select a mission to inspect</p>
                </div>
            </div>
            <div class="cx-dp-stats" style="grid-template-columns:1fr 1fr">
                @foreach ($figures as $f)
                    <div class="cx-dp-stat">
                        <span class="cx-sv">{{ $f['value'] }}</span>
                        <span class="cx-sl">{{ $f['label'] }}</span>
                    </div>
                @endforeach
            </div>

            @if ($worst && ($worst['healthGroup'] ?? null) === 'risk')
                <button type="button" wire:click="activate({{ $worst['id'] }})" class="cx-firstup">
                    <span class="cx-fus">{{ $worst['healthScore'] ?? '—' }}</span>
                    <span style="min-width:0">
                        <span class="cx-fuk">Needs you first</span>
                        <span class="cx-fut">{{ $worst['name'] }}</span>
                    </span>
                </button>
            @else
                <p class="cx-hint">Pick a mission from the board, timeline or table — its detail opens here, in this same panel, whichever view you're reading.</p>
            @endif
        </div>
    @endif
</aside>
