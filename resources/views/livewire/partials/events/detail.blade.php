{{--
    The deep detail an expanded card reveals. Extracted from the old event-panel
    so both the card grid and any future surface can share one detail block.
    Expects $event and $expanded (from EventsIndex::cardDetail).
--}}
@php $money = fn ($c) => \App\Models\Event::moneyIn((int) $c, $event->currency ?? 'USD'); @endphp

<div class="border-t border-line bg-page/40 p-5">
    {{-- AI read --}}
    <div class="mb-4 rounded-xl border border-gold-200 bg-gold-50/70 p-3.5">
        <p class="eyebrow-gold">AI Recommendation</p>
        <p class="pf mt-1 text-sm font-bold text-navy-900">{{ $expanded['ai']['headline'] }}</p>
        @if (! empty($expanded['ai']['attention']))
            <ul class="mt-1.5 space-y-0.5">
                @foreach (array_slice($expanded['ai']['attention'], 0, 3) as $line)
                    <li class="flex gap-1.5 text-micro text-navy-600"><span class="text-gold-600">•</span><span>{{ $line }}</span></li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- health breakdown --}}
        <div class="rounded-xl border border-line bg-white p-3.5">
            <p class="eyebrow">Health breakdown</p>
            <div class="mt-2.5 space-y-2">
                @forelse ($expanded['components'] as $label => $score)
                    @if ($score !== null)
                        @php $c = $score >= 70 ? 'var(--color-success)' : ($score >= 45 ? 'var(--color-warning)' : 'var(--color-danger)'); @endphp
                        <div>
                            <div class="flex items-baseline justify-between text-eyebrow">
                                <span class="capitalize text-navy-600">{{ str($label)->replace('_', ' ') }}</span>
                                <span class="font-black" style="color: {{ $c }}">{{ $score }}%</span>
                            </div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-navy-50"><div class="h-full rounded-full" style="width: {{ $score }}%; background: {{ $c }}"></div></div>
                        </div>
                    @endif
                @empty
                    <p class="text-micro italic text-navy-300">No components scored yet.</p>
                @endforelse
            </div>
        </div>

        {{-- delivery phases --}}
        <div class="rounded-xl border border-line bg-white p-3.5">
            <p class="eyebrow">Delivery phases</p>
            <div class="mt-2.5 space-y-1.5">
                @forelse ($expanded['tracks'] as $t)
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $t['color'] }}"></span>
                        <span class="min-w-0 flex-1 truncate text-micro text-navy-600">{{ \Illuminate\Support\Str::limit($t['name'], 20) }}</span>
                        <span class="h-1.5 w-12 shrink-0 overflow-hidden rounded-full bg-navy-50"><span class="block h-full rounded-full" style="width: {{ $t['pct'] }}%; background: {{ $t['color'] }}"></span></span>
                        <span class="w-9 shrink-0 text-right text-eyebrow font-black text-navy-400">{{ $t['done'] }}/{{ $t['total'] }}</span>
                    </div>
                @empty
                    <p class="text-micro italic text-navy-300">No plan phases yet.</p>
                @endforelse
            </div>
        </div>

        {{-- money + ops --}}
        <div class="rounded-xl border border-line bg-white p-3.5">
            <p class="eyebrow">Money &amp; operations</p>
            @if ($expanded['spentPct'] !== null)
                @php $bc = $expanded['spentPct'] > 90 ? 'var(--color-danger)' : ($expanded['spentPct'] > 70 ? 'var(--color-warning)' : 'var(--color-success)'); @endphp
                <div class="mt-2.5">
                    <div class="flex items-baseline justify-between text-eyebrow">
                        <span class="text-navy-600">{{ $money($expanded['spent']) }} of {{ $money($expanded['budget']) }}</span>
                        <span class="font-black" style="color: {{ $bc }}">{{ $expanded['spentPct'] }}%</span>
                    </div>
                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-navy-50"><div class="h-full rounded-full" style="width: {{ $expanded['spentPct'] }}%; background: {{ $bc }}"></div></div>
                </div>
            @endif
            <div class="mt-3 grid grid-cols-2 gap-2">
                @foreach ([
                    ['Overdue', $expanded['overdue'], 'var(--color-danger)'], ['Unassigned', $expanded['unassigned'], 'var(--color-warning)'],
                    ['Open risks', $expanded['risks'], 'var(--color-danger-on-dark)'], ['Approvals', $expanded['approvals'], \App\Models\Event::moduleColor('approvals')],
                    ['Sessions', $expanded['sessions'], \App\Models\Event::moduleColor('agenda')], ['Suppliers', $expanded['suppliers'], 'var(--color-success)'],
                ] as [$l, $v, $c])
                    <div class="rounded-lg bg-page/70 px-2 py-1.5">
                        <p class="text-sm font-black leading-none" style="color: {{ $v > 0 ? $c : 'var(--color-neutral)' }}">{{ $v }}</p>
                        <p class="mt-0.5 text-eyebrow font-bold uppercase tracking-wider text-muted">{{ $l }}</p>
                    </div>
                @endforeach
            </div>
            @if ($expanded['outstanding'] > 0)
                <p class="mt-2 rounded-lg bg-risk/5 px-2 py-1.5 text-eyebrow font-semibold text-risk">{{ $money($expanded['outstanding']) }} outstanding on the contract</p>
            @endif
        </div>
    </div>

    {{-- next deadlines --}}
    @if ($expanded['deadlines']->isNotEmpty())
        <div class="mt-4 rounded-xl border border-line bg-white p-3.5">
            <p class="eyebrow mb-2">Next deadlines</p>
            <div class="grid gap-1.5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($expanded['deadlines'] as $d)
                    @php $late = $d['due']->copy()->startOfDay()->lt(now()->startOfDay()); @endphp
                    <div class="flex items-center gap-2 rounded-lg bg-page/60 px-2.5 py-1.5">
                        <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $d['hex'] }}"></span>
                        <span class="min-w-0 flex-1 truncate text-micro font-semibold text-navy-700">{{ $d['title'] }}</span>
                        <span class="shrink-0 text-eyebrow font-bold {{ $late ? 'text-risk' : 'text-navy-400' }}">{{ $d['due']->format('j M') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- control room --}}
    <div class="mt-4">
        <p class="eyebrow mb-2">Event Control Room</p>
        <div class="flex flex-wrap gap-1.5">
            @foreach ([['overview', 'Overview', 'home'], ['planning', 'Plan', 'list'], ['tasks', 'Tasks', 'clipboard'], ['budget', 'Budget', 'currency'], ['agenda', 'Agenda', 'calendar'], ['suppliers', 'Suppliers', 'truck'], ['risks', 'Risks', 'bell'], ['approvals', 'Approvals', 'identification']] as [$tab, $label, $icon])
                <a href="{{ route('events.hub', [$event, 'tab' => $tab]) }}" class="flex items-center gap-1.5 rounded-lg border border-line bg-white px-2.5 py-1.5 text-micro font-bold text-navy-600 transition hover:border-gold-300 hover:text-gold-700">
                    <x-icon :name="$icon" class="h-3.5 w-3.5 text-navy-400" />{{ $label }}
                </a>
            @endforeach
            <a href="{{ route('events.hub', $event) }}" class="btn-gold ml-auto h-8 !rounded-lg px-4 text-micro">Open hub →</a>
        </div>
    </div>
</div>
