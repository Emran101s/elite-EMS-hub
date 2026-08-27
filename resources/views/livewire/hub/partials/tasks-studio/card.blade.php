@php
    $prog = $item->progress();
    [$cd, $ct] = $item->checklistProgress();
    $overdue = $item->isOverdue();
    $closed = in_array($item->status, ['done', 'cancelled'], true);
    $ini = fn ($n) => \Illuminate\Support\Str::of($n)->explode(' ')->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    $ref = 'TSK-'.str_pad((string) $item->id, 3, '0', STR_PAD_LEFT);

    // Start rarely changes the call a kanban card exists to support, so it
    // only earns a place on the card when it's actually set — folded into
    // the due line as a range rather than a whole second labelled row.
    $dateLine = match (true) {
        $item->due_on && $item->start_on => $item->start_on->format('j M').' – '.$item->due_on->format('j M Y'),
        (bool) $item->due_on => $item->due_on->format('j M Y'),
        (bool) $item->start_on => 'From '.$item->start_on->format('j M Y'),
        default => null,
    };
@endphp
<div data-card data-item-id="{{ $item->id }}" wire:key="tcard-{{ $item->id }}" class="group op-card cursor-grab active:cursor-grabbing">
    <div class="flex flex-1 flex-col p-3.5">
        {{-- ref · menu --}}
        <div class="flex items-center justify-between gap-2">
            <span class="rounded-md bg-page px-1.5 py-0.5 font-mono text-3xs font-bold text-muted">{{ $ref }}</span>
            @include('livewire.hub.partials.tasks-studio.actions', ['item' => $item])
        </div>

        {{-- title --}}
        <button type="button" wire:click="openTask({{ $item->id }})" data-block-drag
                class="mt-2 block w-full text-left text-sm font-bold leading-snug text-ink"><x-record-title :record="$item" fallback="Untitled task" :muted="$closed" /></button>

        @if (trim((string) $item->description) !== '')
            <p class="mt-1 line-clamp-2 text-2xs leading-relaxed text-muted">{{ $item->description }}</p>
        @endif

        {{-- badges: module · priority · signed --}}
        <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
            @if ($item->area)
                <span class="pill" style="background: {{ $item->moduleHex() }}1A; color: {{ $item->moduleHex() }}"><span class="h-1.5 w-1.5 rounded-full" style="background: {{ $item->moduleHex() }}"></span>{{ $item->moduleLabel() }}</span>
            @endif
            <span class="pill" style="background: {{ $item->priorityHex() }}1A; color: {{ $item->priorityHex() }}"><span class="h-1.5 w-1.5 rounded-full" style="background: {{ $item->priorityHex() }}"></span>{{ $item->priorityLabel() }}</span>
            @if ($item->isSigned())
                <span class="pill ml-auto bg-gold-50 text-gold-700">✓ {{ $item->stageLabel() }}</span>
            @endif
        </div>

        {{-- when · who — one row each, not a labelled 2×2 grid --}}
        <div class="mt-3 flex items-center justify-between gap-2 border-t border-dashed border-line pt-2.5">
            <span class="flex min-w-0 items-center gap-1.5 text-2xs font-bold {{ $overdue ? 'text-danger-ink' : 'text-muted' }}">
                <x-icon name="calendar" class="h-3.5 w-3.5 shrink-0 opacity-70" />
                <span class="truncate">{{ $dateLine ?? 'No date set' }}</span>
            </span>

            @if ($item->assignee)
                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-navy-900 text-3xs font-bold text-gold-400 ring-2 ring-white" title="{{ $item->assignee->name }}">{{ $ini($item->assignee->name) }}</span>
            @else
                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-dashed border-line text-muted" title="Unassigned">
                    <x-icon name="users" class="h-2.5 w-2.5 opacity-50" />
                </span>
            @endif
        </div>

        {{-- progress --}}
        <div class="mt-2.5 flex items-center gap-2">
            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-page"><div class="h-full rounded-full transition-all" style="width: {{ max($prog, $prog > 0 ? 4 : 0) }}%; background: {{ $item->stageHex() }}"></div></div>
            <span class="text-eyebrow font-black text-muted">{{ $prog }}%</span>
        </div>

        {{-- checklist · track — only when either exists --}}
        @if ($ct || $item->track)
            <div class="mt-2 flex items-center gap-3 text-eyebrow font-bold text-muted">
                @if ($ct)
                    <span class="flex items-center gap-1" title="{{ $cd }} of {{ $ct }} checklist done"><x-icon name="clipboard" class="h-3 w-3" />{{ $cd }}/{{ $ct }}</span>
                @endif
                @if ($item->track)
                    <span class="flex items-center gap-1" title="Plan track"><span class="h-1.5 w-1.5 rounded-full" style="background: {{ $item->track->color }}"></span>{{ \Illuminate\Support\Str::limit($item->track->name, 16) }}</span>
                @endif
            </div>
        @endif
    </div>
</div>
