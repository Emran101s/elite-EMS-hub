<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $event->name }} — Plan</title>
    <style>{!! $css !!}</style>
    <style>
        @page { size: A4; margin: 0; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .avoid-break { break-inside: avoid; }
    </style>
</head>
<body class="bg-white text-navy-900">
    @php
        $all = $tracks->flatMap->items;
        $total = $all->count();
        $done = $all->where('status', 'done')->count();
        $signed = $all->filter(fn ($i) => $i->isSigned())->count();
        $overdue = $all->filter(fn ($i) => $i->isOverdue())->count();
        $needApproval = $all->where('status', 'needs_approval')->count();
        $progress = $total ? (int) round($all->avg(fn ($i) => $i->progress())) : 0;
        $ring = 2 * M_PI * 30;
        $ini = fn ($n) => \Illuminate\Support\Str::of($n)->explode(' ')->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    @endphp

    {{-- ═══ Cover header ═══ --}}
    <div class="relative overflow-hidden bg-gradient-to-br from-navy-900 to-[#061225] px-10 py-9 text-white">
        <div class="pointer-events-none absolute -right-10 -top-16 h-56 w-56 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.28),transparent_70%)]"></div>
        <div class="relative flex items-center gap-6">
            <div class="relative shrink-0">
                <svg class="h-[86px] w-[86px] -rotate-90" viewBox="0 0 70 70">
                    <circle cx="35" cy="35" r="30" fill="none" stroke="rgba(255,255,255,.14)" stroke-width="7"/>
                    <circle cx="35" cy="35" r="30" fill="none" stroke="#D4AF37" stroke-width="7" stroke-linecap="round" stroke-dasharray="{{ $ring }}" stroke-dashoffset="{{ $ring - ($ring * $progress / 100) }}"/>
                </svg>
                <span class="absolute inset-0 flex items-center justify-center text-lg font-black text-white">{{ $progress }}%</span>
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-3xs font-bold uppercase tracking-[0.28em] text-gold-300">Event Delivery Plan</p>
                <h1 class="pf mt-1 text-3xl font-black leading-tight text-white" style="font-family:'Spectral',Georgia,serif">{{ $event->name }}</h1>
                <p class="mt-1 text-xs text-white/55">
                    {{ $tracks->count() }} phases · {{ $total }} deliverables
                    @if ($event->starts_at) · Event date {{ $event->starts_at->format('d M Y') }} @endif
                    · Generated {{ now()->format('d M Y') }}
                </p>
            </div>
            <div class="flex shrink-0 gap-2">
                @foreach ([['Done', $done, '#22C55E'], ['Awaiting sign-off', $needApproval, '#F97316'], ['Overdue', $overdue, '#F87171']] as [$lbl, $val, $hex])
                    <div class="w-20 rounded-xl bg-white/[0.07] px-2 py-2.5 text-center ring-1 ring-white/10">
                        <div class="text-2xl font-black" style="color: {{ $val > 0 ? $hex : 'rgba(255,255,255,.4)' }}">{{ $val }}</div>
                        <div class="mt-1 text-[0.46rem] font-bold uppercase leading-tight tracking-wider text-white/55">{{ $lbl }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ═══ Tracks ═══ --}}
    <div class="px-10 py-8">
        @foreach ($tracks as $t)
            @php
                $items = $t->items;
                $tt = $items->count();
                $td = $items->where('status', 'done')->count();
                $tp = $tt ? (int) round($items->avg(fn ($i) => $i->progress())) : 0;
                $color = $t->color ?? '#3B82F6';
            @endphp
            <section class="avoid-break mb-7">
                {{-- track header --}}
                <div class="mb-3 flex items-center gap-3 border-b-2 pb-2" style="border-color: {{ $color }}">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-sm font-black text-white" style="background: {{ $color }}">{{ $loop->iteration }}</span>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-base font-black uppercase tracking-wide text-navy-900">{{ $t->name }}</h2>
                        @if ($t->goal)<p class="text-[0.7rem] italic text-navy-500">{{ $t->goal }}</p>@endif
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <div class="h-1.5 w-24 overflow-hidden rounded-full bg-navy-100"><div class="h-full rounded-full" style="width: {{ $tp }}%; background: {{ $color }}"></div></div>
                        <span class="text-[0.62rem] font-bold text-navy-500">{{ $td }}/{{ $tt }} · {{ $tp }}%</span>
                    </div>
                </div>

                @forelse ($items as $item)
                    @php
                        $isMs = str_contains($item->title, '(Milestone)');
                        [$sd, $st] = $item->subtaskProgress();
                        $hex = $item->statusHex();
                    @endphp
                    <div class="avoid-break mb-2 rounded-lg border border-line px-3 py-2 {{ $isMs ? 'bg-gold-50' : 'bg-white' }}">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $hex }}"></span>
                            <span class="flex-1 text-[0.82rem] font-bold text-navy-900">{{ $item->title }}</span>
                            @if ($item->isSigned())<span class="flex h-4 w-4 items-center justify-center rounded-full bg-gold-500 text-[0.5rem] font-black text-navy-900">✓</span>@endif
                            <span class="rounded-md px-2 py-0.5 text-[0.55rem] font-bold text-white" style="background: {{ $hex }}">{{ $item->statusLabel() }}</span>
                        </div>
                        <div class="mt-1 flex items-center gap-3 pl-[18px] text-[0.6rem] text-navy-400">
                            <span class="font-semibold" style="color: {{ $item->priorityHex() }}">{{ $item->priorityLabel() }}</span>
                            @if ($item->start_on || $item->due_on)
                                <span>{{ $item->start_on?->format('d M') ?? '—' }} → <span class="{{ $item->isOverdue() ? 'font-bold text-red-600' : '' }}">{{ $item->due_on?->format('d M') ?? '—' }}</span></span>
                            @endif
                            @if ($item->owners->isNotEmpty())<span>{{ $item->owners->pluck('name')->map(fn ($n) => \Illuminate\Support\Str::before($n, ' '))->join(', ') }}</span>@endif
                            @if ($st)<span class="ml-auto font-bold text-navy-500">{{ $sd }}/{{ $st }} subtasks · {{ $item->progress() }}%</span>@endif
                        </div>

                        @if ($item->subtasks->isNotEmpty())
                            <div class="mt-1.5 grid grid-cols-2 gap-x-4 gap-y-0.5 pl-[18px]">
                                @foreach ($item->subtasks as $sub)
                                    <div class="flex items-center gap-1.5 text-[0.64rem] {{ $sub->is_done ? 'text-navy-400' : 'text-navy-700' }}">
                                        <span class="flex h-3 w-3 shrink-0 items-center justify-center rounded-[3px] text-[0.42rem] text-white {{ $sub->is_done ? 'bg-emerald-500' : 'border border-navy-300' }}">{{ $sub->is_done ? '✓' : '' }}</span>
                                        <span class="{{ $sub->is_done ? 'line-through' : '' }}">{{ $sub->title }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-[0.7rem] italic text-navy-300">No items in this phase.</p>
                @endforelse
            </section>
        @endforeach
    </div>
</body>
</html>
