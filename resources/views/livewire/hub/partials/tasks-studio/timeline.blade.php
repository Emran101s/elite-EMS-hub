<div class="eo-soft-card overflow-hidden">
    <div class="overflow-x-auto">
        <div class="min-w-[840px]">
            <div class="flex border-b border-eo-line bg-white">
                <div class="w-[210px] shrink-0 px-4 py-2.5 text-eyebrow font-bold uppercase tracking-wide text-eo-muted">Task</div>
                <div class="relative h-9 flex-1">
                    @foreach ($axis['ticks'] as $t)
                        <span class="pointer-events-none absolute inset-y-0 w-px bg-eo-line/50" style="left: {{ $t['left'] }}%"></span>
                        <span class="absolute top-2.5 -translate-x-1/2 text-center text-eyebrow font-bold uppercase tracking-wide text-eo-muted" style="left: {{ min($t['left'] + 5, 97) }}%">{{ $t['label'] }} <span class="font-medium text-eo-muted/70">{{ $t['sub'] }}</span></span>
                    @endforeach
                    @if ($axis['todayIn'])<span class="absolute top-1 z-20 -translate-x-1/2 rounded-full bg-eo-navy px-2 py-0.5 text-eyebrow font-bold tracking-wide text-white shadow" style="left: {{ $axis['todayLeft'] }}%">TODAY</span>@endif
                </div>
            </div>

            <div class="relative">
                @if ($axis['todayIn'])<span class="pointer-events-none absolute inset-y-0 z-[6] w-[2px] bg-eo-teal/60" style="left: calc(210px + (100% - 210px) * {{ $axis['todayLeft'] }} / 100)"></span>@endif

                @forelse ($byModule as $slug => $rows)
                    @php [$mlabel, $mhex] = \App\Models\Task::MODULES[$slug] ?? [ucfirst($slug), 'var(--color-neutral)']; @endphp
                    <div class="flex items-center gap-2 border-b border-eo-line/70 bg-eo-workspace/60 px-3 py-1">
                        <span class="h-2.5 w-2.5 rounded-[3px]" style="background: {{ $mhex }}"></span>
                        <span class="text-eyebrow font-bold uppercase tracking-wide text-eo-text">{{ $mlabel }}</span>
                    </div>

                    @foreach ($rows as $item)
                        @php $g = $geo[$item->id] ?? null; $prog = $item->progress(); $hex = $item->stageHex(); @endphp
                        <div class="group flex items-stretch border-b border-eo-line/50 transition hover:bg-eo-workspace/60">
                            <div class="flex w-[210px] shrink-0 items-center gap-2 px-3 py-1.5">
                                <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $item->priorityHex() }}"></span>
                                <button type="button" wire:click="openTask({{ $item->id }})" class="min-w-0 text-left text-xs font-semibold text-eo-text"><x-record-title :record="$item" fallback="Untitled task" :muted="in_array($item->status, ['done', 'cancelled'])" /></button>
                                @if ($item->isSigned())<span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-eo-gold-ink text-eyebrow font-black text-white">✓</span>@endif
                                <span class="ml-auto">@include('livewire.hub.partials.tasks-studio.actions', ['item' => $item])</span>
                            </div>
                            <div class="relative min-w-0 flex-1" style="min-height: 38px">
                                @foreach ($axis['ticks'] as $t)<span class="pointer-events-none absolute inset-y-0 w-px bg-eo-line/30" style="left: {{ $t['left'] }}%"></span>@endforeach
                                @if ($g)
                                    <button type="button" wire:click="openTask({{ $item->id }})"
                                            class="absolute top-1/2 flex h-[24px] -translate-y-1/2 items-center gap-1.5 overflow-hidden rounded-lg pl-2 pr-1.5 text-left transition hover:brightness-95"
                                            style="left: {{ $g['left'] }}%; width: {{ $g['width'] }}%; min-width: 92px; max-width: 70%; background: {{ $hex }}1F; box-shadow: inset 0 0 0 1px {{ $hex }}55"
                                            title="{{ $item->start_on?->format('j M') }} – {{ $item->due_on?->format('j M') }}">
                                        <span class="pointer-events-none absolute inset-y-0 left-0 z-0 rounded-l-lg" style="width: {{ $prog }}%; background: {{ $hex }}33"></span>
                                        <span class="pointer-events-none absolute inset-y-0 left-0 z-[1] w-1" style="background: {{ $hex }}"></span>
                                        <span class="relative z-[2] truncate text-eyebrow font-bold text-eo-text">{{ $prog }}%</span>
                                        <span class="relative z-[2] ml-auto shrink-0 rounded bg-white px-1.5 text-eyebrow font-bold" style="color: {{ $hex }}">{{ $item->stageLabel() }}</span>
                                    </button>
                                @else
                                    <button type="button" wire:click="openTask({{ $item->id }})" class="absolute left-2 top-1/2 -translate-y-1/2 text-eyebrow italic text-eo-muted hover:text-eo-teal-ink">set dates…</button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @empty
                    <x-empty icon="chart" title="No tasks to place yet" class="!rounded-none !shadow-none" />
                @endforelse

                @if ($unmoduled->isNotEmpty())
                    <div class="flex items-center gap-2 border-b border-eo-line/70 bg-eo-workspace/60 px-3 py-1"><span class="h-2.5 w-2.5 rounded-[3px] bg-eo-muted"></span><span class="text-eyebrow font-bold uppercase tracking-wide text-eo-muted">No module</span></div>
                    @foreach ($unmoduled as $item)
                        @php $g = $geo[$item->id] ?? null; $prog = $item->progress(); $hex = $item->stageHex(); @endphp
                        <div class="flex items-stretch border-b border-eo-line/50">
                            <div class="flex w-[210px] shrink-0 items-center gap-2 px-3 py-1.5"><span class="h-2 w-2 rounded-full" style="background: {{ $item->priorityHex() }}"></span><button type="button" wire:click="openTask({{ $item->id }})" class="truncate text-left text-xs font-semibold text-eo-text">{{ $item->title ?: 'Untitled task' }}</button></div>
                            <div class="relative min-w-0 flex-1" style="min-height: 38px">
                                @if ($g)<button type="button" wire:click="openTask({{ $item->id }})" class="absolute top-1/2 flex h-[24px] -translate-y-1/2 items-center gap-1.5 overflow-hidden rounded-lg px-2 text-left" style="left: {{ $g['left'] }}%; width: {{ $g['width'] }}%; min-width: 92px; background: {{ $hex }}1F; box-shadow: inset 0 0 0 1px {{ $hex }}55"><span class="text-eyebrow font-bold text-eo-text">{{ $prog }}%</span></button>@else<button type="button" wire:click="openTask({{ $item->id }})" class="absolute left-2 top-1/2 -translate-y-1/2 text-eyebrow italic text-eo-muted">set dates…</button>@endif
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>
