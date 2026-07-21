@php
    $prog = $item->progress();
    [$cd, $ct] = $item->checklistProgress();
    $overdue = $item->isOverdue();
    $closed = in_array($item->status, ['done', 'cancelled'], true);
    $ini = fn ($n) => \Illuminate\Support\Str::of($n)->explode(' ')->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
@endphp
<div data-card data-item-id="{{ $item->id }}" wire:key="tcard-{{ $item->id }}"
     class="group cursor-grab rounded-xl border border-line bg-white p-3 shadow-sm transition hover:border-gold-300 hover:shadow-[0_10px_30px_-14px_rgba(11,31,58,0.4)]">
    <div class="flex items-start gap-2">
        <span class="mt-1 h-2 w-2 shrink-0 rounded-full" style="background: {{ $item->priorityHex() }}" title="{{ $item->priorityLabel() }} priority"></span>
        <button type="button" wire:click="openTask({{ $item->id }})" data-block-drag
                class="flex-1 text-left text-xs font-bold leading-snug text-navy-900 {{ $closed ? 'text-navy-400 line-through' : '' }}">{{ $item->title ?: 'Untitled task' }}</button>
        @if ($item->isSigned())
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-gold-300 to-gold-500 text-eyebrow font-black text-navy-900 shadow ring-2 ring-white" title="{{ $item->stageLabel() }}">✓</span>
        @endif
        @include('livewire.hub.partials.tasks-studio.actions', ['item' => $item])
    </div>

    <div class="mt-2 flex flex-wrap items-center gap-1.5">
        @if ($item->area)
            <span class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-eyebrow font-bold" style="background: {{ $item->moduleHex() }}1A; color: {{ $item->moduleHex() }}"><span class="h-1.5 w-1.5 rounded-full" style="background: {{ $item->moduleHex() }}"></span>{{ $item->moduleLabel() }}</span>
        @endif
        @if ($showStatus ?? false)
            <span class="inline-flex items-center gap-1 rounded-md bg-white px-1.5 py-0.5 text-eyebrow font-bold" style="color: {{ $item->stageHex() }}; box-shadow: inset 0 0 0 1px {{ $item->stageHex() }}33"><span class="h-1.5 w-1.5 rounded-full" style="background: {{ $item->stageHex() }}"></span>{{ $item->stageLabel() }}</span>
        @endif
    </div>

    <div class="mt-2.5 flex items-center gap-2">
        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-navy-50"><div class="h-full rounded-full transition-all" style="width: {{ max($prog, $prog > 0 ? 4 : 0) }}%; background: {{ $item->stageHex() }}"></div></div>
        <span class="text-eyebrow font-black text-navy-400">{{ $prog }}%</span>
    </div>

    <div class="mt-2.5 flex items-center gap-2">
        @if ($item->assignee)
            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-gradient-to-br from-navy-800 to-navy-950 text-eyebrow font-bold text-gold-300 ring-2 ring-white" title="{{ $item->assignee->name }}">{{ $ini($item->assignee->name) }}</span>
        @endif
        @if ($ct)
            <span class="flex items-center gap-1 text-eyebrow font-bold text-navy-400" title="{{ $cd }} of {{ $ct }} checklist"><x-icon name="clipboard" class="h-3 w-3" />{{ $cd }}/{{ $ct }}</span>
        @endif
        @if ($item->due_on)
            <span class="ml-auto inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-eyebrow font-bold {{ $overdue ? 'bg-red-50 text-red-600' : 'bg-navy-50 text-navy-500' }}"><x-icon name="calendar" class="h-3 w-3" />{{ $item->due_on->format('d M') }}</span>
        @endif
    </div>
</div>
