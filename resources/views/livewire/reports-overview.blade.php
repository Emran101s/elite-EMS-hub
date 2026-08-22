<div class="space-y-4">

    <x-cc.header eyebrow="Intelligence Command" title="Reports" subtitle="The whole book, read across — delivery, money, programme and people." />

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ($figures as $f)
            <x-cc.kpi-tile :label="$f['label']" :value="$f['value']" :hint="$f['note'] ?? null" />
        @endforeach
    </div>

    {{-- Which slice of the book you are reading. --}}
    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-line bg-white px-3 py-2.5">
        <div class="flex flex-wrap items-center gap-1">
            @foreach (['all' => 'Whole book', 'live' => 'Live now', 'upcoming' => 'Upcoming', 'delivered' => 'Delivered'] as $key => $label)
                <button type="button" wire:click="setWindow('{{ $key }}')"
                        @class([
                            'rounded-full px-3.5 py-1.5 text-[12px] font-bold transition',
                            'bg-navy-900 text-white' => $window === $key,
                            'text-muted hover:bg-page hover:text-ink' => $window !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>

        <p class="ms-auto text-[11px] text-muted">
            Every figure is counted off the records now — nothing here is stored.
        </p>
    </div>

    @if ($events->isEmpty())
        <x-eo.empty-state icon="chart" title="Nothing in this window"
                 hint="No event falls in the slice you picked. Try the whole book." />
    @else

    {{-- ══════════ DELIVERY ══════════ --}}
    <div class="overflow-hidden rounded-lg border border-line bg-white">
        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 border-b border-line px-4 py-3">
            <h2 class="text-[15px] font-bold text-ink">Delivery</h2>
            <p class="text-[11.5px] text-muted">Where each event is, and what is late — worst first.</p>
            <a href="{{ route('events.index') }}" class="ms-auto text-[11.5px] font-semibold text-muted transition hover:text-gold-700">Open the journey →</a>
        </div>

        <div class="scrollbar-none overflow-x-auto">
            <table class="w-full min-w-[820px] text-left">
                <thead>
                    <tr class="border-b border-line text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">
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
                        <tr class="transition hover:bg-page">
                            <td class="px-4 py-2.5">
                                <a href="{{ route('events.hub', $row['event']) }}" class="block max-w-[220px] truncate text-[12.5px] font-bold text-ink transition hover:text-gold-700">{{ $row['event']->name }}</a>
                                <span class="block truncate text-[10.5px] text-muted">{{ $row['event']->client?->name ?? 'No client' }}</span>
                            </td>
                            <td class="px-3 py-2.5">
                                <span class="flex items-center gap-1.5 text-[11.5px] font-semibold text-ink">
                                    <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $row['phaseHex'] }}"></span>{{ $row['phase'] }}
                                </span>
                                <span class="block text-[10px] text-muted">{{ $row['phasePct'] }}% through it</span>
                            </td>
                            <td class="px-3 py-2.5">
                                @php
                                    $healthClasses = match (true) {
                                        $row['group'] === 'risk' => 'bg-danger-soft text-danger-ink',
                                        $row['group'] === 'warn' => 'bg-warning-soft text-warning-ink',
                                        $row['group'] === 'neutral' => 'bg-page text-muted',
                                        default => 'bg-success-soft text-success-ink',
                                    };
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10.5px] font-bold {{ $healthClasses }}">{{ $row['score'] === null ? 'Not scored' : $row['score'].'%' }}</span>
                            </td>
                            <td class="px-3 py-2.5 text-right text-[12px] tabular-nums text-ink">{{ $row['tasksTotal'] ? $row['tasksDone'].' / '.$row['tasksTotal'] : '—' }}</td>
                            <td class="px-3 py-2.5 text-right text-[12px] font-bold tabular-nums {{ $row['overdue'] ? 'text-danger-ink' : 'text-muted' }}">{{ $row['overdue'] ?: '—' }}</td>
                            <td class="px-3 py-2.5 text-right text-[12px] font-bold tabular-nums {{ $row['risks'] ? 'text-danger-ink' : 'text-muted' }}">{{ $row['risks'] ?: '—' }}</td>
                            <td class="px-3 py-2.5 text-right text-[12px] font-bold tabular-nums {{ $row['approvals'] ? 'text-warning-ink' : 'text-muted' }}">{{ $row['approvals'] ?: '—' }}</td>
                            <td class="px-4 py-2.5 text-right text-[12px] font-bold tabular-nums {{ $row['margin'] === null ? 'text-muted' : ($row['margin'] < 0 ? 'text-danger-ink' : 'text-ink') }}">{{ $row['margin'] === null ? '—' : $row['margin'].'%' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">

        {{-- ══════════ MONEY ══════════ --}}
        <div class="overflow-hidden rounded-lg border border-line bg-white">
            <div class="flex flex-wrap items-baseline gap-x-3 border-b border-line px-4 py-3">
                <h2 class="text-[15px] font-bold text-ink">Money</h2>
                <p class="text-[11.5px] text-muted">What it costs, what it sells for.</p>
                <a href="{{ route('finance.index') }}" class="ms-auto text-[11.5px] font-semibold text-muted transition hover:text-gold-700">Open finance →</a>
            </div>

            <div class="grid grid-cols-2 divide-x divide-y divide-line sm:grid-cols-4 sm:divide-y-0">
                @foreach ([
                    ['Contracted', $totals['income'], 'Signed and booked'],
                    ['Cost', $totals['cost'], 'Committed to suppliers'],
                    ['Net', $totals['net'], 'What you keep'],
                    ['Unbilled', $totals['unbilled'], 'Priced, not invoiced'],
                ] as [$label, $value, $note])
                    <div class="px-3.5 py-3">
                        <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">{{ $label }}</p>
                        <p class="mt-1 text-[19px] font-black leading-none {{ $value < 0 ? 'text-danger-ink' : 'text-ink' }}">
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
                        <a href="{{ route('events.hub', [$row['event'], 'tab' => 'budget']) }}" class="min-w-0 flex-1 truncate text-[12px] font-semibold text-ink transition hover:text-gold-700">{{ $row['event']->name }}</a>
                        <span class="shrink-0 text-[11.5px] tabular-nums text-muted">{{ \App\Livewire\EventsIndex::shortMoney($row['cost'], $totals['currency']) }} cost</span>
                        <span class="shrink-0 text-[11.5px] font-bold tabular-nums text-ink">{{ \App\Livewire\EventsIndex::shortMoney($row['charged'], $totals['currency']) }}</span>
                        <span class="w-12 shrink-0 text-right text-[11.5px] font-bold tabular-nums {{ ($row['pricedMargin'] ?? 0) < 0 ? 'text-danger-ink' : 'text-success-ink' }}">{{ $row['pricedMargin'] === null ? '—' : $row['pricedMargin'].'%' }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ══════════ PROGRAMME ══════════ --}}
        <div class="overflow-hidden rounded-lg border border-line bg-white">
            <div class="flex flex-wrap items-baseline gap-x-3 border-b border-line px-4 py-3">
                <h2 class="text-[15px] font-bold text-ink">Programme</h2>
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
                        <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">{{ $label }}</p>
                        <p class="mt-1 text-[19px] font-black leading-none text-ink">{{ $value }}</p>
                        <p class="mt-1 truncate text-[10px] text-muted">{{ $note }}</p>
                    </div>
                @endforeach
            </div>

            @if ($programme['byType']->isNotEmpty())
                <div class="space-y-2 px-4 py-3">
                    <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">What the stage time is spent on</p>
                    @foreach ($programme['byType'] as $type)
                        <div class="flex items-center gap-2.5">
                            <span class="w-24 shrink-0 truncate text-[11.5px] text-ink">{{ $type['label'] }}</span>
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-page">
                                <div class="h-full rounded-full" style="width: {{ $programme['sessions'] ? round($type['count'] / $programme['sessions'] * 100) : 0 }}%; background: {{ $type['hex'] }}"></div>
                            </div>
                            <span class="w-6 shrink-0 text-right text-[11.5px] font-bold tabular-nums text-ink">{{ $type['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ══════════ PEOPLE ══════════ --}}
        <div class="overflow-hidden rounded-lg border border-line bg-white">
            <div class="flex flex-wrap items-baseline gap-x-3 border-b border-line px-4 py-3">
                <h2 class="text-[15px] font-bold text-ink">People</h2>
                <p class="text-[11.5px] text-muted">Who registered, and who actually came.</p>
            </div>

            <div class="flex items-center gap-4 px-4 py-4">
                <div class="ccx-ring h-[72px] w-[72px] shrink-0" style="--ccx-ring: var(--color-gold-500); --ccx-ring-pct: {{ $people['pct'] }}%">
                    <span class="ccx-ring-value !text-[15px]">{{ $people['pct'] }}%</span>
                </div>

                <div class="min-w-0 flex-1 space-y-1.5 text-[12px]">
                    <p class="flex items-baseline justify-between gap-2"><span class="text-muted">Registered</span><span class="font-bold tabular-nums text-ink">{{ number_format($people['registered']) }}</span></p>
                    <p class="flex items-baseline justify-between gap-2"><span class="text-muted">Checked in</span><span class="font-bold tabular-nums text-ink">{{ number_format($people['arrived']) }}</span></p>
                    <p class="flex items-baseline justify-between gap-2"><span class="text-muted">VIP</span><span class="font-bold tabular-nums text-ink">{{ number_format($people['vip']) }}</span></p>
                </div>
            </div>

            @if ($people['byEvent']->isNotEmpty())
                <div class="divide-y divide-line border-t border-line">
                    @foreach ($people['byEvent']->take(5) as $row)
                        <div class="flex items-center gap-3 px-4 py-2.5">
                            <a href="{{ route('events.hub', [$row['event'], 'tab' => 'attendees']) }}" class="min-w-0 flex-1 truncate text-[12px] font-semibold text-ink transition hover:text-gold-700">{{ $row['event']->name }}</a>
                            <span class="shrink-0 text-[11.5px] tabular-nums text-muted">{{ number_format($row['arrived']) }} / {{ number_format($row['registered']) }}</span>
                            <div class="h-2 w-20 shrink-0 overflow-hidden rounded-full bg-page">
                                <div class="h-full rounded-full bg-gold-500" style="width: {{ $row['registered'] ? round($row['arrived'] / $row['registered'] * 100) : 0 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ══════════ WORK ══════════ --}}
        <div class="overflow-hidden rounded-lg border border-line bg-white">
            <div class="flex flex-wrap items-baseline gap-x-3 border-b border-line px-4 py-3">
                <h2 class="text-[15px] font-bold text-ink">Work</h2>
                <p class="text-[11.5px] text-muted">The board across every event.</p>
                <a href="{{ route('tasks.index') }}" class="ms-auto text-[11.5px] font-semibold text-muted transition hover:text-gold-700">Open tasks →</a>
            </div>

            <div class="grid grid-cols-2 divide-x divide-y divide-line sm:grid-cols-4 sm:divide-y-0">
                @foreach ([
                    ['Tasks', $work['total'], 'On the board', 'text-ink'],
                    ['Done', $work['done'], $work['total'] ? round($work['done'] / $work['total'] * 100).'% closed' : '—', 'text-success-ink'],
                    ['Overdue', $work['overdue'], 'Past their date', $work['overdue'] ? 'text-danger' : 'text-muted'],
                    ['Unassigned', $work['unassigned'], 'Nobody owns them', $work['unassigned'] ? 'text-warning-ink' : 'text-muted'],
                ] as [$label, $value, $note, $ink])
                    <div class="px-3.5 py-3">
                        <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">{{ $label }}</p>
                        <p class="mt-1 text-[19px] font-black leading-none {{ $ink }}">{{ $value }}</p>
                        <p class="mt-1 truncate text-[10px] text-muted">{{ $note }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    @endif
</div>
