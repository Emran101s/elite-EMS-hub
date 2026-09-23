<div x-data="{ toasts: [], push(e) { const id = Date.now() + Math.random(); this.toasts.push({ id, message: e.detail.message }); setTimeout(() => this.toasts = this.toasts.filter(t => t.id !== id), 2600); } }" @my-work-toast.window="push($event)">
    {{-- toast host — the same one the event board uses --}}
    <div class="pointer-events-none fixed bottom-6 end-6 z-[60] flex flex-col gap-2" aria-live="polite">
        <template x-for="t in toasts" :key="t.id">
            <div class="pointer-events-auto flex items-center gap-2 rounded-lg bg-navy-900 px-4 py-2.5 text-xs font-semibold text-white shadow-float" x-transition>
                <span class="h-1.5 w-1.5 rounded-full bg-success"></span><span x-text="t.message"></span>
            </div>
        </template>
    </div>

    <x-cc.header eyebrow="Task Command" title="My work"
                 :subtitle="$openCount === 0
                    ? 'Nothing on your plate right now.'
                    : $openCount.' open '.str('task')->plural($openCount).($overdueCount ? ', '.$overdueCount.' overdue' : '').($waitingOnMe->isNotEmpty() ? ' · '.$waitingOnMe->count().' '.str('approval')->plural($waitingOnMe->count()).' waiting on you' : '').'.'" />

    <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
        {{-- ── My tasks ── --}}
        <div class="space-y-3">
            @foreach ($buckets as $label => $tasks)
                @continue($tasks->isEmpty())
                <section class="overflow-hidden rounded-lg border border-line bg-white" aria-labelledby="bucket-{{ str($label)->slug() }}">
                    <div class="flex items-center gap-2 border-b border-line bg-page px-4 py-2.5">
                        <h2 id="bucket-{{ str($label)->slug() }}" class="text-[13px] font-bold {{ $label === 'Overdue' ? 'text-danger-ink' : 'text-ink' }}">{{ $label }}</h2>
                        <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-bold tabular-nums text-muted">{{ $tasks->count() }}</span>
                    </div>
                    <div class="divide-y divide-line">
                        @foreach ($tasks as $task)
                            <div wire:key="task-{{ $task->id }}" class="relative flex items-center gap-4 px-4 py-2.5">
                                <span class="absolute inset-y-2.5 start-0 w-[3px] rounded-full" style="background: {{ $task->stageHex() }}"></span>

                                <div class="min-w-0 flex-1">
                                    <x-record-title :record="$task" fallback="Untitled task" class="max-w-full text-[13.5px] font-semibold text-ink" />
                                    <p class="mt-0.5 truncate text-[11.5px] text-muted">
                                        @if ($task->event)
                                            <a href="{{ route('events.hub', [$task->event, 'tab' => 'tasks']) }}" wire:navigate class="hover:text-ink hover:underline">{{ $task->event->name }}</a>
                                        @else
                                            No event
                                        @endif
                                        · {{ $task->stageLabel() }}
                                        @if (in_array($task->priority, ['high', 'urgent'], true))
                                            · <span class="font-semibold {{ $task->priority === 'urgent' ? 'text-danger-ink' : 'text-warning-ink' }}">{{ $task->priorityLabel() }}</span>
                                        @endif
                                    </p>
                                </div>

                                <span class="hidden w-24 shrink-0 text-end text-[11.5px] font-semibold sm:block {{ $label === 'Overdue' ? 'text-danger-ink' : 'text-muted' }}">
                                    <x-date :value="$task->due_on" />
                                </span>

                                <div class="flex shrink-0 items-center gap-1.5">
                                    @if ($task->status === 'review')
                                        <span class="rounded-full bg-warning-soft px-2.5 py-1 text-[10.5px] font-bold text-warning-ink">Awaiting sign-off</span>
                                    @else
                                        @if ($task->status === 'todo')
                                            <button type="button" wire:click="move({{ $task->id }}, 'doing')" wire:loading.attr="disabled"
                                                    class="rounded-full border border-line bg-white px-3 py-1 text-[11.5px] font-bold text-ink transition hover:border-navy-300">Start</button>
                                        @endif
                                        <button type="button" wire:click="move({{ $task->id }}, 'done')" wire:loading.attr="disabled"
                                                class="rounded-full border border-line bg-white px-3 py-1 text-[11.5px] font-bold text-ink transition hover:border-success hover:text-success"
                                                aria-label="Mark “{{ $task->title }}” done">Done</button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            @if ($openCount === 0)
                <div class="rounded-lg border border-line bg-white px-4 py-8 text-center text-[13px] text-muted">
                    No open tasks assigned to you. <a href="{{ route('tasks.index') }}" wire:navigate class="font-semibold text-ink hover:underline">See everyone's tasks →</a>
                </div>
            @endif
        </div>

        {{-- ── Side rail: decisions and events ── --}}
        <div class="space-y-3">
            @can('decide-approvals')
                <section class="overflow-hidden rounded-lg border border-line bg-white" aria-labelledby="waiting-on-me">
                    <div class="flex items-center gap-2 border-b border-line bg-page px-4 py-2.5">
                        <h2 id="waiting-on-me" class="text-[13px] font-bold text-ink">Waiting on you</h2>
                        <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-bold tabular-nums text-muted">{{ $waitingOnMe->count() }}</span>
                    </div>
                    <div class="divide-y divide-line">
                        @forelse ($waitingOnMe as $approval)
                            <a href="{{ route('events.hub', [$approval->event, 'tab' => 'approvals']) }}" wire:navigate wire:key="wait-{{ $approval->id }}"
                               class="block px-4 py-2.5 transition hover:bg-page">
                                <span class="block truncate text-[13px] font-semibold text-ink">{{ $approval->title }}</span>
                                <span class="mt-0.5 block truncate text-[11.5px] text-muted">
                                    {{ $approval->event?->name }} · from {{ $approval->requester?->name ?? 'someone' }} · <x-date :value="$approval->created_at" />
                                </span>
                            </a>
                        @empty
                            <p class="px-4 py-5 text-center text-[12.5px] text-muted">No decisions waiting on you.</p>
                        @endforelse
                    </div>
                </section>
            @endcan

            @if ($waitingOnOthers->isNotEmpty())
                <section class="overflow-hidden rounded-lg border border-line bg-white" aria-labelledby="waiting-on-others">
                    <div class="flex items-center gap-2 border-b border-line bg-page px-4 py-2.5">
                        <h2 id="waiting-on-others" class="text-[13px] font-bold text-ink">You're waiting on</h2>
                        <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-bold tabular-nums text-muted">{{ $waitingOnOthers->count() }}</span>
                    </div>
                    <div class="divide-y divide-line">
                        @foreach ($waitingOnOthers as $approval)
                            <a href="{{ route('events.hub', [$approval->event, 'tab' => 'approvals']) }}" wire:navigate wire:key="mine-{{ $approval->id }}"
                               class="block px-4 py-2.5 transition hover:bg-page">
                                <span class="block truncate text-[13px] font-semibold text-ink">{{ $approval->title }}</span>
                                <span class="mt-0.5 block truncate text-[11.5px] text-muted">
                                    {{ $approval->event?->name }} · with {{ $approval->currentStep()?->assigneeLabel() ?? 'a manager' }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="overflow-hidden rounded-lg border border-line bg-white" aria-labelledby="my-events">
                <div class="flex items-center gap-2 border-b border-line bg-page px-4 py-2.5">
                    <h2 id="my-events" class="text-[13px] font-bold text-ink">My events</h2>
                    <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-bold tabular-nums text-muted">{{ $events->count() }}</span>
                </div>
                <div class="divide-y divide-line">
                    @forelse ($events as $event)
                        <a href="{{ route('events.hub', $event) }}" wire:navigate wire:key="event-{{ $event->id }}"
                           class="flex items-center gap-3 px-4 py-2.5 transition hover:bg-page">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[13px] font-semibold text-ink">{{ $event->name }}</span>
                                <span class="mt-0.5 block truncate text-[11.5px] text-muted"><x-date :value="$event->starts_at" /></span>
                            </span>
                            @if ($event->my_open_tasks)
                                <span class="shrink-0 rounded-full bg-page px-2 py-0.5 text-[11px] font-bold tabular-nums text-muted"
                                      title="Your open tasks on this event">{{ $event->my_open_tasks }}</span>
                            @endif
                        </a>
                    @empty
                        <p class="px-4 py-5 text-center text-[12.5px] text-muted">You're not on any event's team yet.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</div>
