<div x-data="{ toasts: [], push(e) { const id = Date.now() + Math.random(); this.toasts.push({ id, message: e.detail.message }); setTimeout(() => this.toasts = this.toasts.filter(t => t.id !== id), 2600); } }" @tasks-toast.window="push($event)">
    @php
        // Completion counts what the MODEL calls finished, not what this page
        // used to guess. Task::STAGES marks 'approved' as still open — it is
        // approved to proceed, not delivered. Cancelled work is neither done
        // nor outstanding, so it leaves the denominator.
        $doneCount = $counts['done'] ?? 0;
        $cancelledCount = $counts['cancelled'] ?? 0;
        $inScope = max($counts->sum() - $cancelledCount, 1);
        $donePct = (int) round($doneCount / $inScope * 100);

        $stages = collect(\App\Models\Task::STAGES)->mapWithKeys(fn ($stage, $key) => [
            $key => ['label' => \App\Support\Workflow::label('task_stage', $key), 'hex' => $stage[1], 'count' => $counts[$key] ?? 0],
        ]);

        $active = collect($tasks->items())->filter(fn ($t) => $t->isOpen());
        $closed = collect($tasks->items())->reject(fn ($t) => $t->isOpen());

        // The badges count the whole filtered list, not this page of it.
        $scoped = $statusFilter ? $counts->only([$statusFilter]) : $counts;
        $activeTotal = $scoped->filter(fn ($c, $k) => \App\Models\Task::STAGES[$k][2] ?? true)->sum();
        $closedTotal = $scoped->sum() - $activeTotal;

        $canWrite = auth()->user()->can('write');
        $pageIds = collect($tasks->items())->pluck('id')->all();
        $control = 'h-9 rounded-lg border border-line bg-white px-2.5 text-[12.5px] text-ink focus:border-navy-300 focus:outline-none';
    @endphp

    {{-- toast host — the same one the event board uses --}}
    <div class="pointer-events-none fixed bottom-6 end-6 z-[60] flex flex-col gap-2" aria-live="polite">
        <template x-for="t in toasts" :key="t.id">
            <div class="pointer-events-auto flex items-center gap-2 rounded-lg bg-navy-900 px-4 py-2.5 text-xs font-semibold text-white shadow-float" x-transition>
                <span class="h-1.5 w-1.5 rounded-full bg-success"></span><span x-text="t.message"></span>
            </div>
        </template>
    </div>

    <x-cc.header eyebrow="Task Command" title="Tasks" subtitle="Everything on the to-do list across events and projects." />

    <div class="mt-4 grid gap-3 lg:grid-cols-[1fr_260px]">
        <x-tasks.stage-flow :stages="$stages" :live="true" :active="$statusFilter" />

        <div class="flex items-center gap-3.5 rounded-lg border border-line bg-white p-3.5">
            <div class="ccx-ring h-[72px] w-[72px] shrink-0" style="--ccx-ring: var(--color-success); --ccx-ring-pct: {{ $donePct }}%">
                <span class="ccx-ring-value !text-[16px]">{{ $donePct }}%</span>
            </div>
            <div class="min-w-0">
                <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Completion</p>
                <p class="mt-1 text-[13px] font-semibold text-ink">{{ $doneCount }} of {{ $inScope }} done</p>
                <p class="mt-0.5 text-[11.5px] text-muted">
                    @if ($cancelledCount)
                        {{ $cancelledCount }} cancelled, not counted
                    @else
                        Delivered, not just approved
                    @endif
                </p>
            </div>
        </div>
    </div>

    {{-- ── Filters ── --}}
    <div class="mt-4 flex flex-wrap items-center gap-2" role="search" aria-label="Filter tasks">
        <label class="sr-only" for="tasks-q">Search tasks</label>
        <input id="tasks-q" type="search" wire:model.live.debounce.300ms="q" placeholder="Search tasks…" class="{{ $control }} w-56">

        <label class="sr-only" for="tasks-who">Owner</label>
        <select id="tasks-who" wire:model.live="who" class="{{ $control }}">
            <option value="">Anyone</option>
            <option value="me">Me</option>
            <option value="unassigned">Unassigned</option>
            @foreach ($people as $person)
                <option value="{{ $person->id }}">{{ $person->name }}</option>
            @endforeach
        </select>

        <label class="sr-only" for="tasks-event">Event</label>
        <select id="tasks-event" wire:model.live="event" class="{{ $control }} max-w-[14rem]">
            <option value="">All events</option>
            @foreach ($events as $ev)
                <option value="{{ $ev->id }}">{{ $ev->name }}</option>
            @endforeach
        </select>

        <label class="sr-only" for="tasks-due">Due</label>
        <select id="tasks-due" wire:model.live="due" class="{{ $control }}">
            <option value="">Any due date</option>
            <option value="overdue">Overdue</option>
            <option value="week">Next 7 days</option>
            <option value="none">No due date</option>
        </select>

        @if ($filtered)
            <button type="button" wire:click="clearFilters" class="h-9 rounded-full px-3 text-[12px] font-semibold text-muted hover:text-ink">Clear filters</button>
        @endif
    </div>

    {{-- ── Selection bar ── --}}
    @if ($canWrite && count($selected))
        <div class="sticky top-2 z-20 mt-3 flex flex-wrap items-center gap-2 rounded-lg bg-navy-900 px-4 py-2.5 text-white shadow-float">
            <span class="text-[12.5px] font-bold">{{ count($selected) }} selected</span>
            <label class="sr-only" for="bulk-status">Move selected to</label>
            <select id="bulk-status" x-on:change="$wire.bulkStatus($event.target.value); $event.target.value = ''"
                    class="h-8 rounded-lg border-0 bg-white/10 px-2.5 text-[12px] font-semibold text-white focus:outline-none">
                <option value="" class="text-ink">Move to…</option>
                @foreach (\App\Models\Task::STAGES as $key => $stage)
                    <option value="{{ $key }}" class="text-ink">{{ \App\Support\Workflow::label('task_stage', $key) }}</option>
                @endforeach
            </select>
            <label class="sr-only" for="bulk-assign">Assign selected to</label>
            <select id="bulk-assign" x-on:change="if ($event.target.value !== '-') { $wire.bulkAssign($event.target.value); } $event.target.value = '-'"
                    class="h-8 rounded-lg border-0 bg-white/10 px-2.5 text-[12px] font-semibold text-white focus:outline-none">
                <option value="-" class="text-ink">Assign to…</option>
                <option value="" class="text-ink">Nobody</option>
                @foreach ($people as $person)
                    <option value="{{ $person->id }}" class="text-ink">{{ $person->name }}</option>
                @endforeach
            </select>
            <button type="button" wire:click="clearSelection" class="ms-auto text-[12px] font-semibold text-white/70 hover:text-white">Clear</button>
        </div>
    @endif

    <div class="mt-3 space-y-3">
        @foreach (['Active' => [$active, $activeTotal], 'Closed' => [$closed, $closedTotal]] as $heading => [$list, $total])
            @continue($heading === 'Closed' && $list->isEmpty())
            <div class="overflow-hidden rounded-lg border border-line bg-white">
                <div class="flex items-center gap-2 border-b border-line bg-page px-4 py-2.5">
                    @if ($canWrite && $heading === 'Active' && $list->isNotEmpty())
                        <input type="checkbox" aria-label="Select every task on this page"
                               @checked(count(array_intersect($pageIds, array_map('intval', $selected))) === count($pageIds))
                               x-on:change="$event.target.checked ? $wire.selectMany(@js($pageIds)) : $wire.clearSelection()"
                               class="h-4 w-4 rounded border-line text-navy-900">
                    @endif
                    <h2 class="text-[13px] font-bold text-ink">{{ $heading }}</h2>
                    <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-bold tabular-nums text-muted">{{ $total }}</span>
                    {{-- The badge is the real total; the list is one page of it. --}}
                    @if ($list->count() < $total)
                        <span class="text-[10.5px] text-muted">showing {{ $list->count() }} on this page</span>
                    @endif
                </div>
                <div class="divide-y divide-line">
                    @forelse ($list as $task)
                        @include('livewire.partials.tasks-index-row', ['task' => $task])
                    @empty
                        <p class="px-4 py-7 text-center text-[13px] text-muted">Nothing active on this page.</p>
                    @endforelse
                </div>
            </div>
        @endforeach

        @if ($tasks->isEmpty())
            <div class="rounded-lg border border-line bg-white px-4 py-8 text-center text-[13px] text-muted">
                @if ($filtered)
                    No tasks match these filters. <button type="button" wire:click="clearFilters" class="font-semibold text-ink hover:underline">Clear filters</button>
                @else
                    No tasks yet.
                @endif
            </div>
        @endif
    </div>

    <div class="mt-4">{{ $tasks->links() }}</div>
</div>
