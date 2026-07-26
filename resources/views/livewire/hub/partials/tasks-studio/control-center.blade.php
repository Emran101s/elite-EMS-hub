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

    $kpis = [
        ['Total Tasks', $stats['total'], 'text-navy-900'],
        ['Overdue', $stats['overdue'], $stats['overdue'] > 0 ? 'text-red-600' : 'text-navy-900'],
        ['Due Today', $dueToday, $dueToday > 0 ? 'text-amber-600' : 'text-navy-900'],
        ['Need Approval', $stats['needApproval'], $stats['needApproval'] > 0 ? 'text-orange-600' : 'text-navy-900'],
        ['Completed', $stats['pct'].'%', 'text-emerald-600'],
        ['Assigned to Me', $stats['mine'], 'text-navy-900'],
        ['Open', $openCount, 'text-navy-900'],
        ['Done', $doneCount, 'text-emerald-600'],
    ];
@endphp

<div class="cc-panel flex flex-col">
    {{-- dark mini header --}}
    <div class="cc-head">
        <div class="pointer-events-none absolute -right-6 -top-10 h-28 w-28 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.28),transparent_70%)]"></div>
        <x-icon name="sparkles" class="relative h-4 w-4 text-gold-400" />
        <span class="relative text-2xs font-bold uppercase tracking-[0.18em] text-white">Task Control Center</span>
        <button type="button" wire:click="setView('list')" class="relative ml-auto flex items-center gap-1 rounded-lg bg-white/[0.08] px-2 py-1 text-3xs font-bold text-white/70 ring-1 ring-white/10 transition hover:text-white" title="List view">
            <x-icon name="cog" class="h-3 w-3" /> Customize
        </button>
    </div>

    <div class="grid gap-4 p-4" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr))">
        {{-- The view switcher moved to the toolbar, where it sits beside the
             filters it changes. --}}

        {{-- task overview KPIs --}}
        <div>
            <div class="mb-1.5 flex items-center justify-between">
                <p class="eyebrow">Task Overview</p>
                <span class="rounded-lg bg-navy-50 px-2 py-0.5 text-3xs font-bold text-navy-500">This Event</span>
            </div>
            <div class="grid grid-cols-2 gap-2">
                @foreach ($kpis as [$label, $val, $tone])
                    <div class="cc-tile">
                        <span class="cc-kpi {{ $tone }}">{{ $val }}</span>
                        <span class="cc-kpi-label">{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ══ Event Progress │ Quick Actions ══ --}}
        <div class="grid gap-3 xl:grid-cols-2">
            {{-- event progress donut --}}
            <div class="rounded-2xl border border-line bg-page/40 p-3.5">
                <p class="eyebrow mb-2">Event Progress</p>
                <div class="flex items-center justify-center">
                    <div class="relative shrink-0">
                        <svg class="h-[76px] w-[76px] -rotate-90" viewBox="0 0 72 72">
                            <circle cx="36" cy="36" r="30" fill="none" stroke="var(--color-navy-50)" stroke-width="7"/>
                            <circle cx="36" cy="36" r="30" fill="none" stroke="var(--color-gold-500)" stroke-width="7" stroke-linecap="round" stroke-dasharray="{{ $ring }}" stroke-dashoffset="{{ $ring - ($ring * $stats['pct'] / 100) }}"/>
                        </svg>
                        <span class="pf absolute inset-0 flex items-center justify-center text-base font-black text-navy-900">{{ $stats['pct'] }}%</span>
                    </div>
                </div>
                <div class="mt-2 space-y-1">
                    @foreach (\App\Models\Task::STAGES as $sv => [$slabel, $shex, $sopen])
                        @if (($gateCounts[$sv] ?? 0) > 0)
                            <div class="flex items-center gap-1.5 text-3xs">
                                <span class="h-1.5 w-1.5 rounded-full" style="background: {{ $shex }}"></span>
                                <span class="truncate text-navy-500">{{ $slabel }}</span>
                                <span class="ml-auto font-black text-navy-800">{{ $gateCounts[$sv] }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- quick actions --}}
            <div class="rounded-2xl border border-line bg-page/40 p-3.5">
                <p class="eyebrow mb-2">Quick Actions</p>
                <div class="space-y-1.5">
                    <button type="button" wire:click="addTask" class="qa-btn w-full"><x-icon name="clipboard" class="h-3.5 w-3.5 text-gold-700" /> New Task</button>
                    <button type="button" wire:click="setFocus('mine')" class="qa-btn w-full"><x-icon name="users" class="h-3.5 w-3.5 text-gold-700" /> My Tasks</button>
                    <button type="button" wire:click="setFocus('overdue')" class="qa-btn w-full"><x-icon name="bell" class="h-3.5 w-3.5 text-gold-700" /> Overdue</button>
                    <button type="button" wire:click="setView('timeline')" class="qa-btn w-full"><x-icon name="chart" class="h-3.5 w-3.5 text-gold-700" /> Timeline</button>
                    <a href="{{ route('events.hub', [$event, 'tab' => 'planning']) }}" class="qa-btn w-full"><x-icon name="list" class="h-3.5 w-3.5 text-gold-700" /> Open Plan</a>
                    <a href="{{ route('events.hub', [$event, 'tab' => 'reports']) }}" class="qa-btn w-full"><x-icon name="archive" class="h-3.5 w-3.5 text-gold-700" /> Reports</a>
                </div>
            </div>
        </div>

        {{-- ══ Team Workload │ Summary ══ --}}
        <div class="grid gap-3 xl:grid-cols-2">
            {{-- team workload --}}
            <div class="rounded-2xl border border-line bg-page/40 p-3.5">
                <p class="eyebrow mb-2">Team Workload</p>
                @if ($workload->isNotEmpty())
                    <div class="space-y-2">
                        @foreach ($workload as $uid => $count)
                            @php $u = $usersById[$uid] ?? null; @endphp
                            @continue (! $u)
                            <div class="flex items-center gap-1.5">
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-navy-800 to-navy-950 text-3xs font-bold text-gold-300" title="{{ $u->name }}">{{ $ini($u->name) }}</span>
                                <span class="min-w-0 flex-1 truncate text-3xs font-semibold text-navy-700">{{ \Illuminate\Support\Str::before($u->name, ' ') }}</span>
                                <div class="h-1.5 w-10 shrink-0 overflow-hidden rounded-full bg-navy-50"><div class="h-full rounded-full bg-gradient-to-r from-gold-400 to-gold-500" style="width: {{ round($count / $workMax * 100) }}%"></div></div>
                                <span class="w-4 shrink-0 text-right text-3xs font-black text-navy-500">{{ $count }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-3xs italic text-navy-300">No one is assigned open tasks yet.</p>
                @endif
            </div>

            {{-- summary --}}
            <div class="rounded-2xl border border-line bg-page/40 p-3.5">
                <p class="eyebrow mb-2">Summary</p>
                <dl class="space-y-1 text-3xs">
                    @foreach ([['Total', $stats['total'], 'text-navy-800'], ['Completed', $doneCount, 'text-emerald-600'], ['Open', $openCount, 'text-navy-800'], ['Overdue', $stats['overdue'], 'text-red-600'], ['Approval', $stats['needApproval'], 'text-orange-600']] as [$l, $v, $t])
                        <div class="flex items-center justify-between">
                            <dt class="text-navy-500">{{ $l }}</dt>
                            <dd class="font-black {{ $t }}">{{ $v }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>

        {{-- AI assistant --}}
        <div class="rounded-2xl border border-line bg-gradient-to-br from-white to-page/60 p-3.5">
            <div class="flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-gradient-to-br from-[var(--plasma-lit)] to-[var(--gold-lit)] text-white shadow"><x-icon name="sparkles" class="h-3.5 w-3.5" /></span>
                <span class="text-micro font-bold uppercase tracking-[0.14em] text-navy-700">AI Assistant</span>
                <span class="ml-auto rounded-full bg-navy-50 px-1.5 py-0.5 text-3xs font-bold uppercase tracking-wide text-navy-400">Beta</span>
            </div>
            <div class="mt-2.5 space-y-1.5">
                @foreach ($insights as [$tone, $text, $act])
                    @php $dot = ['risk' => 'bg-red-500', 'warn' => 'bg-amber-500', 'info' => 'bg-blue-500', 'ok' => 'bg-emerald-500'][$tone]; @endphp
                    <div class="flex items-center gap-2 rounded-lg bg-white px-2.5 py-1.5 ring-1 ring-line">
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $dot }}"></span>
                        <span class="flex-1 text-3xs font-semibold text-navy-700">{{ $text }}</span>
                        @if ($act === 'overdue')
                            <button type="button" wire:click="setFocus('overdue')" class="text-3xs font-bold text-gold-600 hover:underline">View</button>
                        @elseif ($act === 'unassigned' || $act === 'today')
                            <button type="button" wire:click="setFocus('all')" class="text-3xs font-bold text-gold-600 hover:underline">View</button>
                        @endif
                    </div>
                @endforeach
            </div>
            <div class="mt-2.5 flex items-center gap-2 rounded-lg border border-line bg-white px-2.5 py-1.5">
                <input type="text" placeholder="Ask AI Assistant" class="flex-1 bg-transparent text-3xs text-navy-700 placeholder:text-navy-300 focus:outline-none" disabled>
                <span class="flex h-5 w-5 items-center justify-center rounded-md bg-navy-900 text-white"><x-icon name="sparkles" class="h-3 w-3" /></span>
            </div>
        </div>

        {{-- primary CTA --}}
        <a href="{{ route('events.hub', [$event, 'tab' => 'reports']) }}"
           class="flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-navy-900 to-navy-950 px-4 py-3 text-xs font-bold text-white shadow-[0_10px_26px_-10px_rgba(11,31,58,0.7)] transition hover:brightness-110">
            <x-icon name="chart" class="h-4 w-4 text-gold-400" /> View Full Task Report
        </a>
    </div>
</div>
