@php
    $ini = fn ($n) => \Illuminate\Support\Str::of($n)->explode(' ')->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    $cols = 'grid-cols-[1fr_120px_92px_84px_84px_120px]';
@endphp
<div class="overflow-hidden rounded-lg border border-line bg-white">
    <div class="overflow-x-auto">
        {{-- The list owns which row is expanded — one `at`, so opening a row's
             checklist closes the last one and the table stays a table. --}}
        <div x-data="{ at: null }" class="min-w-[900px]">
            <div class="grid {{ $cols }} gap-2 border-b border-line bg-page px-4 py-2 text-eyebrow font-bold uppercase tracking-wide text-muted">
                <span>Task</span><span>Status</span><span>Owner</span><span>Start</span><span>Due</span><span>Progress</span>
            </div>

            @forelse ($byModule as $slug => $rows)
                @php [$mlabel, $mhex] = \App\Models\Task::MODULES[$slug] ?? [ucfirst($slug), 'var(--color-neutral)']; @endphp
                <div class="flex items-center gap-2 border-b border-line/70 bg-page px-4 py-1.5">
                    <span class="h-2.5 w-2.5 rounded-[3px]" style="background: {{ $mhex }}"></span>
                    <span class="text-eyebrow font-bold uppercase tracking-wide text-ink">{{ $mlabel }}</span>
                    <span class="text-eyebrow font-semibold text-muted">{{ $rows->count() }}</span>
                    <button type="button" wire:click="addTask('{{ $slug }}')" class="ml-auto shrink-0 text-eyebrow font-bold text-muted transition hover:text-gold-700">＋ Task</button>
                </div>

                @foreach ($rows as $item)
                    @php $prog = $item->progress(); [$cd, $ct] = $item->checklistProgress(); $overdue = $item->isOverdue(); $checklist = $item->checklist ?? []; @endphp
                    <div wire:key="tlir-{{ $item->id }}" class="border-b border-line/50">
                        <div class="grid {{ $cols }} items-center gap-2 px-4 py-2 transition hover:bg-page">
                            <div class="flex min-w-0 items-center gap-2">
                                @if (count($checklist))
                                    <button type="button" x-on:click="at = (at === {{ $item->id }} ? null : {{ $item->id }})"
                                            x-bind:aria-expanded="at === {{ $item->id }} ? 'true' : 'false'"
                                            class="text-muted transition hover:text-ink"><svg class="h-3 w-3 transition-transform duration-300" x-bind:class="at === {{ $item->id }} && 'rotate-90'" viewBox="0 0 20 20" fill="currentColor"><path d="M7 5l6 5-6 5V5z"/></svg></button>
                                @else<span class="w-3"></span>@endif
                                <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $item->priorityHex() }}" title="{{ $item->priorityLabel() }}"></span>
                                <button type="button" wire:click="openTask({{ $item->id }})" class="min-w-0 text-left text-xs font-semibold text-ink"><x-record-title :record="$item" fallback="Untitled task" :muted="in_array($item->status, ['done', 'cancelled'])" /></button>
                                @if ($item->isSigned())<span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-gold-500 text-eyebrow font-black text-navy-900" title="{{ $item->stageLabel() }}">✓</span>@endif
                                <span class="ml-auto">@include('livewire.hub.partials.tasks-studio.actions', ['item' => $item])</span>
                            </div>
                            <span class="inline-flex w-fit items-center gap-1 rounded-md bg-white px-2 py-0.5 text-eyebrow font-bold" style="color: {{ $item->stageHex() }}; box-shadow: inset 0 0 0 1px {{ $item->stageHex() }}33"><span class="h-1.5 w-1.5 rounded-full" style="background: {{ $item->stageHex() }}"></span>{{ $item->stageLabel() }}</span>
                            <span>@if ($item->assignee)<span class="flex h-5 w-5 items-center justify-center rounded-full bg-navy-900 text-eyebrow font-bold text-gold-400" title="{{ $item->assignee->name }}">{{ $ini($item->assignee->name) }}</span>@else<span class="text-eyebrow italic text-muted">—</span>@endif</span>
                            <span class="text-eyebrow font-medium text-muted">{{ $item->start_on?->format('j M') ?? '—' }}</span>
                            <span class="text-eyebrow font-medium {{ $overdue ? 'font-bold text-danger-ink' : 'text-muted' }}">{{ $item->due_on?->format('j M') ?? '—' }}</span>
                            <div class="flex items-center gap-1.5"><div class="h-1.5 flex-1 overflow-hidden rounded-full bg-page"><div class="h-full rounded-full" style="width: {{ max($prog, $prog > 0 ? 4 : 0) }}%; background: {{ $item->stageHex() }}"></div></div><span class="text-eyebrow font-black text-muted">{{ $prog }}%</span></div>
                        </div>

                        @if (count($checklist))
                            <div x-show="at === {{ $item->id }}" x-collapse.duration.300ms x-cloak
                                 class="space-y-1 border-t border-line/50 bg-page px-4 py-2 pl-11">
                                @foreach ($checklist as $ci)
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded border text-eyebrow text-white {{ ($ci['done'] ?? false) ? 'border-success bg-success' : 'border-line' }}">{{ ($ci['done'] ?? false) ? '✓' : '' }}</span>
                                        <span class="flex-1 truncate text-micro {{ ($ci['done'] ?? false) ? 'text-muted line-through' : 'text-ink' }}">{{ $ci['text'] ?? '' }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            @empty
                <x-empty icon="clipboard" title="No tasks match" hint="Nothing fits the current search or filter." class="!rounded-none !shadow-none">
                    <x-slot:actions>
                        <x-eo.button size="sm" wire:click="addTask">＋ Add a task</x-eo.button>
                    </x-slot:actions>
                </x-empty>
            @endforelse
        </div>
    </div>
</div>
