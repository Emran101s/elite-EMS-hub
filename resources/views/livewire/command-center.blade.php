<div>
    @php
        $ring = 2 * M_PI * 30;
        $sevHex = ['critical' => 'var(--color-danger)', 'warn' => 'var(--color-warning)'];
    @endphp

    {{-- ══════════ PULSE ══════════ --}}
    <div class="strip-dark relative mb-4 flex flex-wrap items-center gap-x-9 gap-y-5 overflow-hidden px-6 py-5">
        <div class="pointer-events-none absolute -right-10 -top-16 h-52 w-52 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.28),transparent_70%)]"></div>

        <div class="relative flex shrink-0 items-center gap-4">
            <div class="relative">
                <svg class="h-[84px] w-[84px] -rotate-90" viewBox="0 0 70 70">
                    <circle cx="35" cy="35" r="30" fill="none" stroke="rgba(255,255,255,.12)" stroke-width="7"/>
                    <circle cx="35" cy="35" r="30" fill="none" stroke="{{ $pulse['health'] >= 70 ? 'var(--color-success-on-dark)' : ($pulse['health'] >= 45 ? 'var(--color-warning-on-dark)' : 'var(--color-danger-on-dark)') }}"
                            stroke-width="7" stroke-linecap="round" stroke-dasharray="{{ $ring }}" stroke-dashoffset="{{ $ring - ($ring * $pulse['health'] / 100) }}"/>
                </svg>
                <span class="absolute inset-0 flex items-center justify-center text-base font-black text-white">{{ $pulse['health'] }}%</span>
            </div>
            <div>
                <p class="text-eyebrow font-bold uppercase tracking-[0.2em] text-gold-300">
                    Operations Room · Welcome back, {{ str(auth()->user()->name)->before(' ') }}
                </p>
                <p class="pf text-2xl font-black leading-none text-white">
                    {{ $pulse['signals'] }} <span class="text-base font-bold text-white/45">{{ str('signal')->plural($pulse['signals']) }}</span>
                </p>
                <p class="mt-1 text-eyebrow font-semibold {{ $pulse['critical'] > 0 ? 'text-red-300' : 'text-emerald-300' }}">
                    {{ $pulse['critical'] > 0 ? $pulse['critical'].' need you today' : 'nothing critical — you are clear' }}
                </p>
            </div>
        </div>

        <div class="relative flex flex-1 flex-wrap items-center gap-2">
            @foreach ([['Events', $pulse['events'], 'white'], ['Live now', $pulse['live'], 'var(--color-success-on-dark)'], ['At risk', $pulse['atRisk'], 'var(--color-danger-on-dark)']] as [$lbl, $val, $hex])
                <div class="flex min-w-[5rem] flex-1 flex-col items-center rounded-xl bg-white/[0.06] py-2 ring-1 ring-white/10">
                    <span class="text-xl font-black leading-none" style="color: {{ $val > 0 ? $hex : 'rgba(255,255,255,.4)' }}">{{ $val }}</span>
                    <span class="mt-1 text-eyebrow font-bold uppercase tracking-wider text-white/45">{{ $lbl }}</span>
                </div>
            @endforeach
        </div>

        <a href="{{ route('events.index') }}" class="btn-gold relative h-10 shrink-0 !rounded-xl px-5 text-xs">All events →</a>
    </div>

    {{-- ══════════ WORKSPACE CARDS ══════════ --}}
    @php
        $money = fn ($cents) => \App\Models\Event::moneyIn((int) $cents, $w['currency']);
        $donutC = 2 * M_PI * 42;          // donut circumference
        $donutOffset = 0;
        $nextRing = 2 * M_PI * 34;
        $nextPct = $w['nextTotal'] ? (int) round($w['nextDone'] / $w['nextTotal'] * 100) : 0;
    @endphp

    <div class="mb-4 grid gap-4 xl:grid-cols-4">

        {{-- 1 · Countdown --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-navy-900 to-[var(--color-navy-950)] p-5 text-white">
            <div class="pointer-events-none absolute -right-10 -top-14 h-44 w-44 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.30),transparent_70%)]"></div>
            <p class="relative text-eyebrow font-black uppercase tracking-[0.22em] text-gold-300">Event progress</p>

            @if ($w['next'])
                <div class="relative mt-3 flex items-start justify-between gap-3">
                    <div>
                        <p class="pf text-[44px] font-black leading-none text-gold-400">{{ max($w['nextDays'], 0) }}</p>
                        <p class="mt-1 text-eyebrow font-bold uppercase tracking-[0.18em] text-white/55">days to go</p>
                        <p class="mt-2 text-micro text-white/45">{{ $w['next']->starts_at?->format('l, j F Y') }}</p>
                    </div>
                    <div class="relative shrink-0">
                        <svg class="h-[86px] w-[86px] -rotate-90" viewBox="0 0 80 80">
                            <circle cx="40" cy="40" r="34" fill="none" stroke="rgba(255,255,255,.12)" stroke-width="8"/>
                            <circle cx="40" cy="40" r="34" fill="none" stroke="var(--color-gold-500)" stroke-width="8" stroke-linecap="round"
                                    stroke-dasharray="{{ $nextRing }}" stroke-dashoffset="{{ $nextRing - ($nextRing * $nextPct / 100) }}"/>
                        </svg>
                        <span class="absolute inset-0 flex items-center justify-center text-sm font-black text-white">{{ $nextPct }}%</span>
                    </div>
                </div>
                <p class="relative mt-1 text-right text-eyebrow text-white/40">{{ $w['nextDone'] }} of {{ $w['nextTotal'] }} done</p>

                <a href="{{ route('events.hub', $w['next']) }}" class="relative mt-3 block truncate text-sm font-bold text-white transition hover:text-gold-300">{{ $w['next']->name }} →</a>

                <div class="relative mt-3 grid grid-cols-3 gap-1.5 border-t border-white/10 pt-3">
                    @foreach ([['Overdue', $w['nextOverdue'], 'var(--color-danger-on-dark)'], ['Due 7d', $w['nextDue7'], 'var(--color-warning-on-dark)'], ['Unassigned', $w['nextUnassigned'], 'var(--color-info-on-dark)']] as [$l, $v, $c])
                        <div><p class="text-base font-black leading-none" style="color: {{ $v > 0 ? $c : 'rgba(255,255,255,.35)' }}">{{ $v }}</p>
                        <p class="mt-1 text-eyebrow font-bold uppercase tracking-wider text-white/45">{{ $l }}</p></div>
                    @endforeach
                </div>
            @else
                <p class="relative mt-6 text-sm text-white/50">No active events yet.</p>
            @endif
        </div>

        {{-- 2 · Delivery journey --}}
        <div class="rounded-2xl border border-line bg-white p-5">
            <p class="text-eyebrow font-black uppercase tracking-[0.22em] text-navy-400">Delivery journey</p>
            <p class="mt-1 text-micro text-muted">{{ $w['tracks']->count() }} phases · {{ $w['next']?->name ?? 'no event' }}</p>

            <div class="mt-3 space-y-2">
                @forelse ($w['tracks'] as $i => $t)
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg text-eyebrow font-black text-white" style="background: {{ $t['color'] }}">{{ $i + 1 }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-baseline justify-between gap-2">
                                <span class="truncate text-micro font-bold text-navy-800">{{ \Illuminate\Support\Str::limit($t['name'], 22) }}</span>
                                <span class="shrink-0 text-eyebrow font-black text-navy-400">{{ $t['done'] }}/{{ $t['total'] }}</span>
                            </span>
                            <span class="mt-1 block h-1.5 overflow-hidden rounded-full bg-navy-50">
                                <span class="block h-full rounded-full" style="width: {{ max($t['pct'], $t['pct'] > 0 ? 4 : 0) }}%; background: {{ $t['color'] }}"></span>
                            </span>
                        </span>
                    </div>
                @empty
                    <p class="py-6 text-center text-micro italic text-navy-300">No plan phases yet.</p>
                @endforelse
            </div>

            @if ($w['next'])
                <a href="{{ route('events.hub', [$w['next'], 'tab' => 'planning']) }}" class="mt-3 block rounded-xl border border-gold-300 bg-gold-50 py-2 text-center text-micro font-bold text-gold-700 transition hover:bg-gold-100">Open Plan Studio →</a>
            @endif
        </div>

        {{-- 3 · Team & assignments --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-navy-900 to-[var(--color-navy-950)] p-5 text-white">
            <div class="pointer-events-none absolute -right-8 -top-12 h-40 w-40 rounded-full bg-[radial-gradient(circle,rgba(96,165,250,0.22),transparent_70%)]"></div>
            <p class="relative text-eyebrow font-black uppercase tracking-[0.22em] text-gold-300">Team &amp; assignments</p>

            <div class="relative mt-3 flex items-end justify-between">
                <p class="pf text-2xl font-black leading-none text-white">{{ $w['teamSize'] }} <span class="text-sm font-bold text-white/40">people</span></p>
                <div class="text-right">
                    <p class="text-xl font-black leading-none {{ $w['unassigned'] > 0 ? 'text-amber-300' : 'text-white/40' }}">{{ $w['unassigned'] }}</p>
                    <p class="text-eyebrow font-bold uppercase tracking-wider text-white/45">unassigned</p>
                </div>
            </div>

            <div class="relative mt-4 space-y-2">
                @forelse ($w['people'] as $p)
                    @php $ini = \Illuminate\Support\Str::of($p['user']->name)->explode(' ')->filter()->map(fn ($x) => mb_substr($x, 0, 1))->take(2)->implode(''); @endphp
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/10 text-eyebrow font-bold text-gold-300 ring-1 ring-white/15">{{ $ini }}</span>
                        <span class="min-w-0 flex-1 truncate text-micro font-semibold text-white/85">{{ $p['user']->name }}</span>
                        <span class="shrink-0 rounded-full bg-white/10 px-2 py-0.5 text-eyebrow font-bold text-white/70">{{ $p['count'] }} open</span>
                    </div>
                @empty
                    <p class="py-4 text-center text-micro text-white/40">Nothing assigned yet.</p>
                @endforelse
            </div>

            <a href="{{ route('team.index') }}" class="relative mt-4 block rounded-xl bg-white/[0.07] py-2 text-center text-micro font-bold text-white/80 ring-1 ring-white/10 transition hover:bg-white/10">View all members →</a>
        </div>

        {{-- 4 · At a glance --}}
        <div class="rounded-2xl border border-line bg-white p-5">
            <p class="text-eyebrow font-black uppercase tracking-[0.22em] text-navy-400">At a glance</p>
            <div class="mt-3 grid grid-cols-2 gap-2">
                @foreach ([
                    ['Active tasks', $w['taskTotal'] - $w['taskDone'], 'var(--color-info)', 'clipboard', route('tasks.index')],
                    ['Risks open', $w['risks'], 'var(--color-danger)', 'bell', null],
                    ['Approvals', $w['approvals'], \App\Models\Event::moduleColor('approvals'), 'identification', null],
                    ['Sessions', $w['sessions'], \App\Models\Event::moduleColor('agenda'), 'calendar', null],
                    ['Suppliers', $w['suppliers'], 'var(--color-success)', 'truck', null],
                    ['Team', $w['teamSize'], 'var(--color-gold-500)', 'users', route('team.index')],
                ] as [$l, $v, $c, $ic, $href])
                    <div class="rounded-xl border border-line p-2.5">
                        <div class="flex items-center gap-1.5">
                            <span class="flex h-6 w-6 items-center justify-center rounded-lg" style="background: {{ $c }}1A; color: {{ $c }}"><x-icon :name="$ic" class="h-3.5 w-3.5" /></span>
                            <span class="pf text-lg font-black leading-none text-navy-900">{{ $v }}</span>
                        </div>
                        <p class="mt-1.5 text-eyebrow font-bold uppercase tracking-wider text-muted">{{ $l }}</p>
                    </div>
                @endforeach
            </div>
            <a href="{{ route('events.index') }}" class="mt-3 block rounded-xl bg-navy-900 py-2 text-center text-micro font-bold text-white transition hover:bg-navy-800">Open portfolio →</a>
        </div>
    </div>

    {{-- ══════════ SECOND ROW ══════════ --}}
    <div class="mb-4 grid gap-4 xl:grid-cols-4">

        {{-- 5 · Priority pyramid --}}
        <div class="rounded-2xl border border-line bg-white p-5">
            <p class="text-eyebrow font-black uppercase tracking-[0.22em] text-navy-400">Smart priority</p>
            <p class="pf mt-1.5 text-base font-bold leading-snug text-navy-900">Focus on what matters most</p>
            <div class="mt-3 space-y-1.5">
                @foreach ([['urgent', 'Critical', 'var(--color-danger)', 100], ['high', 'High', 'var(--color-warning)', 84], ['normal', 'Medium', 'var(--color-warning-on-dark)', 68], ['low', 'Low', 'var(--color-info)', 52]] as $i => [$key, $label, $hex, $width])
                    <div class="mx-auto flex items-center justify-between rounded-lg px-3 py-2 text-white shadow-sm" style="width: {{ $width }}%; background: {{ $hex }}">
                        <span class="text-eyebrow font-black">{{ $label }}</span>
                        <span class="text-micro font-black">{{ $w['byPriority'][$key] ?? 0 }}</span>
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-eyebrow leading-relaxed text-muted">Open tasks ranked by priority across every active event.</p>
        </div>

        {{-- 6 · Task overview donut --}}
        <div class="rounded-2xl border border-line bg-white p-5">
            <p class="text-eyebrow font-black uppercase tracking-[0.22em] text-navy-400">Task overview</p>
            <div class="mt-3 flex items-center gap-4">
                <div class="relative shrink-0">
                    <svg class="h-[104px] w-[104px] -rotate-90" viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="42" fill="none" stroke="var(--color-page)" stroke-width="12"/>
                        @foreach (\App\Models\Task::STAGES as $sk => [$sl, $sh])
                            @php
                                $cnt = $w['byStage'][$sk] ?? 0;
                                $frac = $w['taskTotal'] > 0 ? $cnt / $w['taskTotal'] : 0;
                                $len = $donutC * $frac;
                            @endphp
                            @if ($cnt > 0)
                                <circle cx="50" cy="50" r="42" fill="none" stroke="{{ $sh }}" stroke-width="12"
                                        stroke-dasharray="{{ $len }} {{ $donutC - $len }}" stroke-dashoffset="{{ -$donutOffset }}"/>
                                @php $donutOffset += $len; @endphp
                            @endif
                        @endforeach
                    </svg>
                    <span class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="pf text-xl font-black leading-none text-navy-900">{{ $w['taskTotal'] }}</span>
                        <span class="text-eyebrow font-bold uppercase tracking-wider text-muted">total</span>
                    </span>
                </div>
                <div class="min-w-0 flex-1 space-y-1">
                    @foreach (\App\Models\Task::STAGES as $sk => [$sl, $sh])
                        <div class="flex items-center gap-1.5 text-eyebrow">
                            <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $sh }}"></span>
                            <span class="truncate text-navy-600">{{ $sl }}</span>
                            <span class="ml-auto font-black text-navy-900">{{ $w['byStage'][$sk] ?? 0 }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            @php $donePct = $w['taskTotal'] ? (int) round($w['taskDone'] / $w['taskTotal'] * 100) : 0; @endphp
            <p class="mt-3 rounded-xl bg-page/60 px-3 py-2 text-eyebrow text-muted"><b class="text-navy-900">{{ $donePct }}%</b> of tasks complete across the portfolio.</p>
        </div>

        {{-- 7 · Deadline watch (interactive) --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-navy-900 to-[var(--color-navy-950)] p-5 text-white">
            <div class="pointer-events-none absolute -right-8 -top-12 h-40 w-40 rounded-full bg-[radial-gradient(circle,rgba(248,113,113,0.20),transparent_70%)]"></div>
            <p class="relative text-eyebrow font-black uppercase tracking-[0.22em] text-gold-300">Deadline watch</p>
            <p class="pf relative mt-1.5 text-base font-bold leading-snug text-white">Stay ahead of every deadline</p>

            <div class="relative mt-3 space-y-2">
                @foreach ([['overdue', 'Overdue', $w['overdue'], 'var(--color-danger-on-dark)', 'Needs attention now'], [null, 'Due in 7 days', $w['due7'], 'var(--color-warning-on-dark)', 'Coming up this week'], [null, 'Due in 30 days', $w['due30'], 'var(--color-info-on-dark)', 'On the horizon']] as [$lensKey, $l, $v, $c, $sub])
                    <button type="button" @if ($lensKey) wire:click="setLens('{{ $lensKey }}')" @endif
                            class="flex w-full items-center gap-3 rounded-xl bg-white/[0.06] px-3 py-2.5 text-left ring-1 ring-white/10 transition hover:bg-white/10">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-sm font-black" style="background: {{ $c }}1F; color: {{ $c }}">{{ $v }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-micro font-black uppercase tracking-wider" style="color: {{ $c }}">{{ $l }}</span>
                            <span class="block truncate text-eyebrow text-white/45">{{ $sub }}</span>
                        </span>
                        @if ($lensKey)<span class="shrink-0 text-white/30">›</span>@endif
                    </button>
                @endforeach
            </div>
        </div>

        {{-- 8 · Money --}}
        <div class="rounded-2xl border border-line bg-white p-5">
            <p class="text-eyebrow font-black uppercase tracking-[0.22em] text-navy-400">Money</p>
            <p class="pf mt-2 text-xl font-black leading-none text-navy-900">{{ $money($w['budget']) }}</p>
            <p class="mt-1 text-eyebrow text-muted">total budget across active events</p>

            <div class="mt-3">
                <div class="mb-1 flex items-baseline justify-between text-eyebrow">
                    <span class="font-bold uppercase tracking-wider text-muted">Spent</span>
                    <span class="font-black" style="color: {{ $w['spentPct'] > 90 ? 'var(--color-danger)' : ($w['spentPct'] > 70 ? 'var(--color-warning)' : 'var(--color-success)') }}">{{ $w['spentPct'] }}%</span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-navy-50">
                    <div class="h-full rounded-full" style="width: {{ $w['spentPct'] }}%; background: {{ $w['spentPct'] > 90 ? 'var(--color-danger)' : ($w['spentPct'] > 70 ? 'var(--color-warning)' : 'var(--color-success)') }}"></div>
                </div>
                <p class="mt-1 text-eyebrow text-muted">{{ $money($w['spent']) }} actual</p>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-2 border-t border-line pt-3">
                <div>
                    <p class="text-sm font-black leading-none {{ $w['outstanding'] > 0 ? 'text-risk' : 'text-navy-900' }}">{{ $money($w['outstanding']) }}</p>
                    <p class="mt-1 text-eyebrow font-bold uppercase tracking-wider text-muted">Outstanding</p>
                </div>
                <div>
                    <p class="text-sm font-black leading-none text-navy-900">{{ $money($w['sponsorship']) }}</p>
                    <p class="mt-1 text-eyebrow font-bold uppercase tracking-wider text-muted">Sponsorship</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════ FOCUS BANNER ══════════ --}}
    @if ($focused)
        <div class="mb-3 flex flex-wrap items-center gap-3 rounded-2xl border border-gold-300 bg-gold-50 px-4 py-2.5">
            <span class="text-micro font-bold uppercase tracking-wider text-gold-700">Focused on</span>
            <span class="pf text-sm font-black text-navy-900">{{ $focused->name }}</span>
            <a href="{{ route('events.hub', $focused) }}" class="text-micro font-bold text-gold-700 underline-offset-2 hover:underline">open hub →</a>
            <button type="button" wire:click="focusOn" class="ml-auto rounded-lg bg-white px-2.5 py-1 text-micro font-bold text-navy-600 ring-1 ring-line transition hover:text-navy-900">✕ Clear focus</button>
        </div>
    @endif

    <div class="flex flex-col gap-4 xl:flex-row">
        {{-- ══════════ ACTION STREAM ══════════ --}}
        <div class="min-w-0 flex-1">
            {{-- lens chips --}}
            <div class="mb-3 flex flex-wrap items-center gap-1.5">
                <button type="button" wire:click="setLens('all')"
                        @class(['h-8 rounded-lg px-3 text-micro font-bold transition', 'bg-navy-900 text-white' => $lens === 'all', 'bg-white text-navy-500 ring-1 ring-line hover:text-navy-900' => $lens !== 'all'])>
                    All <span class="opacity-60">{{ $pulse['signals'] }}</span>
                </button>
                @foreach (\App\Livewire\CommandCenter::LENSES as $key => [$label, $hex])
                    <button type="button" wire:click="setLens('{{ $key }}')"
                            @class(['flex h-8 items-center gap-1.5 rounded-lg px-3 text-micro font-bold transition', 'text-white' => $lens === $key, 'bg-white text-navy-600 ring-1 ring-line hover:text-navy-900' => $lens !== $key])
                            @style(['background: '.$hex => $lens === $key])>
                        <span class="h-2 w-2 rounded-full" style="background: {{ $lens === $key ? 'var(--chrome-ink)' : $hex }}"></span>{{ $label }}
                        <span class="opacity-60">{{ $lensCounts[$key] }}</span>
                    </button>
                @endforeach
            </div>

            <div class="overflow-hidden rounded-2xl border border-line bg-white shadow-[0_10px_40px_-18px_rgba(11,31,58,0.35)]">
                @forelse ($signals as $s)
                    @php [$lensLabel, $lensHex] = \App\Livewire\CommandCenter::LENSES[$s['lens']]; @endphp
                    <a href="{{ $s['link'] }}" wire:key="sig-{{ $s['key'] }}"
                       class="group flex items-center gap-3 border-b border-line/60 px-4 py-3 transition last:border-b-0 hover:bg-navy-50/40">
                        <span class="h-8 w-1 shrink-0 rounded-full" style="background: {{ $sevHex[$s['severity']] }}"></span>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-navy-900 group-hover:text-gold-700">{{ $s['title'] }}</p>
                            <p class="mt-0.5 truncate text-micro text-muted">{{ $s['detail'] }}</p>
                        </div>

                        <button type="button" wire:click.prevent="focusOn({{ $s['event_id'] }})"
                                class="hidden shrink-0 truncate rounded-lg bg-navy-50 px-2 py-1 text-eyebrow font-bold text-navy-600 transition hover:bg-navy-900 hover:text-white sm:block"
                                title="Focus the room on this event">{{ \Illuminate\Support\Str::limit($s['event_name'], 22) }}</button>

                        <span class="shrink-0 rounded-md px-2 py-0.5 text-eyebrow font-black uppercase tracking-wider"
                              style="background: {{ $lensHex }}1A; color: {{ $lensHex }}">{{ $lensLabel }}</span>
                        <span class="shrink-0 text-navy-300 transition group-hover:translate-x-0.5 group-hover:text-gold-600">→</span>
                    </a>
                @empty
                    <div class="flex flex-col items-center px-6 py-16 text-center">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-xl text-emerald-500">✓</span>
                        <p class="pf mt-3 text-base font-bold text-navy-900">Nothing needs you here</p>
                        <p class="mt-1 max-w-sm text-xs text-muted">
                            @if ($focused || $lens !== 'all') No signals match this filter — clear it to see the whole room. @else Every event is on track, nothing overdue, nothing awaiting sign-off. @endif
                        </p>
                    </div>
                @endforelse

                @if ($signalTotal > $signals->count())
                    <p class="border-t border-line bg-page/40 px-4 py-2.5 text-center text-micro text-muted">
                        Showing the {{ $signals->count() }} most urgent of {{ $signalTotal }} — narrow with a lens or focus an event.
                    </p>
                @endif
            </div>
        </div>

        {{-- ══════════ EVENT RAIL ══════════ --}}
        <aside class="w-full shrink-0 xl:w-[326px]">
            @if ($week->isNotEmpty())
                <div class="mb-3 overflow-hidden rounded-2xl border border-line bg-white">
                    <p class="border-b border-line px-4 py-2.5 text-eyebrow font-bold uppercase tracking-[0.16em] text-navy-400">Next 14 days</p>
                    @foreach ($week as $e)
                        @php $d = (int) round(now()->startOfDay()->diffInDays($e->starts_at->copy()->startOfDay(), false)); @endphp
                        <a href="{{ route('events.hub', $e) }}" class="flex items-center gap-3 border-b border-line/50 px-4 py-2.5 transition last:border-b-0 hover:bg-navy-50/40">
                            <span class="flex h-9 w-11 shrink-0 flex-col items-center justify-center rounded-lg bg-navy-900 text-white">
                                <span class="text-micro font-black leading-none text-gold-400">{{ $d <= 0 ? 'NOW' : $d }}</span>
                                <span class="text-eyebrow font-bold uppercase tracking-wider text-white/50">{{ $d <= 0 ? 'live' : 'days' }}</span>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-xs font-bold text-navy-900">{{ $e->name }}</span>
                                <span class="block text-eyebrow text-muted">{{ $e->starts_at?->format('D, j M') }}{{ $e->venue ? ' · '.$e->venue->name : '' }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="overflow-hidden rounded-2xl border border-line bg-white">
                <p class="border-b border-line px-4 py-2.5 text-eyebrow font-bold uppercase tracking-[0.16em] text-navy-400">Portfolio · click to focus</p>
                @forelse ($events as $e)
                    @php $h = $health[$e->id]; $count = $byEvent[$e->id] ?? 0; $on = $focusEvent === $e->id; @endphp
                    <button type="button" wire:click="focusOn({{ $e->id }})" wire:key="rail-{{ $e->id }}"
                            @class(['flex w-full items-center gap-3 border-b border-line/50 px-4 py-2.5 text-left transition last:border-b-0', 'bg-gold-50' => $on, 'hover:bg-navy-50/40' => ! $on])>
                        <x-health-ring :percent="$h['score']" :group="$h['group']" size="h-9 w-9" textSize="text-[10px]" class="shrink-0" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-xs font-bold text-navy-900">{{ $e->name }}</span>
                            <span class="block truncate text-eyebrow text-muted">{{ str($e->stage)->replace('_', ' ')->title() }}{{ $e->starts_at ? ' · '.$e->starts_at->format('j M Y') : '' }}</span>
                        </span>
                        @if ($count > 0)
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-eyebrow font-black text-white" style="background: {{ $count >= 5 ? 'var(--color-danger)' : 'var(--color-warning)' }}" title="{{ $count }} open {{ str('signal')->plural($count) }}">{{ $count }}</span>
                        @else
                            <span class="shrink-0 text-micro font-bold text-emerald-500" title="clear">✓</span>
                        @endif
                    </button>
                @empty
                    <p class="px-4 py-10 text-center text-xs text-muted">No active events.</p>
                @endforelse
            </div>
        </aside>
    </div>
</div>
