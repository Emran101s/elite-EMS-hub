<div class="space-y-4">

    <x-figure-strip :figures="$figures" />

    {{-- Which slice of the book you are reading. --}}
    <div class="card flex flex-wrap items-center gap-x-3 gap-y-2 px-3 py-2.5">
        <div class="flex flex-wrap items-center gap-1">
            @foreach (['all' => 'Whole book', 'live' => 'Live now', 'upcoming' => 'Upcoming', 'delivered' => 'Delivered'] as $key => $label)
                <button type="button" wire:click="setWindow('{{ $key }}')"
                        @class([
                            'rounded-full px-3.5 py-1.5 text-xs font-bold transition',
                            'bg-navy-950 text-white' => $window === $key,
                            'text-navy-500 hover:bg-navy-50 hover:text-navy-900' => $window !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>

        <p class="ms-auto text-[11px] text-muted">
            Every figure is counted off the records now — nothing here is stored.
        </p>
    </div>

    @if ($events->isEmpty())
        <x-empty icon="chart" title="Nothing in this window"
                 hint="No event falls in the slice you picked. Try the whole book." />
    @else

    {{-- ══════════ DELIVERY ══════════ --}}
    <div class="card overflow-hidden">
        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 border-b border-line px-4 py-3">
            <h2 class="pf text-[15px] font-bold text-navy-950">Delivery</h2>
            <p class="text-[11.5px] text-muted">Where each event is, and what is late — worst first.</p>
            <a href="{{ route('events.index') }}" class="ms-auto text-[11.5px] font-semibold text-navy-500 transition hover:text-navy-900">Open the journey →</a>
        </div>

        <div class="scrollbar-none overflow-x-auto">
            <table class="w-full min-w-[820px] text-left">
                <thead>
                    <tr class="border-b border-line text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-400">
                        <th class="px-4 py-2.5 font-bold">Event</th>
                        <th class="px-3 py-2.5 font-bold">Phase</th>
                        <th class="px-3 py-2.5 font-bold">Health</th>
                        <th class="px-3 py-2.5 text-right font-bold">Tasks</th>
                        <th class="px-3 py-2.5 text-right font-bold">Overdue</th>
                        <th class="px-3 py-2.5 text-right font-bold">Open risks</th>
                        <th class="px-3 py-2.5 text-right font-bold">Approvals</th>
                        <th class="px-4 py-2.5 text-right font-bold">Margin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($delivery as $row)
                        <tr class="transition hover:bg-page/50">
                            <td class="px-4 py-2.5">
                                <a href="{{ route('events.hub', $row['event']) }}" class="block max-w-[220px] truncate text-[12.5px] font-bold text-navy-900 transition hover:text-gold-700">{{ $row['event']->name }}</a>
                                <span class="block truncate text-[10.5px] text-muted">{{ $row['event']->client?->name ?? 'No client' }}</span>
                            </td>
                            <td class="px-3 py-2.5">
                                <span class="flex items-center gap-1.5 text-[11.5px] font-semibold text-navy-700">
                                    <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $row['phaseHex'] }}"></span>{{ $row['phase'] }}
                                </span>
                                <span class="block text-[10px] text-muted">{{ $row['phasePct'] }}% through it</span>
                            </td>
                            <td class="px-3 py-2.5">
                                <span @class([
                                    'inline-block rounded-full px-2 py-0.5 text-[10px] font-bold',
                                    'bg-red-100 text-red-700' => $row['group'] === 'risk',
                                    'bg-amber-100 text-amber-700' => $row['group'] === 'warn',
                                    'bg-navy-50 text-navy-400' => $row['group'] === 'neutral',
                                    'bg-emerald-100 text-emerald-700' => ! in_array($row['group'], ['risk', 'warn', 'neutral'], true),
                                ])>{{ $row['score'] === null ? 'Not scored' : $row['score'].'%' }}</span>
                            </td>
                            <td class="px-3 py-2.5 text-right text-[12px] tabular-nums text-navy-800">{{ $row['tasksTotal'] ? $row['tasksDone'].' / '.$row['tasksTotal'] : '—' }}</td>
                            <td class="px-3 py-2.5 text-right text-[12px] font-bold tabular-nums {{ $row['overdue'] ? 'text-red-600' : 'text-navy-300' }}">{{ $row['overdue'] ?: '—' }}</td>
                            <td class="px-3 py-2.5 text-right text-[12px] font-bold tabular-nums {{ $row['risks'] ? 'text-red-600' : 'text-navy-300' }}">{{ $row['risks'] ?: '—' }}</td>
                            <td class="px-3 py-2.5 text-right text-[12px] font-bold tabular-nums {{ $row['approvals'] ? 'text-amber-600' : 'text-navy-300' }}">{{ $row['approvals'] ?: '—' }}</td>
                            <td class="px-4 py-2.5 text-right text-[12px] font-bold tabular-nums {{ $row['margin'] === null ? 'text-navy-300' : ($row['margin'] < 0 ? 'text-red-600' : 'text-navy-900') }}">{{ $row['margin'] === null ? '—' : $row['margin'].'%' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">

        {{-- ══════════ MONEY ══════════ --}}
        <div class="card overflow-hidden">
            <div class="flex flex-wrap items-baseline gap-x-3 border-b border-line px-4 py-3">
                <h2 class="pf text-[15px] font-bold text-navy-950">Money</h2>
                <p class="text-[11.5px] text-muted">What it costs, what it sells for.</p>
                <a href="{{ route('finance.index') }}" class="ms-auto text-[11.5px] font-semibold text-navy-500 transition hover:text-navy-900">Open finance →</a>
            </div>

            <div class="grid grid-cols-2 divide-x divide-y divide-line sm:grid-cols-4 sm:divide-y-0">
                @foreach ([
                    ['Contracted', $totals['income'], 'Signed and booked'],
                    ['Cost', $totals['cost'], 'Committed to suppliers'],
                    ['Net', $totals['net'], 'What you keep'],
                    ['Unbilled', $totals['unbilled'], 'Priced, not invoiced'],
                ] as [$label, $value, $note])
                    <div class="px-3.5 py-3">
                        <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-400">{{ $label }}</p>
                        <p class="pf mt-1 text-[19px] font-black leading-none {{ $value < 0 ? 'text-red-600' : 'text-navy-950' }}">
                            {{ \App\Livewire\EventsIndex::shortMoney($value, $totals['currency']) }}
                        </p>
                        <p class="mt-1 truncate text-[10px] text-muted">{{ $note }}</p>
                    </div>
                @endforeach
            </div>

            @if ($totals['mixed'])
                <p class="border-t border-line px-4 py-2 text-[10.5px] italic text-muted">
                    Converted to {{ $totals['currency'] }} — the book runs in more than one currency.
                </p>
            @endif

            <div class="divide-y divide-line border-t border-line">
                @foreach ($money->sortByDesc('charged')->take(6) as $row)
                    <div class="flex items-center gap-3 px-4 py-2.5">
                        <a href="{{ route('events.hub', [$row['event'], 'tab' => 'budget']) }}" class="min-w-0 flex-1 truncate text-[12px] font-semibold text-navy-800 transition hover:text-gold-700">{{ $row['event']->name }}</a>
                        <span class="shrink-0 text-[11.5px] tabular-nums text-muted">{{ \App\Livewire\EventsIndex::shortMoney($row['cost'], $totals['currency']) }} cost</span>
                        <span class="shrink-0 text-[11.5px] font-bold tabular-nums text-navy-900">{{ \App\Livewire\EventsIndex::shortMoney($row['charged'], $totals['currency']) }}</span>
                        <span class="w-12 shrink-0 text-right text-[11.5px] font-bold tabular-nums {{ ($row['pricedMargin'] ?? 0) < 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ $row['pricedMargin'] === null ? '—' : $row['pricedMargin'].'%' }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ══════════ PROGRAMME ══════════ --}}
        <div class="card overflow-hidden">
            <div class="flex flex-wrap items-baseline gap-x-3 border-b border-line px-4 py-3">
                <h2 class="pf text-[15px] font-bold text-navy-950">Programme</h2>
                <p class="text-[11.5px] text-muted">How much stage there is, and who is on it.</p>
            </div>

            <div class="grid grid-cols-2 divide-x divide-y divide-line sm:grid-cols-4 sm:divide-y-0">
                @foreach ([
                    ['Sessions', $programme['sessions'], $programme['settled'].' confirmed'],
                    ['Stage hours', $programme['hours'], 'Programmed'],
                    ['Speakers', $programme['speakers'], 'Billed across the book'],
                    ['Rooms', $programme['rooms'], 'In play'],
                ] as [$label, $value, $note])
                    <div class="px-3.5 py-3">
                        <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-400">{{ $label }}</p>
                        <p class="pf mt-1 text-[19px] font-black leading-none text-navy-950">{{ $value }}</p>
                        <p class="mt-1 truncate text-[10px] text-muted">{{ $note }}</p>
                    </div>
                @endforeach
            </div>

            @if ($programme['byType']->isNotEmpty())
                <div class="space-y-2 px-4 py-3">
                    <p class="eyebrow">What the stage time is spent on</p>
                    @foreach ($programme['byType'] as $type)
                        <div class="flex items-center gap-2.5">
                            <span class="w-24 shrink-0 truncate text-[11.5px] text-navy-700">{{ $type['label'] }}</span>
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-navy-50">
                                <div class="h-full rounded-full" style="width: {{ $programme['sessions'] ? round($type['count'] / $programme['sessions'] * 100) : 0 }}%; background: {{ $type['hex'] }}"></div>
                            </div>
                            <span class="w-6 shrink-0 text-right text-[11.5px] font-bold tabular-nums text-navy-900">{{ $type['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ══════════ PEOPLE ══════════ --}}
        <div class="card overflow-hidden">
            <div class="flex flex-wrap items-baseline gap-x-3 border-b border-line px-4 py-3">
                <h2 class="pf text-[15px] font-bold text-navy-950">People</h2>
                <p class="text-[11.5px] text-muted">Who registered, and who actually came.</p>
            </div>

            <div class="flex items-center gap-4 px-4 py-4">
                <span class="relative grid h-[86px] w-[86px] shrink-0 place-items-center">
                    @php $r = 2 * M_PI * 26; @endphp
                    <svg class="h-[86px] w-[86px] -rotate-90" viewBox="0 0 60 60" aria-hidden="true">
                        <circle cx="30" cy="30" r="26" fill="none" stroke="var(--color-navy-50)" stroke-width="7" />
                        <circle cx="30" cy="30" r="26" fill="none" stroke="var(--color-gold-500)" stroke-width="7" stroke-linecap="round"
                                stroke-dasharray="{{ $r }}" stroke-dashoffset="{{ $r - ($r * $people['pct'] / 100) }}" />
                    </svg>
                    <span class="absolute text-center leading-none">
                        <span class="pf block text-[18px] font-black text-navy-950">{{ $people['pct'] }}%</span>
                        <span class="mt-0.5 block text-[8px] font-bold uppercase tracking-[0.1em] text-muted">Arrived</span>
                    </span>
                </span>

                <div class="min-w-0 flex-1 space-y-1.5 text-[12px]">
                    <p class="flex items-baseline justify-between gap-2"><span class="text-muted">Registered</span><span class="font-bold tabular-nums text-navy-900">{{ number_format($people['registered']) }}</span></p>
                    <p class="flex items-baseline justify-between gap-2"><span class="text-muted">Checked in</span><span class="font-bold tabular-nums text-navy-900">{{ number_format($people['arrived']) }}</span></p>
                    <p class="flex items-baseline justify-between gap-2"><span class="text-muted">VIP</span><span class="font-bold tabular-nums text-navy-900">{{ number_format($people['vip']) }}</span></p>
                </div>
            </div>

            @if ($people['byEvent']->isNotEmpty())
                <div class="divide-y divide-line border-t border-line">
                    @foreach ($people['byEvent']->take(5) as $row)
                        <div class="flex items-center gap-3 px-4 py-2.5">
                            <a href="{{ route('events.hub', [$row['event'], 'tab' => 'attendees']) }}" class="min-w-0 flex-1 truncate text-[12px] font-semibold text-navy-800 transition hover:text-gold-700">{{ $row['event']->name }}</a>
                            <span class="shrink-0 text-[11.5px] tabular-nums text-muted">{{ number_format($row['arrived']) }} / {{ number_format($row['registered']) }}</span>
                            <div class="h-2 w-20 shrink-0 overflow-hidden rounded-full bg-navy-50">
                                <div class="h-full rounded-full bg-gold-500" style="width: {{ $row['registered'] ? round($row['arrived'] / $row['registered'] * 100) : 0 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ══════════ WORK ══════════ --}}
        <div class="card overflow-hidden">
            <div class="flex flex-wrap items-baseline gap-x-3 border-b border-line px-4 py-3">
                <h2 class="pf text-[15px] font-bold text-navy-950">Work</h2>
                <p class="text-[11.5px] text-muted">The board across every event.</p>
                <a href="{{ route('tasks.index') }}" class="ms-auto text-[11.5px] font-semibold text-navy-500 transition hover:text-navy-900">Open tasks →</a>
            </div>

            <div class="grid grid-cols-2 divide-x divide-y divide-line sm:grid-cols-4 sm:divide-y-0">
                @foreach ([
                    ['Tasks', $work['total'], 'On the board', 'text-navy-950'],
                    ['Done', $work['done'], $work['total'] ? round($work['done'] / $work['total'] * 100).'% closed' : '—', 'text-emerald-600'],
                    ['Overdue', $work['overdue'], 'Past their date', $work['overdue'] ? 'text-red-600' : 'text-navy-300'],
                    ['Unassigned', $work['unassigned'], 'Nobody owns them', $work['unassigned'] ? 'text-amber-600' : 'text-navy-300'],
                ] as [$label, $value, $note, $ink])
                    <div class="px-3.5 py-3">
                        <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-400">{{ $label }}</p>
                        <p class="pf mt-1 text-[19px] font-black leading-none {{ $ink }}">{{ $value }}</p>
                        <p class="mt-1 truncate text-[10px] text-muted">{{ $note }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    @endif
</div>
