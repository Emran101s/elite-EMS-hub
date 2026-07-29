<div class="space-y-4">

    {{-- ══ The briefing ══
         The one dark plate on this page, because this is the one line you are
         meant to read before anything else. --}}
    <div class="strip-dark relative overflow-hidden px-5 py-5 lg:px-6">
        <div class="pointer-events-none absolute -right-8 -top-14 h-44 w-44 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.26),transparent_70%)]"></div>

        <div class="relative flex flex-wrap items-center gap-x-6 gap-y-4">
            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-white/10 text-gold-400 ring-1 ring-white/10">
                <x-icon name="sparkles" class="h-6 w-6" />
            </span>

            <div class="min-w-0 flex-1">
                <p class="text-eyebrow font-bold uppercase tracking-[0.24em] text-gold-300/90">Today's briefing</p>
                <p class="pf mt-1.5 text-[19px] font-bold leading-snug text-white lg:text-[22px]">{{ $headline }}</p>
                <p class="mt-1.5 text-[11px] text-white/45">
                    {{ now()->format('l, j F') }} · Rule-based, from your own records — every line below names the one it came from.
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                @foreach ([['critical', 'Critical', 'var(--color-danger-on-dark)'], ['warning', 'Watch', 'var(--color-warning-on-dark)'], ['info', 'To note', 'rgba(255,255,255,.55)']] as [$key, $label, $hex])
                    <div class="flex h-[52px] min-w-[4.6rem] flex-col justify-center rounded-xl bg-black/20 px-2.5 text-center ring-1 ring-white/10">
                        <span class="text-lg font-black leading-none" style="color: {{ $counts[$key] ? $hex : 'rgba(255,255,255,.35)' }}">{{ $counts[$key] }}</span>
                        <span class="mt-0.5 text-eyebrow font-bold uppercase tracking-wider text-white/50">{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid gap-4 2xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="min-w-0 space-y-4">

            {{-- how much noise you want --}}
            <div class="card flex flex-wrap items-center gap-x-3 gap-y-2 px-3 py-2.5">
                <div class="flex flex-wrap items-center gap-1">
                    @foreach (['all' => 'Everything', 'warning' => 'Needs action', 'critical' => 'Critical only'] as $key => $label)
                        <button type="button" wire:click="setFilter('{{ $key }}')"
                                @class([
                                    'rounded-full px-3.5 py-1.5 text-xs font-bold transition',
                                    'bg-navy-950 text-white' => $filter === $key,
                                    'text-navy-500 hover:bg-navy-50 hover:text-navy-900' => $filter !== $key,
                                ])>{{ $label }}</button>
                    @endforeach
                </div>
                <p class="ms-auto text-[11px] text-muted">{{ $items->count() }} of {{ $counts['all'] }} shown</p>
            </div>

            @if ($items->isEmpty())
                <x-empty icon="sparkles" title="Nothing to report"
                         hint="No open risk, no pending approval, no overdue task, nothing missing that should be here by now." />
            @else
                <div class="card divide-y divide-line">
                    @foreach ($items as $item)
                        @php
                            [$dot, $chip] = match ($item['tone']) {
                                'red' => ['bg-risk', 'bg-red-100 text-red-700'],
                                'amber' => ['bg-warn', 'bg-amber-100 text-amber-700'],
                                default => ['bg-navy-300', 'bg-navy-50 text-navy-500'],
                            };
                        @endphp
                        <a href="{{ $item['href'] }}" class="flex items-start gap-3 px-4 py-3 transition hover:bg-page/50">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $dot }}"></span>

                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-baseline gap-x-2">
                                    <span class="text-[13px] font-bold text-navy-950">{{ $item['title'] }}</span>
                                    <span class="rounded-full px-1.5 py-0.5 text-[9px] font-black uppercase tracking-[0.1em] {{ $chip }}">{{ $item['severity'] }}</span>
                                </span>
                                {{-- The source, always. An assistant that says something
                                     is wrong without saying where sends you round all
                                     twenty tabs anyway. --}}
                                <span class="mt-0.5 block truncate text-[11.5px] text-muted">{{ $item['why'] }}</span>
                                <span class="mt-0.5 block truncate text-[11px] font-semibold text-navy-500">{{ $item['where'] }}</span>
                            </span>

                            <span class="shrink-0 self-center text-[11px] font-bold text-navy-300">→</span>
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- one event's own briefing, on demand --}}
            @if ($focus && $focusSummary)
                <div class="card p-4">
                    <div class="flex flex-wrap items-baseline gap-2">
                        <h3 class="pf text-[15px] font-bold text-navy-950">{{ $focus->name }}</h3>
                        <button type="button" wire:click="focus(null)" class="ms-auto text-[11px] font-semibold text-navy-400 transition hover:text-navy-900">Close</button>
                    </div>
                    <p class="mt-1 text-[12.5px] text-navy-700">{{ $focusSummary['headline'] }}</p>

                    @if ($focusSummary['attention'])
                        <ul class="mt-3 space-y-1.5 border-t border-line pt-3">
                            @foreach ($focusSummary['attention'] as $line)
                                <li class="flex gap-2 text-[11.5px] text-navy-700"><span class="text-gold-600">·</span>{{ $line }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <a href="{{ route('events.hub', $focus) }}" class="mt-3 inline-flex h-9 items-center rounded-xl bg-navy-950 px-3.5 text-[11.5px] font-bold text-white transition hover:bg-navy-800">Open command center →</a>
                </div>
            @endif
        </div>

        {{-- ══ Where it is coming from ══ --}}
        <aside class="space-y-4">
            <div class="card overflow-hidden">
                <div class="border-b border-line px-4 py-3">
                    <h3 class="pf text-[14px] font-bold text-navy-950">Where it is coming from</h3>
                    <p class="text-[11px] text-muted">Flags per event — open one for its own briefing.</p>
                </div>
                <div class="divide-y divide-line">
                    @forelse ($events as $event)
                        @php $n = $byEvent[$event->name] ?? 0; @endphp
                        <button type="button" wire:click="focus({{ $event->id }})"
                                class="flex w-full items-center gap-3 px-4 py-2.5 text-left transition hover:bg-page/60 {{ $focusId === $event->id ? 'bg-page/60' : '' }}">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[12px] font-bold text-navy-900">{{ $event->name }}</span>
                                <span class="block truncate text-[10.5px] text-muted">{{ $event->starts_at?->format('j M Y') ?? 'No dates' }}</span>
                            </span>
                            <span @class([
                                'shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold tabular-nums',
                                'bg-red-100 text-red-700' => $n > 3,
                                'bg-amber-100 text-amber-700' => $n > 0 && $n <= 3,
                                'bg-navy-50 text-navy-300' => $n === 0,
                            ])>{{ $n ?: '—' }}</span>
                        </button>
                    @empty
                        <p class="px-4 py-6 text-center text-[11.5px] text-muted">No events yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="card p-4">
                <p class="eyebrow">How this works</p>
                <p class="mt-1.5 text-[11.5px] leading-relaxed text-muted">
                    These are rules, not a model: open risks over 15/25, approvals waiting on a
                    decision, tasks past their date, suppliers flagged, movements without a driver,
                    and the things that should exist by now and do not. Each line reads straight off
                    the record and links to it, so you can check every one.
                </p>
            </div>
        </aside>
    </div>
</div>
