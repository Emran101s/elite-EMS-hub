@php
    $prog = $detail->progress();
    [$cd, $ct] = $detail->checklistProgress();
    $checklist = $detail->checklist ?? [];
    $lbl = 'text-eyebrow font-bold uppercase tracking-[0.14em] text-muted';
@endphp

{{--
    THE TASK, OPENED.

    Centred rather than slid in from the edge: editing one task is a moment of
    focus, and a panel pinned to the right competes with the board it is
    covering — you end up reading a form through a gap. In the middle, the
    board dims away and the task gets the room to be laid out properly instead
    of stacked into a 440px ribbon.
--}}
<div class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto p-4 sm:p-8"
     wire:key="tdrawer-{{ $detail->id }}"
     x-data x-on:keydown.escape.window="$wire.closeTask()">

    <div class="fixed inset-0 bg-navy-900/50 backdrop-blur-[2px]" wire:click="closeTask"></div>

    <div class="relative my-auto w-full max-w-[720px] overflow-hidden rounded-lg bg-white shadow-overlay">

        {{-- ── head: what it is, and where it stands ── --}}
        <div class="relative border-b border-line bg-page px-7 pt-6">
            <div class="flex items-start gap-4">
                <span class="mt-1 grid h-10 w-10 shrink-0 place-items-center rounded-lg text-white"
                      style="background: {{ $detail->stageHex() }}">
                    <x-icon name="clipboard" class="h-4 w-4" />
                </span>

                <div class="min-w-0 flex-1">
                    <p class="flex items-center gap-2 {{ $lbl }} !text-gold-700">
                        Task
                        <span class="font-mono text-muted">TSK-{{ str_pad((string) $detail->id, 3, '0', STR_PAD_LEFT) }}</span>
                    </p>
                    <input type="text" value="{{ $detail->title }}" @change="$wire.patch('title', $event.target.value)"
                           placeholder="Untitled task"
                           class="mt-1 w-full border-0 bg-transparent px-0 text-[26px] font-black leading-tight text-ink placeholder:text-line focus:outline-none focus:ring-0">
                </div>

                <button type="button" wire:click="closeTask"
                        class="-mr-2 -mt-1 shrink-0 rounded-lg p-2 text-muted transition hover:bg-white hover:text-ink">✕</button>
            </div>

            {{-- the stage, as one row of pills — the primary act on a task --}}
            <div class="mt-4 flex flex-wrap gap-1.5 pb-4">
                @foreach (\App\Models\Task::STAGES as $sv => [$sl, $sh])
                    <button type="button" wire:click="moveTask({{ $detail->id }}, '{{ $sv }}')"
                            class="flex items-center gap-1.5 rounded-full px-3 py-1.5 text-eyebrow font-bold transition {{ $detail->status === $sv ? 'text-white shadow-sm' : 'bg-white text-muted ring-1 ring-line hover:text-ink' }}"
                            style="{{ $detail->status === $sv ? 'background:'.$sh : '' }}">
                        <span class="h-1.5 w-1.5 rounded-full" style="background: {{ $detail->status === $sv ? '#ffffff' : $sh }}"></span>{{ $sl }}
                    </button>
                @endforeach
            </div>

            {{-- progress rides the bottom edge, where a header meets its body --}}
            <div class="absolute inset-x-0 bottom-0 h-1 bg-page">
                <div class="h-full transition-all" style="width: {{ $prog }}%; background: {{ $detail->stageHex() }}"></div>
            </div>
        </div>

        <div class="max-h-[min(70vh,44rem)] overflow-y-auto px-7 py-6">

            {{-- ── the facts, four across: nothing wraps, nothing stacks ── --}}
            <div class="grid gap-x-5 gap-y-4 sm:grid-cols-2">
                <div>
                    <p class="mb-1.5 {{ $lbl }}">Module</p>
                    <select @change="$wire.patch('area', $event.target.value)" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none">
                        <option value="">No module</option>
                        @foreach (\App\Models\Task::MODULES as $slug => [$mlabel, $mhex])
                            <option value="{{ $slug }}" @selected($detail->area === $slug)>{{ $mlabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <p class="mb-1.5 {{ $lbl }}">Priority</p>
                    <div class="flex gap-1">
                        @foreach (\App\Models\Task::PRIORITIES as $pv => [$pl, $ph])
                            <button type="button" wire:click="patch('priority', '{{ $pv }}')"
                                    class="h-10 flex-1 rounded-lg border text-eyebrow font-bold transition {{ $detail->priority === $pv ? 'text-white' : 'border-line bg-white text-muted hover:border-navy-300' }}"
                                    style="{{ $detail->priority === $pv ? 'background:'.$ph.';border-color:'.$ph : '' }}">{{ $pl }}</button>
                        @endforeach
                    </div>
                </div>

                <div>
                    <p class="mb-1.5 {{ $lbl }}">Owner</p>
                    <select @change="$wire.patch('assignee_id', $event.target.value)" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none">
                        <option value="">Unassigned</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" @selected($detail->assignee_id === $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <p class="mb-1.5 {{ $lbl }}">Start</p>
                        <input type="date" value="{{ $detail->start_on?->format('Y-m-d') }}"
                               @change="$wire.patch('start_on', $event.target.value)" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none">
                    </div>
                    <div>
                        <p class="mb-1.5 {{ $lbl }}">Due</p>
                        <input type="date" value="{{ $detail->due_on?->format('Y-m-d') }}"
                               @change="$wire.patch('due_on', $event.target.value)"
                               class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none {{ $detail->isOverdue() ? '!text-danger-ink !font-bold' : '' }}">
                    </div>
                </div>
            </div>

            <div class="mt-6 border-t border-line pt-5">
                <p class="mb-1.5 {{ $lbl }}">Description</p>
                <textarea @change="$wire.patch('description', $event.target.value)" rows="3" class="w-full rounded-lg border border-line bg-white px-2.5 py-2 text-sm text-ink focus:border-navy-300 focus:outline-none"
                          placeholder="What does this task involve?">{{ $detail->description }}</textarea>
            </div>

            {{-- ── checklist ── --}}
            <div class="mt-6 border-t border-line pt-5">
                <div class="mb-2.5 flex items-baseline justify-between">
                    <p class="{{ $lbl }}">Checklist</p>
                    @if ($ct)
                        <span class="text-eyebrow font-bold text-muted">{{ $cd }} of {{ $ct }} done</span>
                    @endif
                </div>

                <div class="space-y-1">
                    @forelse ($checklist as $idx => $ci)
                        <div wire:key="chk-{{ $detail->id }}-{{ $idx }}"
                             class="group flex items-center gap-2.5 rounded-lg px-2 py-1.5 transition hover:bg-page">
                            <button type="button" wire:click="toggleCheckItem({{ $idx }})"
                                    class="flex h-4.5 w-4.5 shrink-0 items-center justify-center rounded-md border text-eyebrow text-white transition {{ ($ci['done'] ?? false) ? 'border-success bg-success' : 'border-line hover:border-success' }}">{{ ($ci['done'] ?? false) ? '✓' : '' }}</button>
                            <span class="min-w-0 flex-1 text-sm {{ ($ci['done'] ?? false) ? 'text-muted line-through' : 'text-ink' }}">{{ $ci['text'] ?? '' }}</span>
                            <button type="button" wire:click="removeCheckItem({{ $idx }})"
                                    class="shrink-0 text-muted opacity-100 transition hover:text-danger-ink sm:opacity-0 sm:group-hover:opacity-100">✕</button>
                        </div>
                    @empty
                        <p class="px-2 text-sm italic text-muted">Nothing on the checklist yet.</p>
                    @endforelse
                </div>

                <div class="mt-2.5 flex items-center gap-2">
                    <input type="text" wire:model="checkText" wire:keydown.enter="addCheckItem"
                           placeholder="Add a checklist item…" class="h-10 flex-1 rounded-lg border border-line bg-white px-2.5 text-sm text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none">
                    <button type="button" wire:click="addCheckItem" class="rounded-full border border-line bg-white px-3 py-2 text-[12px] font-bold text-ink transition hover:border-navy-300">＋ Add</button>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2 border-t border-line bg-page px-7 py-4">
            <x-confirm title="Delete “{{ $detail->title }}”?"
                    confirm="Delete"
                    run="$wire.deleteTask({{ $detail->id }})"
                    class="rounded-lg px-3 py-2 text-xs font-bold text-danger-ink transition hover:bg-danger-soft">Delete</x-confirm>
            <span class="ms-auto text-eyebrow text-muted">Every change saves as you make it</span>
            <button type="button" wire:click="closeTask" class="h-10 rounded-full bg-navy-900 px-6 text-sm font-bold text-white transition hover:bg-navy-800">Done</button>
        </div>
    </div>
</div>
