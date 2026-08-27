@props(['mission', 'variant' => 'hero', 'href' => null, 'selectAction' => null, 'selected' => false])

@php
    $m = $mission;
    $name = $m['name'] ?? 'Untitled mission';
    $client = $m['client'] ?? null;
    $daysOut = $m['daysOut'] ?? null;
    $dateLabel = $m['shortDates'] ?? $m['dates'] ?? null;
    $where = $m['where'] ?? null;
    $type = $m['type'] ?? null;
    $stage = $m['statusLabel'] ?? null;
    $ready = is_null($m['progress'] ?? null) ? null : max(0, min(100, (int) $m['progress']));
    $healthScore = $m['healthScore'] ?? null;
    $healthGroup = $m['healthGroup'] ?? null; // risk | warn | ok | live | null
    $budgetLabel = $m['budgetLabel'] ?? null;
    $budgetOf = $m['budgetOf'] ?? null;
    $budgetPct = $m['budgetPct'] ?? null;
    $milestone = $m['milestone'] ?? null;

    $ownerRaw = $milestone['owner'] ?? null;
    $ownerName = is_object($ownerRaw) ? ($ownerRaw->name ?? null) : $ownerRaw;
    $ownerInitials = $ownerName ? collect(explode(' ', trim($ownerName)))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') : null;

    $resolvedHref = $href ?? (($m['event'] ?? null) && \Illuminate\Support\Facades\Route::has('events.hub')
        ? route('events.hub', $m['event'])
        : null);

    $healthTone = match ($healthGroup) {
        'risk' => 'risk', 'warn' => 'warn', 'live' => 'live', 'ok' => 'ok', default => null,
    };
    $healthPillLabel = match ($healthTone) {
        'risk' => 'At risk', 'warn' => 'Watch', 'live' => 'Live', 'ok' => 'On track', default => null,
    };
    $toneInk = fn (?string $tone) => match ($tone) {
        'ok' => 'var(--color-success)', 'warn' => 'var(--color-warning)',
        'risk' => 'var(--color-danger)', default => 'var(--color-info)',
    };
    $toneSoftClass = fn (?string $tone) => match ($tone) {
        'ok' => 'bg-success-soft text-success-ink', 'warn' => 'bg-warning-soft text-warning-ink',
        'risk' => 'bg-danger-soft text-danger-ink', default => 'bg-info-soft text-info-ink',
    };

    $urgency = $milestone['tone'] ?? (($milestone['overdue'] ?? false) ? 'overdue' : ($milestone ? 'ok' : null));
    [$urgencyLabel, $urgencyTone] = match ($urgency) {
        'overdue' => ['Overdue', 'risk'],
        'due-today' => ['Due today', 'warn'],
        'ok' => ['On track', 'ok'],
        default => [null, null],
    };

    $daysOutLabel = match (true) {
        $daysOut === null => null,
        $daysOut < 0 => 'In progress',
        $daysOut === 0 => 'Today',
        default => $daysOut.'d out',
    };

    $tag = $resolvedHref ? 'a' : 'div';
    $clickable = (bool) $selectAction && isset($m['id']);
    $cardTag = $clickable ? 'div' : $tag;
@endphp

@if ($variant === 'board')
    {{-- board — Mission Board's grid item. With a selectAction, clicking
         selects the card (the shared detail panel updates in place)
         instead of navigating away — only the "Open Event" link leaves
         the page, so scanning the board never costs you the board. --}}
    <{{ $cardTag }}
        @if ($clickable)
            wire:click="{{ $selectAction }}({{ $m['id'] }})" role="button" tabindex="0"
            aria-pressed="{{ $selected ? 'true' : 'false' }}" aria-label="Select {{ $name }}"
            wire:keydown.enter="{{ $selectAction }}({{ $m['id'] }})" wire:keydown.space.prevent="{{ $selectAction }}({{ $m['id'] }})"
        @endif
        @if (! $clickable && $resolvedHref) href="{{ $resolvedHref }}" @endif
        @class([
            'block rounded-lg border border-line bg-white p-3.5 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gold-400 focus-visible:ring-offset-2',
            'cursor-pointer' => $clickable,
            'hover:-translate-y-0.5 hover:shadow-float' => (bool) $resolvedHref || $clickable,
            'shadow-float ring-2 ring-gold-400/60' => $selected,
        ])
    >
        {{-- Header: a readiness ring gives each card an instant visual anchor
             (the same language the hero/compact variants use) instead of the
             twin grey stat boxes that made every board card tall and samey. --}}
        <div class="flex items-start gap-3">
            @if (! is_null($ready))
                <div class="ccx-ring h-11 w-11 shrink-0" style="--ccx-ring: var(--color-gold-500); --ccx-ring-pct: {{ $ready }}%" title="{{ $ready }}% ready">
                    <span class="ccx-ring-value !text-[10px]">{{ $ready }}</span>
                </div>
            @endif
            <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-2">
                    <h3 class="min-w-0 truncate text-[14.5px] font-bold text-ink">{{ $name }}</h3>
                    @if ($stage)<span class="shrink-0 rounded-full bg-info-soft px-2 py-0.5 text-[9.5px] font-bold text-info-ink">{{ $stage }}</span>@endif
                </div>
                @if ($client || $daysOutLabel)
                    <p class="mt-0.5 truncate text-[11px] text-muted">{{ collect([$client, $daysOutLabel])->filter()->implode(' · ') }}</p>
                @endif
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted">
            <span class="flex items-center gap-1"><x-icon name="calendar" class="h-3 w-3 shrink-0" />{{ $dateLabel ?? 'Date TBC' }}</span>
            <span class="flex min-w-0 items-center gap-1 truncate"><x-icon name="pin" class="h-3 w-3 shrink-0" />{{ $where ?? 'Venue TBC' }}</span>
        </div>

        {{-- Health + owner on one tight line — no grey box, just the read. --}}
        <div class="mt-3 flex items-center justify-between gap-2 border-t border-line pt-2.5 text-[11px]">
            <span class="flex min-w-0 items-center gap-1.5">
                <span class="text-eyebrow font-bold uppercase tracking-[0.1em] text-muted !text-[9px]">Health</span>
                <span class="h-1.5 w-1.5 shrink-0 rounded-full" style="background: {{ $toneInk($healthTone) }}"></span>
                <b class="tabular-nums text-ink">{{ $healthScore ?? '—' }}</b>
                @if ($healthPillLabel)<span class="truncate text-muted">· {{ $healthPillLabel }}</span>@endif
            </span>
            @if ($ownerInitials)
                <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-navy-900 text-[8.5px] font-bold text-white" title="Owner · {{ $ownerName }}">{{ $ownerInitials }}</span>
            @endif
        </div>

        @if ($budgetLabel)
            <div class="mt-2.5">
                <div class="flex items-baseline justify-between gap-2 text-[11px]">
                    <span class="text-eyebrow font-bold uppercase tracking-[0.1em] text-muted !text-[9px]">Budget</span>
                    <span class="truncate text-ink"><b class="font-bold">{{ $budgetLabel }}</b> <span class="text-muted">{{ $budgetOf }}</span></span>
                </div>
                @if (! is_null($budgetPct))
                    <div class="mt-1 h-1 overflow-hidden rounded-full bg-page">
                        <div class="h-full rounded-full bg-gold-500" style="width: {{ $budgetPct }}%"></div>
                    </div>
                @endif
            </div>
        @endif

        @if ($milestone)
            <div class="mt-2.5 flex items-center gap-2 rounded-md bg-page px-2.5 py-2">
                <span class="min-w-0 flex-1 truncate text-[11px] text-ink">{{ $milestone['title'] }}</span>
                @if ($urgencyLabel)<span class="shrink-0 rounded-full px-2 py-0.5 text-[9.5px] font-bold {{ $toneSoftClass($urgencyTone) }}">{{ $urgencyLabel }}</span>@endif
            </div>
        @endif

        @if ($resolvedHref)
            <a href="{{ $resolvedHref }}" wire:navigate @if ($clickable) onclick="event.stopPropagation()" @endif
               class="mt-3 flex items-center gap-1 border-t border-line pt-2.5 text-[11px] font-bold text-gold-700 transition hover:text-gold-800">
                Open Event <x-icon name="chevron" class="h-3 w-3 -rotate-90" />
            </a>
        @endif
    </{{ $cardTag }}>

@elseif ($variant === 'compact')
    <{{ $tag }} @if ($resolvedHref) href="{{ $resolvedHref }}" @endif
        class="flex items-center gap-3 rounded-lg border border-line bg-white p-3 transition hover:-translate-y-0.5 hover:shadow-float"
    >
        @if (! is_null($healthScore))
            <div class="ccx-ring h-9 w-9 shrink-0" style="--ccx-ring: {{ $toneInk($healthTone) }}; --ccx-ring-pct: {{ $healthScore }}%">
                <span class="ccx-ring-value !text-[9.5px]">{{ $healthScore }}</span>
            </div>
        @endif
        <div class="min-w-0 flex-1">
            <p class="truncate text-[12.5px] font-semibold text-ink">{{ $name }}</p>
            <p class="truncate text-[10.5px] text-muted">{{ $milestone['title'] ?? $where ?? '—' }}</p>
        </div>
        @if ($urgencyLabel)
            <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $toneSoftClass($urgencyTone) }}">{{ $urgencyLabel }}</span>
        @elseif ($healthPillLabel)
            <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $toneSoftClass($healthTone) }}">{{ $healthPillLabel }}</span>
        @endif
    </{{ $tag }}>
@else
    {{-- hero — the spotlight card for the nearest mission --}}
    <{{ $tag }} @if ($resolvedHref) href="{{ $resolvedHref }}" @endif
        class="block rounded-lg border border-line bg-white p-4 transition hover:-translate-y-0.5 hover:shadow-float focus:outline-none focus-visible:ring-2 focus-visible:ring-gold-400/50"
    >
        <div class="flex items-start gap-3">
            <div class="min-w-0 flex-1">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    @if ($type)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-2.5 py-1 text-[10.5px] font-bold uppercase tracking-wide text-gold-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> {{ $type }}
                        </span>
                    @endif
                    @if ($stage)
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold bg-info-soft text-info-ink">{{ $stage }}</span>
                    @elseif ($healthPillLabel)
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $toneSoftClass($healthTone) }}">{{ $healthPillLabel }}</span>
                    @endif
                </div>

                <h3 class="text-[16px] font-semibold text-ink">{{ $name }}</h3>

                @if ($where || $dateLabel || $client)
                    <p class="mt-1.5 text-[13px] text-muted">
                        {{ collect([$client, $where, $dateLabel])->filter()->implode(' · ') }}
                    </p>
                @endif
            </div>

            @if (! is_null($ready))
                <div class="ccx-ring shrink-0" style="--ccx-ring: var(--color-gold-500); --ccx-ring-pct: {{ $ready }}%" title="{{ $ready }}% readiness">
                    <span class="ccx-ring-value">{{ $ready }}</span>
                </div>
            @endif
        </div>

        @if (! is_null($ready))
            <div class="mt-4">
                <div class="mb-1.5 flex items-center justify-between">
                    <span class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Mission readiness</span>
                    <span class="text-[12px] font-bold tabular-nums text-gold-700">{{ $ready }}%</span>
                </div>
                <div class="h-1.5 overflow-hidden rounded-full bg-page">
                    <div class="h-full rounded-full bg-gradient-to-r from-gold-400 to-gold-600" style="width: {{ $ready }}%"></div>
                </div>
            </div>
        @endif

        @if ($budgetLabel || $daysOutLabel || $milestone)
            <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2 border-t border-line pt-3.5">
                @if ($daysOutLabel)
                    <div><span class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Days out</span><p class="text-[12.5px] font-bold text-ink">{{ $daysOutLabel }}</p></div>
                @endif
                @if ($budgetLabel)
                    <div><span class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Budget</span><p class="text-[12.5px] font-bold text-ink">{{ $budgetLabel }} <span class="font-normal text-muted">{{ $budgetOf }}</span></p></div>
                @endif
                @if ($milestone)
                    <div class="min-w-[160px] flex-1">
                        <span class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Next action</span>
                        <p class="flex items-center gap-2 text-[12.5px] font-bold text-ink">
                            <span class="min-w-0 truncate">{{ $milestone['title'] }}</span>
                            @if ($urgencyLabel)<span class="shrink-0 rounded-full px-2 py-0.5 text-[9.5px] font-bold {{ $toneSoftClass($urgencyTone) }}">{{ $urgencyLabel }}</span>@endif
                        </p>
                    </div>
                @endif
            </div>
        @endif
    </{{ $tag }}>
@endif
