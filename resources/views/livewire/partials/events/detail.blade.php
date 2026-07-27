{{--
    The Inspector's body.

    This was written for a card that expanded to the full width of the page, so
    it laid itself out in three columns. It now lives in a ~320px panel, and
    Tailwind's breakpoints answer to the viewport rather than the container —
    at 1280px the three columns fired inside a 320px box and the labels ran
    straight through each other. Everything here is single-column by design.

    Expects $event and $expanded (from EventsIndex::cardDetail).
--}}
@php $money = fn ($c) => \App\Models\Event::moneyIn((int) $c, $event->currency ?? 'USD'); @endphp

<div class="space-y-3.5">

    {{-- what the engine would tell you first --}}
    <div class="rounded-xl border border-gold-200 bg-gold-50/70 p-3">
        <p class="eyebrow-gold">AI Recommendation</p>
        <p class="pf mt-1 text-[13px] font-bold leading-snug text-navy-900">{{ $expanded['ai']['headline'] }}</p>
        @if (! empty($expanded['ai']['attention']))
            <ul class="mt-1.5 space-y-1">
                @foreach (array_slice($expanded['ai']['attention'], 0, 3) as $line)
                    <li class="flex gap-1.5 text-[11px] leading-snug text-navy-600"><span class="text-gold-600">•</span><span>{{ $line }}</span></li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- health, component by component --}}
    <div>
        <p class="eyebrow mb-2">Health breakdown</p>
        <div class="space-y-2">
            @forelse (collect($expanded['components'])->filter(fn ($s) => $s !== null) as $label => $score)
                @php $c = $score >= 70 ? 'var(--color-success)' : ($score >= 45 ? 'var(--color-warning)' : 'var(--color-danger)'); @endphp
                <div>
                    <div class="flex items-baseline justify-between gap-2 text-[11px]">
                        <span class="capitalize text-navy-600">{{ str($label)->replace('_', ' ') }}</span>
                        <span class="font-bold tabular-nums" style="color: {{ $c }}">{{ $score }}%</span>
                    </div>
                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-navy-50">
                        <div class="h-full rounded-full" style="width: {{ $score }}%; background: {{ $c }}"></div>
                    </div>
                </div>
            @empty
                <p class="text-[11px] italic text-navy-300">Not scored yet — this event is still at {{ $event->stage }} stage.</p>
            @endforelse
        </div>
    </div>

    {{-- money --}}
    @if ($expanded['spentPct'] !== null)
        @php $bc = $expanded['spentPct'] > 90 ? 'var(--color-danger)' : ($expanded['spentPct'] > 70 ? 'var(--color-warning)' : 'var(--color-success)'); @endphp
        <div>
            <p class="eyebrow mb-2">Money</p>
            <div class="flex items-baseline justify-between gap-2 text-[11px]">
                <span class="truncate text-navy-600">{{ $money($expanded['spent']) }} of {{ $money($expanded['budget']) }}</span>
                <span class="font-bold tabular-nums" style="color: {{ $bc }}">{{ $expanded['spentPct'] }}%</span>
            </div>
            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-navy-50">
                <div class="h-full rounded-full" style="width: {{ $expanded['spentPct'] }}%; background: {{ $bc }}"></div>
            </div>
            @if ($expanded['outstanding'] > 0)
                <p class="mt-2 rounded-lg bg-risk/5 px-2 py-1.5 text-[10.5px] font-semibold text-risk">{{ $money($expanded['outstanding']) }} outstanding on the contract</p>
            @endif
        </div>
    @endif

    {{-- the six operational counts, two up — they fit at 320px, three never did --}}
    <div>
        <p class="eyebrow mb-2">Operations</p>
        <div class="grid grid-cols-2 gap-1.5">
            @foreach ([
                ['Overdue', $expanded['overdue'], 'var(--color-danger)'],
                ['Unassigned', $expanded['unassigned'], 'var(--color-warning)'],
                ['Open risks', $expanded['risks'], 'var(--color-danger)'],
                ['Approvals', $expanded['approvals'], \App\Models\Event::moduleColor('approvals')],
                ['Sessions', $expanded['sessions'], \App\Models\Event::moduleColor('agenda')],
                ['Suppliers', $expanded['suppliers'], 'var(--color-success)'],
            ] as [$l, $v, $c])
                <div class="rounded-lg bg-page/70 px-2.5 py-1.5">
                    <p class="text-[15px] font-bold leading-none tabular-nums" style="color: {{ $v > 0 ? $c : 'var(--color-neutral)' }}">{{ $v }}</p>
                    <p class="mt-1 truncate text-[9px] font-bold uppercase tracking-[0.08em] text-muted">{{ $l }}</p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- delivery phases --}}
    <div>
        <p class="eyebrow mb-2">Delivery phases</p>
        <div class="space-y-1.5">
            @forelse ($expanded['tracks'] as $t)
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $t['color'] }}"></span>
                        <span class="min-w-0 flex-1 truncate text-[11px] text-navy-600">{{ $t['name'] }}</span>
                        <span class="h-1.5 w-10 shrink-0 overflow-hidden rounded-full bg-navy-50">
                            <span class="block h-full rounded-full" style="width: {{ $t['pct'] }}%; background: {{ $t['color'] }}"></span>
                        </span>
                    <span class="w-9 shrink-0 text-right text-[10px] font-bold tabular-nums text-navy-400">{{ $t['done'] }}/{{ $t['total'] }}</span>
                </div>
            @empty
                <p class="text-[11px] italic text-navy-300">No plan phases yet.</p>
            @endforelse
        </div>
    </div>

    {{-- next deadlines --}}
    @if ($expanded['deadlines']->isNotEmpty())
        <div>
            <p class="eyebrow mb-2">Next deadlines</p>
            <div class="space-y-1">
                @foreach ($expanded['deadlines'] as $d)
                    @php $late = $d['due']->copy()->startOfDay()->lt(now()->startOfDay()); @endphp
                    <div class="flex items-center gap-2 rounded-lg bg-page/60 px-2.5 py-1.5">
                        <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $d['hex'] }}"></span>
                        <span class="min-w-0 flex-1 truncate text-[11px] font-semibold text-navy-700">{{ $d['title'] }}</span>
                        <span class="shrink-0 text-[10px] font-bold tabular-nums {{ $late ? 'text-risk' : 'text-navy-400' }}">{{ $d['due']->format('j M') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- straight into the event --}}
    <div class="border-t border-line pt-3">
        <p class="eyebrow mb-2">Event Control Room</p>
        <div class="grid grid-cols-2 gap-1.5">
            @foreach ([
                ['overview', 'Overview', 'home'], ['planning', 'Plan', 'list'],
                ['tasks', 'Tasks', 'clipboard'], ['budget', 'Budget', 'currency'],
                ['agenda', 'Agenda', 'calendar'], ['suppliers', 'Suppliers', 'truck'],
                ['risks', 'Risks', 'bell'], ['approvals', 'Approvals', 'identification'],
            ] as [$tab, $label, $icon])
                <a href="{{ route('events.hub', [$event, 'tab' => $tab]) }}"
                   class="flex items-center gap-1.5 rounded-lg border border-line bg-white px-2 py-1.5 text-[11px] font-bold text-navy-600 transition hover:border-gold-300 hover:text-gold-700">
                    <x-icon :name="$icon" class="h-3.5 w-3.5 shrink-0 text-navy-400" />
                    <span class="truncate">{{ $label }}</span>
                </a>
            @endforeach
        </div>
        <a href="{{ route('events.hub', $event) }}" class="btn-gold mt-2 flex h-9 w-full items-center justify-center !rounded-lg text-[12px]">Open hub →</a>
    </div>
</div>
