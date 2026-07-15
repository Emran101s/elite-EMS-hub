@php
    $depth = $depth ?? 0;
    $item = $node['item'];
    $geo = $node['geo'];
    $done = $item->status === 'done';
    $isTop = $depth === 0;
    $startISO = ($item->starts_on ?? $item->due_on)?->format('Y-m-d');
    $endISO = ($item->due_on ?? $item->starts_on)?->format('Y-m-d');
    $owners = $item->owners;
    $ini = fn ($name) => \Illuminate\Support\Str::of($name)->explode(' ')->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    [$statusLabel, $statusHex] = \App\Models\EventPlanItem::STATUS_BAR[$item->status] ?? \App\Models\EventPlanItem::STATUS_BAR['todo'];
    $isExpanded = in_array($item->id, $expanded ?? [], true);
    $overdue = $item->due_on && ! $done && $item->due_on->isPast();
@endphp

<div wire:key="row-{{ $item->id }}" class="group flex items-stretch border-b border-line/50 transition-colors duration-150 hover:bg-gold-50/40 {{ $depth > 0 ? 'bg-page/30' : '' }}">
    {{-- Task --}}
    <div class="flex w-[320px] shrink-0 items-center gap-2 py-2 pr-2" style="padding-left: {{ 14 + $depth * 18 }}px">
        <button type="button" wire:click="toggleExpand({{ $item->id }})" title="Show / add subtasks"
                class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md text-navy-300 transition hover:bg-navy-50 hover:text-navy-700">
            <svg class="h-3.5 w-3.5 transition-transform duration-200 {{ $isExpanded ? 'rotate-90' : '' }}" viewBox="0 0 20 20" fill="currentColor"><path d="M7 5l6 5-6 5V5z" /></svg>
        </button>
        <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $statusHex }}"></span>
        <button type="button" wire:click="editItem({{ $item->id }})"
                class="flex-1 truncate text-left {{ $isTop ? 'text-[0.86rem] font-bold text-navy-900' : ($depth === 1 ? 'text-[0.79rem] font-semibold text-navy-700' : 'text-[0.74rem] text-navy-600') }} {{ $done ? '!font-normal !text-navy-300 line-through' : '' }}">{{ $item->title }}</button>
        @if ($node['childTotal'])
            <button type="button" wire:click="toggleExpand({{ $item->id }})" class="shrink-0 rounded-full bg-navy-50 px-1.5 py-0.5 text-[0.5rem] font-bold text-navy-500 transition hover:bg-navy-100">{{ $node['childDone'] }}/{{ $node['childTotal'] }}</button>
        @endif
        @if ($owners->isNotEmpty())
            <span class="flex shrink-0 -space-x-1.5" title="{{ $owners->pluck('name')->join(', ') }}">
                @foreach ($owners->take(3) as $o)
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-gradient-to-br from-navy-800 to-navy-950 text-[0.45rem] font-bold text-gold-300 ring-2 ring-white">{{ $ini($o->name) }}</span>
                @endforeach
                @if ($owners->count() > 3)
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-navy-100 text-[0.45rem] font-bold text-navy-600 ring-2 ring-white">+{{ $owners->count() - 3 }}</span>
                @endif
            </span>
        @endif
    </div>

    {{-- Timeline --}}
    <div class="relative min-w-0 flex-1 {{ $isTop ? 'min-h-[40px]' : 'min-h-[34px]' }}" data-plan-track data-span-days="{{ $roadmap['spanDays'] }}">
        @foreach ($roadmap['ticks'] as $tick)
            <span class="absolute inset-y-0 w-px bg-line/30" style="left: {{ $tick['left'] }}%"></span>
        @endforeach
        @if ($roadmap['todayIn'])<span class="absolute inset-y-0 z-[1] w-px bg-navy-900/35" style="left: {{ $roadmap['todayLeft'] }}%"></span>@endif
        @if ($roadmap['eventLeft'] !== null)<span class="absolute inset-y-0 z-[1] w-0.5 bg-gold-500/60" style="left: {{ $roadmap['eventLeft'] }}%"></span>@endif

        {{-- the empty track the bar sits on --}}
        <span class="absolute inset-x-2 top-1/2 h-2.5 -translate-y-1/2 rounded-full bg-navy-50/80"></span>

        @if ($geo)
            @if ($geo['ms'])
                <div wire:key="bar-{{ $item->id }}" class="plan-bar plan-ms absolute top-1/2 z-[2] -translate-y-1/2 -translate-x-1/2 cursor-grab touch-none select-none"
                     data-task-id="{{ $item->id }}" data-start="{{ $startISO }}" data-end="{{ $endISO }}" data-milestone="1"
                     title="{{ $item->title }} · {{ $item->due_on?->format('M j') }} — drag to move, click to edit"
                     style="left: {{ $geo['left'] }}%">
                    <span class="block {{ $isTop ? 'h-3.5 w-3.5' : 'h-3 w-3' }} rotate-45 rounded-[3px] ring-2 ring-white" style="background: {{ $geo['hex'] }}"></span>
                </div>
            @else
                <div wire:key="bar-{{ $item->id }}" class="plan-bar absolute top-1/2 z-[2] flex -translate-y-1/2 cursor-grab touch-none select-none items-center rounded-full {{ $isTop ? 'h-[13px]' : 'h-[10px]' }}"
                     data-task-id="{{ $item->id }}" data-start="{{ $startISO }}" data-end="{{ $endISO }}" data-milestone="0"
                     title="{{ $item->title }} — drag to move, drag edges to resize, click to edit"
                     style="left: {{ $geo['left'] }}%; width: {{ $geo['width'] }}%; min-width: 18px; background: {{ $geo['hex'] }}">
                    <span data-resize="left" class="absolute inset-y-0 left-0 z-[3] w-2 cursor-ew-resize rounded-l-full"></span>
                    <span data-resize="right" class="absolute inset-y-0 right-0 z-[3] w-2 cursor-ew-resize rounded-r-full"></span>
                </div>
            @endif
        @endif
    </div>

    {{-- Start · Due --}}
    <div class="flex w-[66px] shrink-0 items-center justify-center text-[0.64rem] font-medium text-navy-500">{{ $item->starts_on?->format('j M') ?? '—' }}</div>
    <div class="flex w-[66px] shrink-0 items-center justify-center text-[0.64rem] font-medium {{ $overdue ? 'text-risk font-bold' : 'text-navy-500' }}">{{ $item->due_on?->format('j M') ?? '—' }}</div>

    {{-- Status --}}
    <div class="flex w-[92px] shrink-0 items-center justify-center px-1.5">
        <span class="rounded-full px-2 py-0.5 text-[0.5rem] font-bold uppercase tracking-wide text-white" style="background: {{ $statusHex }}">{{ $statusLabel }}</span>
    </div>
</div>

{{-- Children — recursive, revealed on expand --}}
@if ($isExpanded)
    @foreach ($node['children'] as $child)
        @include('livewire.hub.partials.plan-row', ['node' => $child, 'depth' => $depth + 1, 'users' => $users, 'roadmap' => $roadmap])
    @endforeach

    <div class="flex items-stretch border-b border-line/50 bg-page/30">
        <div class="w-[320px] shrink-0 py-1.5" style="padding-left: {{ 34 + $depth * 18 }}px">
            <button type="button" wire:click="newSubItem({{ $item->id }})" class="inline-flex items-center gap-1 rounded-full border border-dashed border-line px-2.5 py-1 text-[0.58rem] font-semibold text-navy-400 transition hover:border-gold-400 hover:bg-gold-50/50 hover:text-gold-600">＋ Add {{ $depth === 0 ? 'task' : 'subtask' }}</button>
        </div>
        <div class="flex-1"></div>
    </div>
@endif
