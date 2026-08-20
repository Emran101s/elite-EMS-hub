@php
    $prog = $item->progress();
    [$sd, $st] = $item->subtaskProgress();
    $overdue = $item->isOverdue();
    $closed = in_array($item->status, \App\Models\PlanItem::CLOSED, true);
    $ini = fn ($n) => \Illuminate\Support\Str::of($n)->explode(' ')->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
@endphp
<div data-card data-item-id="{{ $item->id }}" wire:key="card-{{ $item->id }}"
     class="group cursor-grab rounded-xl border border-eo-line bg-white p-3 shadow-sm transition hover:border-eo-teal hover:shadow-eo">
    <div class="flex items-start gap-2">
        <span class="mt-1 h-2 w-2 shrink-0 rounded-full" style="background: {{ $item->priorityHex() }}" title="{{ $item->priorityLabel() }} priority"></span>
        <button type="button" wire:click="openItem({{ $item->id }})" data-block-drag
                class="min-w-0 flex-1 text-left text-sm font-bold leading-snug text-eo-text"><x-record-title :record="$item" fallback="Untitled item" :muted="$closed" /></button>
        @if ($item->isSigned())
            <span title="Approved by {{ $item->approver?->name ?? 'team' }} · {{ $item->approved_at?->format('j M Y') }}"
                  class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-eo-gold-ink text-eyebrow font-black text-white shadow ring-2 ring-white">✓</span>
        @endif
        @include('livewire.hub.partials.plan-studio.actions', ['item' => $item])
    </div>

    <div class="mt-2 flex flex-wrap items-center gap-1.5">
        @if ($item->track)
            <span class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-eyebrow font-bold" style="background: {{ $item->track->color }}1A; color: {{ $item->track->color }}"><span class="h-1.5 w-1.5 rounded-full" style="background: {{ $item->track->color }}"></span>{{ \Illuminate\Support\Str::limit($item->track->name, 18) }}</span>
        @endif
        @if ($showStatus ?? false)
            <span class="inline-flex items-center gap-1 rounded-md bg-white px-1.5 py-0.5 text-eyebrow font-bold" style="color: {{ $item->statusHex() }}; box-shadow: inset 0 0 0 1px {{ $item->statusHex() }}33"><span class="h-1.5 w-1.5 rounded-full" style="background: {{ $item->statusHex() }}"></span>{{ $item->statusLabel() }}</span>
        @endif
    </div>

    <div class="mt-2.5 flex items-center gap-2">
        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-eo-bg"><div class="h-full rounded-full transition-all" style="width: {{ max($prog, $prog > 0 ? 4 : 0) }}%; background: {{ $item->statusHex() }}"></div></div>
        <span class="text-eyebrow font-black text-eo-muted">{{ $prog }}%</span>
    </div>

    <div class="mt-2.5 flex items-center gap-2">
        @if ($item->owners->isNotEmpty())
            <span class="flex -space-x-1.5">
                @foreach ($item->owners->take(3) as $o)
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-eo-navy text-eyebrow font-bold text-white ring-2 ring-white" title="{{ $o->name }}">{{ $ini($o->name) }}</span>
                @endforeach
                @if ($item->owners->count() > 3)<span class="flex h-5 w-5 items-center justify-center rounded-full bg-eo-bg text-eyebrow font-bold text-eo-muted ring-2 ring-white">+{{ $item->owners->count() - 3 }}</span>@endif
            </span>
        @endif
        @if ($st)
            <span class="flex items-center gap-1 text-eyebrow font-bold text-eo-muted" title="{{ $sd }} of {{ $st }} subtasks done"><x-icon name="clipboard" class="h-3 w-3" />{{ $sd }}/{{ $st }}</span>
        @endif
        @if ($item->due_on)
            <span class="ml-auto inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-eyebrow font-bold {{ $overdue ? 'bg-eo-risk-soft text-eo-risk-ink' : 'bg-eo-bg text-eo-muted' }}"><x-icon name="calendar" class="h-3 w-3" />{{ $item->due_on->format('j M') }}</span>
        @endif
    </div>
</div>
