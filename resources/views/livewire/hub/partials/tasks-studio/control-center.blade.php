@php
    // Derived, honest signals — all from the tasks already loaded.
    $ini = fn ($n) => \Illuminate\Support\Str::of($n)->explode(' ')->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    $openTasks = $items->filter->isOpen();
    $dueToday = $openTasks->filter(fn ($t) => $t->due_on && $t->due_on->isToday())->count();
    $unassigned = $openTasks->whereNull('assignee_id')->count();
    $doneCount = $items->where('status', 'done')->count();
    $cancelled = $items->where('status', 'cancelled')->count();
    $openCount = max($stats['total'] - $doneCount - $cancelled, 0);
    $usersById = $users->keyBy('id');
    $workload = $openTasks->whereNotNull('assignee_id')->groupBy('assignee_id')
        ->map->count()->sortDesc()->take(4);
    $workMax = $workload->max() ?: 1;
    $ring = 2 * M_PI * 30;

    // AI advisor — rule-based, every line names its cause.
    $insights = [];
    if ($stats['overdue'] > 0) $insights[] = ['risk', $stats['overdue'].' '.\Illuminate\Support\Str::plural('task', $stats['overdue']).' overdue', 'overdue'];
    if ($dueToday > 0) $insights[] = ['warn', $dueToday.' due today', 'today'];
    if ($unassigned > 0) $insights[] = ['warn', $unassigned.' '.\Illuminate\Support\Str::plural('task', $unassigned).' have no assignee', 'unassigned'];
    if ($stats['needApproval'] > 0) $insights[] = ['info', $stats['needApproval'].' awaiting approval', 'review'];
    if (empty($insights)) $insights[] = ['ok', 'On track — no blockers detected', 'ok'];

    // "Total Tasks" and "Overdue" are left off this grid — the Universal
    // Module Header already carries those two exact counts.
    $kpis = [
        ['Due Today', $dueToday, $dueToday > 0 ? 'warn' : null],
        ['Need Approval', $stats['needApproval'], $stats['needApproval'] > 0 ? 'warn' : null],
        ['Completed', $stats['pct'].'%', 'live'],
        ['Assigned to Me', $stats['mine'], null],
        ['Open', $openCount, null],
        ['Done', $doneCount, 'ok'],
    ];

    $taskHex = \App\Models\Event::moduleColor('tasks');
@endphp

<div class="flex flex-col overflow-hidden rounded-lg border border-line bg-white">
    <div class="flex items-center gap-2.5 border-b border-line px-4 py-3">
        <span class="relative flex h-7 w-7 items-center justify-center rounded-lg text-white shadow-sm" style="background: {{ $taskHex }}">
            <x-icon name="{{ \App\Models\Event::moduleIcon('tasks') }}" class="h-3.5 w-3.5" />
        </span>
        <span class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Task Control Center</span>
        <button type="button" wire:click="setView('list')" class="ml-auto flex items-center gap-1 rounded-lg border border-line bg-white px-2 py-1 text-3xs font-bold text-muted transition hover:border-navy-300 hover:text-gold-700" title="List view">
            <x-icon name="cog" class="h-3 w-3" /> Customize
        </button>
    </div>

    <div class="grid gap-4 p-4" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr))">
        {{-- task overview KPIs --}}
        <div>
            <div class="mb-1.5 flex items-center justify-between">
                <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Task Overview</p>
                <span class="rounded-lg bg-page px-2 py-0.5 text-3xs font-bold text-muted">This Event</span>
            </div>
            <div class="grid grid-cols-2 gap-2">
                @foreach ($kpis as [$label, $val, $tone])
                    <x-eo.metric-pill :label="$label" :value="$val" :tone="$tone" class="!min-w-0 !px-3 !py-2.5" />
                @endforeach
            </div>
        </div>

        {{-- ══ Event Progress │ Quick Actions ══ --}}
        <div class="grid gap-3 xl:grid-cols-2">
            {{-- event progress donut --}}
            <div class="rounded-lg border border-line bg-page p-3.5">
                <p class="mb-2 text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Event Progress</p>
                <div class="flex items-center justify-center">
                    <div class="relative shrink-0">
                        <svg class="h-[76px] w-[76px] -rotate-90" viewBox="0 0 72 72">
                            <circle cx="36" cy="36" r="30" fill="none" stroke="var(--color-page)" stroke-width="7"/>
                            <circle cx="36" cy="36" r="30" fill="none" stroke="var(--color-gold-500)" stroke-width="7" stroke-linecap="round" stroke-dasharray="{{ $ring }}" stroke-dashoffset="{{ $ring - ($ring * $stats['pct'] / 100) }}"/>
                        </svg>
                        <span class="absolute inset-0 flex items-center justify-center text-base font-black text-ink">{{ $stats['pct'] }}%</span>
                    </div>
                </div>
                <div class="mt-2 space-y-1">
                    @foreach (\App\Models\Task::STAGES as $sv => [$slabel, $shex, $sopen])
                        @if (($gateCounts[$sv] ?? 0) > 0)
                            <div class="flex items-center gap-1.5 text-3xs">
                                <span class="h-1.5 w-1.5 rounded-full" style="background: {{ $shex }}"></span>
                                <span class="truncate text-muted">{{ $slabel }}</span>
                                <span class="ml-auto font-black text-ink">{{ $gateCounts[$sv] }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- quick actions --}}
            <div class="rounded-lg border border-line bg-page p-3.5">
                <p class="mb-2 text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Quick Actions</p>
                <div class="space-y-1.5">
                    <button type="button" wire:click="addTask" class="flex w-full items-center gap-2 rounded-lg border border-line bg-white px-3 py-2 text-left text-micro font-bold text-ink shadow-sm transition hover:-translate-y-0.5 hover:border-navy-300 hover:shadow-float"><x-icon name="clipboard" class="h-3.5 w-3.5 text-gold-600" /> New Task</button>
                    <button type="button" wire:click="setFocus('mine')" class="flex w-full items-center gap-2 rounded-lg border border-line bg-white px-3 py-2 text-left text-micro font-bold text-ink shadow-sm transition hover:-translate-y-0.5 hover:border-navy-300 hover:shadow-float"><x-icon name="users" class="h-3.5 w-3.5 text-gold-600" /> My Tasks</button>
                    <button type="button" wire:click="setFocus('overdue')" class="flex w-full items-center gap-2 rounded-lg border border-line bg-white px-3 py-2 text-left text-micro font-bold text-ink shadow-sm transition hover:-translate-y-0.5 hover:border-navy-300 hover:shadow-float"><x-icon name="bell" class="h-3.5 w-3.5 text-gold-600" /> Overdue</button>
                    <button type="button" wire:click="setView('timeline')" class="flex w-full items-center gap-2 rounded-lg border border-line bg-white px-3 py-2 text-left text-micro font-bold text-ink shadow-sm transition hover:-translate-y-0.5 hover:border-navy-300 hover:shadow-float"><x-icon name="chart" class="h-3.5 w-3.5 text-gold-600" /> Timeline</button>
                    <a href="{{ route('events.hub', [$event, 'tab' => 'planning']) }}" class="flex w-full items-center gap-2 rounded-lg border border-line bg-white px-3 py-2 text-left text-micro font-bold text-ink shadow-sm transition hover:-translate-y-0.5 hover:border-navy-300 hover:shadow-float"><x-icon name="list" class="h-3.5 w-3.5 text-gold-600" /> Open Plan</a>
                    <a href="{{ route('events.hub', [$event, 'tab' => 'reports']) }}" class="flex w-full items-center gap-2 rounded-lg border border-line bg-white px-3 py-2 text-left text-micro font-bold text-ink shadow-sm transition hover:-translate-y-0.5 hover:border-navy-300 hover:shadow-float"><x-icon name="archive" class="h-3.5 w-3.5 text-gold-600" /> Reports</a>
                </div>
            </div>
        </div>

        {{-- Team Workload — the "Summary" panel that used to sit beside this
             restated Total/Completed/Open/Overdue/Approval a second time,
             within a few inches of the KPI grid above. Removed outright
             rather than trimmed — everything in it survives elsewhere on
             this panel or in the Header. --}}
        <div class="rounded-lg border border-line bg-page p-3.5">
            <p class="mb-2 text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Team Workload</p>
            @if ($workload->isNotEmpty())
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($workload as $uid => $count)
                        @php $u = $usersById[$uid] ?? null; @endphp
                        @continue (! $u)
                        <div class="flex items-center gap-1.5">
                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-navy-900 text-3xs font-bold text-gold-400" title="{{ $u->name }}">{{ $ini($u->name) }}</span>
                            <span class="min-w-0 flex-1 truncate text-3xs font-semibold text-ink">{{ \Illuminate\Support\Str::before($u->name, ' ') }}</span>
                            <div class="h-1.5 w-10 shrink-0 overflow-hidden rounded-full bg-page"><div class="h-full rounded-full bg-gold-500" style="width: {{ round($count / $workMax * 100) }}%"></div></div>
                            <span class="w-4 shrink-0 text-right text-3xs font-black text-muted">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-3xs italic text-muted">No one is assigned open tasks yet.</p>
            @endif
        </div>

        {{-- AI assistant --}}
        <div class="rounded-lg border border-line bg-gradient-to-br from-white to-page p-3.5">
            <div class="flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-gold-500 text-navy-900 shadow"><x-icon name="sparkles" class="h-3.5 w-3.5" /></span>
                <span class="text-micro font-bold uppercase tracking-[0.14em] text-ink">AI Assistant</span>
                <span class="ml-auto rounded-full bg-page px-1.5 py-0.5 text-3xs font-bold uppercase tracking-wide text-muted">Beta</span>
            </div>
            <div class="mt-2.5 space-y-1.5">
                @foreach ($insights as [$tone, $text, $act])
                    @php $dot = ['risk' => 'bg-danger', 'warn' => 'bg-warning', 'info' => 'bg-info', 'ok' => 'bg-success'][$tone]; @endphp
                    <div class="flex items-center gap-2 rounded-lg bg-white px-2.5 py-1.5 ring-1 ring-line">
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $dot }}"></span>
                        <span class="flex-1 text-3xs font-semibold text-ink">{{ $text }}</span>
                        @if ($act === 'overdue')
                            <button type="button" wire:click="setFocus('overdue')" class="text-3xs font-bold text-gold-700 hover:underline">View</button>
                        @elseif ($act === 'unassigned' || $act === 'today')
                            <button type="button" wire:click="setFocus('all')" class="text-3xs font-bold text-gold-700 hover:underline">View</button>
                        @endif
                    </div>
                @endforeach
            </div>
            <div class="mt-2.5 flex items-center gap-2 rounded-lg border border-line bg-white px-2.5 py-1.5">
                <input type="text" placeholder="Ask AI Assistant" class="flex-1 bg-transparent text-3xs text-ink placeholder:text-muted focus:outline-none" disabled>
                <span class="flex h-5 w-5 items-center justify-center rounded-md bg-navy-900 text-gold-400"><x-icon name="sparkles" class="h-3 w-3" /></span>
            </div>
        </div>

        {{-- primary CTA --}}
        <a href="{{ route('events.hub', [$event, 'tab' => 'reports']) }}"
           class="flex items-center justify-center gap-2 rounded-full bg-navy-900 px-4 py-3 text-xs font-bold text-white shadow-float transition hover:bg-navy-800">
            <x-icon name="chart" class="h-4 w-4 text-gold-400" /> View Full Task Report
        </a>
    </div>
</div>
