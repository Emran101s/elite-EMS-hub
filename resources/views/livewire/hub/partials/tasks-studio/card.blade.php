@php
    $prog = $item->progress();
    [$cd, $ct] = $item->checklistProgress();
    $overdue = $item->isOverdue();
    $closed = in_array($item->status, ['done', 'cancelled'], true);
    $ini = fn ($n) => \Illuminate\Support\Str::of($n)->explode(' ')->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    $ref = 'TSK-'.str_pad((string) $item->id, 3, '0', STR_PAD_LEFT);
    $reporter = $event->projectManager;
@endphp
<div data-card data-item-id="{{ $item->id }}" wire:key="tcard-{{ $item->id }}" class="group op-card cursor-grab active:cursor-grabbing">
    <div class="flex flex-1 flex-col p-3.5">
        {{-- header: type · ref · menu --}}
        <div class="flex items-center justify-between gap-2">
            <span class="flex items-center gap-1.5 text-3xs font-bold uppercase tracking-[0.16em] text-muted">
                <x-icon name="clipboard" class="h-3 w-3 text-gold-600" /> Task
            </span>
            <div class="flex items-center gap-1.5">
                <span class="rounded-md bg-page px-1.5 py-0.5 font-mono text-3xs font-bold text-muted">{{ $ref }}</span>
                @include('livewire.hub.partials.tasks-studio.actions', ['item' => $item])
            </div>
        </div>

        {{-- title --}}
        <button type="button" wire:click="openTask({{ $item->id }})" data-block-drag
                class="mt-2 block w-full text-left text-sm font-bold leading-snug text-ink"><x-record-title :record="$item" fallback="Untitled task" :muted="$closed" /></button>

        @if (trim((string) $item->description) !== '')
            <p class="mt-1 line-clamp-2 text-2xs leading-relaxed text-muted">{{ $item->description }}</p>
        @endif

        {{-- badges: module · priority --}}
        <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
            @if ($item->area)
                <span class="pill" style="background: {{ $item->moduleHex() }}1A; color: {{ $item->moduleHex() }}"><span class="h-1.5 w-1.5 rounded-full" style="background: {{ $item->moduleHex() }}"></span>{{ $item->moduleLabel() }}</span>
            @endif
            <span class="pill" style="background: {{ $item->priorityHex() }}1A; color: {{ $item->priorityHex() }}"><span class="h-1.5 w-1.5 rounded-full" style="background: {{ $item->priorityHex() }}"></span>{{ $item->priorityLabel() }}</span>
            @if ($item->isSigned())
                <span class="pill ml-auto bg-gold-50 text-gold-700">✓ {{ $item->stageLabel() }}</span>
            @endif
        </div>

        {{-- dates: due · start --}}
        <div class="mt-3 grid grid-cols-2 gap-2 border-t border-dashed border-line pt-2.5">
            <div>
                <p class="text-3xs font-bold uppercase tracking-[0.12em] text-muted">Due</p>
                <p class="mt-0.5 flex items-center gap-1 text-2xs font-bold {{ $overdue ? 'text-danger-ink' : 'text-ink' }}">
                    <x-icon name="calendar" class="h-3 w-3 opacity-70" />{{ $item->due_on?->format('j M Y') ?? '—' }}
                </p>
            </div>
            <div>
                <p class="text-3xs font-bold uppercase tracking-[0.12em] text-muted">Start</p>
                <p class="mt-0.5 flex items-center gap-1 text-2xs font-bold text-ink">
                    <x-icon name="calendar" class="h-3 w-3 opacity-70" />{{ $item->start_on?->format('j M Y') ?? '—' }}
                </p>
            </div>
        </div>

        {{-- people: assignee · reporter --}}
        <div class="mt-2.5 grid grid-cols-2 gap-2">
            <div>
                <p class="text-3xs font-bold uppercase tracking-[0.12em] text-muted">Assignee</p>
                <div class="mt-1 flex items-center gap-1.5">
                    @if ($item->assignee)
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-navy-900 text-3xs font-bold text-gold-400 ring-2 ring-white" title="{{ $item->assignee->name }}">{{ $ini($item->assignee->name) }}</span>
                        <span class="truncate text-micro font-semibold text-ink">{{ \Illuminate\Support\Str::before($item->assignee->name, ' ') }}</span>
                    @else
                        <span class="text-micro italic text-muted">Unassigned</span>
                    @endif
                </div>
            </div>
            <div>
                <p class="text-3xs font-bold uppercase tracking-[0.12em] text-muted">Reporter</p>
                <div class="mt-1 flex items-center gap-1.5">
                    @if ($reporter)
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-page text-3xs font-bold text-muted ring-2 ring-white" title="{{ $reporter->name }}">{{ $ini($reporter->name) }}</span>
                        <span class="truncate text-micro font-semibold text-ink">{{ \Illuminate\Support\Str::before($reporter->name, ' ') }}</span>
                    @else
                        <span class="text-micro italic text-muted">—</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- progress --}}
        <div class="mt-3 flex items-center gap-2">
            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-page"><div class="h-full rounded-full transition-all" style="width: {{ max($prog, $prog > 0 ? 4 : 0) }}%; background: {{ $item->stageHex() }}"></div></div>
            <span class="text-eyebrow font-black text-muted">{{ $prog }}%</span>
        </div>

        {{-- meta chips --}}
        <div class="mt-2.5 flex items-center gap-3 text-eyebrow font-bold text-muted">
            @if ($ct)
                <span class="flex items-center gap-1" title="{{ $cd }} of {{ $ct }} checklist done"><x-icon name="clipboard" class="h-3 w-3" />{{ $cd }}/{{ $ct }}</span>
            @endif
            @if ($item->track)
                <span class="flex items-center gap-1" title="Plan track"><span class="h-1.5 w-1.5 rounded-full" style="background: {{ $item->track->color }}"></span>{{ \Illuminate\Support\Str::limit($item->track->name, 14) }}</span>
            @endif
        </div>
    </div>

    {{-- event footer --}}
    <div class="op-card-foot">
        <x-icon name="calendar" class="h-3 w-3 shrink-0 text-gold-600" />
        <span class="truncate text-3xs font-semibold text-ink">{{ \Illuminate\Support\Str::limit($event->name, 22) }}</span>
        <span class="ml-auto shrink-0 text-3xs font-bold uppercase tracking-wide text-muted">{{ $item->moduleLabel() ?? $item->stageLabel() }}</span>
    </div>
</div>
