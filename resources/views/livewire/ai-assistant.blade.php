<div class="space-y-4">

    <x-eo.intelligence-header active="briefing" title="Command Briefing"
        subtitle="What needs a person today, across every event — and where to find it."
        :attentionCount="$counts['critical'] + $counts['warning']" />

    {{-- ══ The briefing ══
         The one dark plate on this page, because this is the one line you are
         meant to read before anything else. --}}
    <div class="relative overflow-hidden rounded-[22px] px-5 py-5 text-white lg:px-6"
         style="background: linear-gradient(165deg, var(--color-eo-navy-mid) 0%, var(--color-eo-navy) 55%, var(--color-eo-navy-deep) 100%); box-shadow: var(--shadow-eo-float);">
        <div class="pointer-events-none absolute -right-8 -top-14 h-44 w-44 rounded-full" style="background: radial-gradient(circle, rgba(214,174,52,0.26), transparent 70%);"></div>

        <div class="relative flex flex-wrap items-center gap-x-6 gap-y-4">
            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-white/10 text-eo-gold-soft ring-1 ring-white/10">
                <x-icon name="sparkles" class="h-6 w-6" />
            </span>

            <div class="min-w-0 flex-1">
                <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-eo-gold-soft/90">Today's briefing</p>
                <p class="mt-1.5 text-[19px] font-bold leading-snug text-white lg:text-[22px]">{{ $headline }}</p>
                <p class="mt-1.5 text-[11px] text-white/45">
                    {{ now()->format('l, j F') }} · Rule-based, from your own records — every line below names the one it came from.
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                @foreach ([['critical', 'Critical', 'var(--color-eo-risk)'], ['warning', 'Watch', 'var(--color-eo-warn)'], ['info', 'To note', 'rgba(255,255,255,.55)']] as [$key, $label, $hex])
                    <div class="flex h-[52px] min-w-[4.6rem] flex-col justify-center rounded-xl bg-black/20 px-2.5 text-center ring-1 ring-white/10">
                        <span class="text-lg font-black leading-none" style="color: {{ $counts[$key] ? $hex : 'rgba(255,255,255,.35)' }}">{{ $counts[$key] }}</span>
                        <span class="mt-0.5 text-[10px] font-bold uppercase tracking-wider text-white/50">{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid gap-4 2xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="min-w-0 space-y-4">

            {{-- how much noise you want --}}
            <div class="eo-soft-card flex flex-wrap items-center gap-x-3 gap-y-2 px-3 py-2.5">
                <div class="flex flex-wrap items-center gap-1">
                    @foreach (['all' => 'Everything', 'warning' => 'Needs action', 'critical' => 'Critical only'] as $key => $label)
                        <button type="button" wire:click="setFilter('{{ $key }}')"
                                @class([
                                    'rounded-full px-3.5 py-1.5 text-xs font-bold transition',
                                    'bg-eo-navy text-white' => $filter === $key,
                                    'text-eo-muted hover:bg-eo-bg hover:text-eo-text' => $filter !== $key,
                                ])>{{ $label }}</button>
                    @endforeach
                </div>
                <p class="ms-auto text-[11px] text-eo-muted">{{ $items->count() }} of {{ $counts['all'] }} shown</p>
            </div>

            @if ($items->isEmpty())
                <x-eo.empty-state icon="sparkles" title="Nothing to report"
                         hint="No open risk, no pending approval, no overdue task, nothing missing that should be here by now." />
            @else
                <div class="eo-soft-card divide-y divide-eo-line">
                    @foreach ($items as $item)
                        @php
                            [$dot, $tone] = match ($item['tone']) {
                                'red' => ['bg-eo-risk', 'eo-pill-risk'],
                                'amber' => ['bg-eo-warn', 'eo-pill-warn'],
                                default => ['bg-eo-muted', 'eo-pill-pending'],
                            };
                        @endphp
                        <a href="{{ $item['href'] }}" class="flex items-start gap-3 px-4 py-3 transition hover:bg-eo-workspace">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $dot }}"></span>

                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-baseline gap-x-2">
                                    <span class="text-[13px] font-bold text-eo-text">{{ $item['title'] }}</span>
                                    <span class="eo-pill {{ $tone }} !text-[9px] uppercase tracking-[0.1em]">{{ $item['severity'] }}</span>
                                </span>
                                {{-- The source, always. An assistant that says something
                                     is wrong without saying where sends you round all
                                     twenty tabs anyway. --}}
                                <span class="mt-0.5 block truncate text-[11.5px] text-eo-muted">{{ $item['why'] }}</span>
                                <span class="mt-0.5 block truncate text-[11px] font-semibold text-eo-muted">{{ $item['where'] }}</span>
                            </span>

                            <span class="shrink-0 self-center text-[11px] font-bold text-eo-muted">→</span>
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- one event's own briefing, on demand --}}
            @if ($focus && $focusSummary)
                <div class="eo-soft-card p-4">
                    <div class="flex flex-wrap items-baseline gap-2">
                        <h3 class="text-[15px] font-bold text-eo-text">{{ $focus->name }}</h3>
                        <button type="button" wire:click="focus(null)" class="ms-auto text-[11px] font-semibold text-eo-muted transition hover:text-eo-text">Close</button>
                    </div>
                    <p class="mt-1 text-[12.5px] text-eo-text">{{ $focusSummary['headline'] }}</p>

                    @if ($focusSummary['attention'])
                        <ul class="mt-3 space-y-1.5 border-t border-eo-line pt-3">
                            @foreach ($focusSummary['attention'] as $line)
                                <li class="flex gap-2 text-[11.5px] text-eo-text"><span class="text-eo-gold">·</span>{{ $line }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <x-eo.button href="{{ route('events.hub', $focus) }}" size="sm" class="mt-3">Open command center →</x-eo.button>
                </div>
            @endif
        </div>

        {{-- ══ Where it is coming from ══ --}}
        <aside class="space-y-4">
            <div class="eo-soft-card overflow-hidden">
                <div class="border-b border-eo-line px-4 py-3">
                    <h3 class="text-[14px] font-bold text-eo-text">Where it is coming from</h3>
                    <p class="text-[11px] text-eo-muted">Flags per event — open one for its own briefing.</p>
                </div>
                <div class="divide-y divide-eo-line">
                    @forelse ($events as $event)
                        @php $n = $byEvent[$event->name] ?? 0; @endphp
                        <button type="button" wire:click="focus({{ $event->id }})"
                                class="flex w-full items-center gap-3 px-4 py-2.5 text-left transition hover:bg-eo-workspace {{ $focusId === $event->id ? 'bg-eo-workspace' : '' }}">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[12px] font-bold text-eo-text">{{ $event->name }}</span>
                                <span class="block truncate text-[10.5px] text-eo-muted">{{ $event->starts_at?->format('j M Y') ?? 'No dates' }}</span>
                            </span>
                            <span @class([
                                'eo-pill',
                                'eo-pill-risk' => $n > 3,
                                'eo-pill-warn' => $n > 0 && $n <= 3,
                                'eo-pill-pending' => $n === 0,
                            ])>{{ $n ?: '—' }}</span>
                        </button>
                    @empty
                        <p class="px-4 py-6 text-center text-[11.5px] text-eo-muted">No events yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="eo-soft-card p-4">
                <p class="eo-label">How this works</p>
                <p class="mt-1.5 text-[11.5px] leading-relaxed text-eo-muted">
                    These are rules, not a model: open risks over 15/25, approvals waiting on a
                    decision, tasks past their date, suppliers flagged, movements without a driver,
                    and the things that should exist by now and do not. Each line reads straight off
                    the record and links to it, so you can check every one.
                </p>
            </div>
        </aside>
    </div>
</div>
