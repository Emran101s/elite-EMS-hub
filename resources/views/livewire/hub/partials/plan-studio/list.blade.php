@php
    $ini = fn ($n) => \Illuminate\Support\Str::of($n)->explode(' ')->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    $cols = 'grid-cols-[1fr_120px_92px_84px_84px_128px]';
@endphp
<div class="overflow-hidden rounded-2xl border border-line bg-white shadow-sm">
    <div class="overflow-x-auto">
        {{-- The list owns which row is expanded, the same way <x-accordion>
             does: one `at`, so opening a row's sub-items closes the last one
             and the table never grows into a wall of nested rows. --}}
        <div x-data="{ at: null }" class="min-w-[820px]">
            <div class="grid {{ $cols }} gap-2 border-b border-line bg-navy-50/50 px-4 py-2 text-eyebrow font-bold uppercase tracking-wide text-navy-400">
                <span>Item</span><span>Status</span><span>Owners</span><span>Start</span><span>Due</span><span>Progress</span>
            </div>

            @foreach ($tracks as $t)
                @php $rows = $byTrack[$t->id] ?? collect(); @endphp
                @continue($rows->isEmpty())
                <div class="flex items-center gap-2 border-b border-line/70 bg-navy-50/30 px-4 py-1.5">
                    <span class="h-2.5 w-2.5 rounded-[3px]" style="background: {{ $t->color }}"></span>
                    <span class="text-eyebrow font-bold uppercase tracking-wide text-navy-600">{{ $t->name }}</span>
                    <span class="text-eyebrow font-semibold text-navy-300">{{ $rows->count() }}</span>
                    @if ($t->goal)<span class="truncate text-eyebrow italic text-navy-300">— {{ $t->goal }}</span>@endif
                    <button type="button" wire:click="addItem({{ $t->id }})" class="ml-auto shrink-0 text-eyebrow font-bold text-navy-300 transition hover:text-gold-600">＋ Item</button>
                </div>

                @foreach ($rows as $item)
                    @php $prog = $item->progress(); [$sd, $st] = $item->subtaskProgress(); $overdue = $item->isOverdue(); @endphp
                    <div wire:key="lir-{{ $item->id }}" class="border-b border-line/50">
                        <div class="grid {{ $cols }} items-center gap-2 px-4 py-2 transition hover:bg-navy-50/30">
                            <div class="flex min-w-0 items-center gap-2">
                                @if ($item->subtasks->count())
                                    <button type="button" x-on:click="at = (at === {{ $item->id }} ? null : {{ $item->id }})"
                                            x-bind:aria-expanded="at === {{ $item->id }} ? 'true' : 'false'"
                                            class="text-navy-300 transition hover:text-navy-700"><svg class="h-3 w-3 transition-transform duration-300" x-bind:class="at === {{ $item->id }} && 'rotate-90'" viewBox="0 0 20 20" fill="currentColor"><path d="M7 5l6 5-6 5V5z"/></svg></button>
                                @else<span class="w-3"></span>@endif
                                <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $item->priorityHex() }}" title="{{ $item->priorityLabel() }}"></span>
                                <button type="button" wire:click="openItem({{ $item->id }})" class="min-w-0 text-left text-xs font-semibold text-navy-800"><x-record-title :record="$item" fallback="Untitled item" :muted="in_array($item->status, ['done', 'cancelled'])" /></button>
                                @if ($item->isSigned())<span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-gold-300 to-gold-500 text-eyebrow font-black text-navy-900" title="Approved">✓</span>@endif
                                <span class="ml-auto">@include('livewire.hub.partials.plan-studio.actions', ['item' => $item])</span>
                            </div>
                            <span class="inline-flex w-fit items-center gap-1 rounded-md bg-white px-2 py-0.5 text-eyebrow font-bold" style="color: {{ $item->statusHex() }}; box-shadow: inset 0 0 0 1px {{ $item->statusHex() }}33"><span class="h-1.5 w-1.5 rounded-full" style="background: {{ $item->statusHex() }}"></span>{{ $item->statusLabel() }}</span>
                            <span class="flex -space-x-1.5">
                                @foreach ($item->owners->take(3) as $o)<span class="flex h-5 w-5 items-center justify-center rounded-full bg-gradient-to-br from-navy-800 to-navy-950 text-eyebrow font-bold text-gold-300 ring-2 ring-white" title="{{ $o->name }}">{{ $ini($o->name) }}</span>@endforeach
                                @if ($item->owners->isEmpty())<span class="text-eyebrow italic text-navy-300">—</span>@endif
                            </span>
                            <span class="text-eyebrow font-medium text-navy-500">{{ $item->start_on?->format('j M') ?? '—' }}</span>
                            <span class="text-eyebrow font-medium {{ $overdue ? 'font-bold text-red-600' : 'text-navy-500' }}">{{ $item->due_on?->format('j M') ?? '—' }}</span>
                            <div class="flex items-center gap-1.5"><div class="h-1.5 flex-1 overflow-hidden rounded-full bg-navy-50"><div class="h-full rounded-full" style="width: {{ max($prog, $prog > 0 ? 4 : 0) }}%; background: {{ $item->statusHex() }}"></div></div><span class="text-eyebrow font-black text-navy-400">{{ $prog }}%</span></div>
                        </div>

                        <div x-show="at === {{ $item->id }}" x-collapse.duration.300ms x-cloak
                             class="space-y-1 border-t border-line/50 bg-page/40 px-4 py-2 pl-11">
                            @foreach ($item->subtasks as $sub)
                                <div class="flex items-center gap-2">
                                    <button type="button" wire:click="toggleSubtask({{ $sub->id }})" class="flex h-4 w-4 shrink-0 items-center justify-center rounded border text-eyebrow text-white transition {{ $sub->is_done ? 'border-emerald-500 bg-emerald-500' : 'border-navy-300 hover:border-emerald-400' }}">{{ $sub->is_done ? '✓' : '' }}</button>
                                    <span class="flex-1 truncate text-micro {{ $sub->is_done ? 'text-navy-300 line-through' : 'text-navy-600' }}">{{ $sub->title }}</span>
                                    @if ($sub->owner)<span class="text-eyebrow font-semibold text-navy-400">{{ \Illuminate\Support\Str::before($sub->owner->name, ' ') }}</span>@endif
                                    @if ($sub->due_on)<span class="text-eyebrow {{ $sub->isOverdue() ? 'font-bold text-red-600' : 'text-navy-400' }}">{{ $sub->due_on->format('j M') }}</span>@endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endforeach

            @if ($items->isEmpty())
                <div class="px-6 py-12 text-center text-xs text-muted">No items match — <button type="button" wire:click="addItem" class="font-semibold text-gold-600 hover:underline">add one</button>.</div>
            @endif
        </div>
    </div>
</div>
