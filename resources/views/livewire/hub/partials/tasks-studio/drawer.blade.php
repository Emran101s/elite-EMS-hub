@php
    $prog = $detail->progress();
    [$cd, $ct] = $detail->checklistProgress();
    $checklist = $detail->checklist ?? [];
    $ini = fn ($n) => \Illuminate\Support\Str::of($n)->explode(' ')->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
@endphp
<div class="fixed inset-0 z-40 flex justify-end" wire:key="tdrawer-{{ $detail->id }}">
    <div class="absolute inset-0 bg-navy-950/40" wire:click="closeTask"></div>
    <aside class="relative flex h-full w-full max-w-[440px] flex-col bg-white shadow-[0_0_80px_-10px_rgba(11,31,58,0.6)]">

        <div class="relative shrink-0 overflow-hidden bg-gradient-to-br from-navy-900 to-[var(--color-navy-950)] px-5 pb-4 pt-4 text-white">
            <div class="pointer-events-none absolute -right-10 -top-12 h-36 w-36 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.3),transparent_70%)]"></div>
            <div class="relative flex items-start justify-between gap-2">
                <div class="min-w-0 flex-1">
                    <p class="text-eyebrow font-bold uppercase tracking-[0.24em] text-gold-400">Task</p>
                    <input type="text" value="{{ $detail->title }}" @change="$wire.patch('title', $event.target.value)" placeholder="Untitled task"
                           class="mt-1 w-full border-0 border-b border-white/15 bg-transparent px-0 pb-1 text-lg font-black text-white placeholder:text-white/25 focus:border-gold-400 focus:outline-none focus:ring-0" style="font-family:'Spectral',Georgia,serif">
                </div>
                <button type="button" wire:click="closeTask" class="-mr-1 -mt-1 shrink-0 text-white/40 transition hover:text-white">✕</button>
            </div>
            <div class="relative mt-3 flex flex-wrap gap-1.5">
                @foreach (\App\Models\Task::STAGES as $sv => [$sl, $sh])
                    <button type="button" wire:click="moveTask({{ $detail->id }}, '{{ $sv }}')"
                            class="flex items-center gap-1.5 rounded-full px-2.5 py-1 text-eyebrow font-bold transition {{ $detail->status === $sv ? 'text-white shadow' : 'text-white/45 ring-1 ring-white/15 hover:text-white/80' }}"
                            style="{{ $detail->status === $sv ? 'background:'.$sh : '' }}"><span class="h-1.5 w-1.5 rounded-full" style="background: {{ $detail->status === $sv ? 'var(--chrome-ink)' : $sh }}"></span>{{ $sl }}</button>
                @endforeach
            </div>
        </div>

        <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
            <div>
                <div class="mb-1.5 flex items-center justify-between text-eyebrow font-bold uppercase tracking-[0.14em] text-navy-400"><span>Progress</span><span class="text-navy-900">{{ $prog }}%</span></div>
                <div class="h-2 overflow-hidden rounded-full bg-navy-50"><div class="h-full rounded-full transition-all" style="width: {{ $prog }}%; background: {{ $detail->stageHex() }}"></div></div>
            </div>

            {{-- module + phase --}}
            <div class="grid grid-cols-2 gap-3 border-t border-line pt-3.5">
                <div>
                    <p class="mb-1 text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-400">Module</p>
                    <select @change="$wire.patch('area', $event.target.value)" class="input h-9 text-micro">
                        <option value="">No module</option>
                        @foreach (\App\Models\Task::MODULES as $slug => [$mlabel, $mhex])<option value="{{ $slug }}" @selected($detail->area === $slug)>{{ $mlabel }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <p class="mb-1 text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-400">Priority</p>
                    <div class="flex gap-1">@foreach (\App\Models\Task::PRIORITIES as $pv => [$pl, $ph])<button type="button" wire:click="patch('priority', '{{ $pv }}')" class="flex-1 rounded-lg border px-1 py-1.5 text-eyebrow font-bold transition {{ $detail->priority === $pv ? 'text-white' : 'border-line bg-white text-navy-500 hover:border-navy-200' }}" style="{{ $detail->priority === $pv ? 'background:'.$ph.';border-color:'.$ph : '' }}">{{ $pl }}</button>@endforeach</div>
                </div>
                <div>
                    <p class="mb-1 text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-400">Owner</p>
                    <select @change="$wire.patch('assignee_id', $event.target.value)" class="input h-9 text-micro">
                        <option value="">Unassigned</option>
                        @foreach ($users as $u)<option value="{{ $u->id }}" @selected($detail->assignee_id === $u->id)>{{ $u->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <p class="mb-1 text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-400">Start</p>
                    <input type="date" value="{{ $detail->start_on?->format('Y-m-d') }}" @change="$wire.patch('start_on', $event.target.value)" class="input h-9 text-micro">
                </div>
                <div>
                    <p class="mb-1 text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-400">Due</p>
                    <input type="date" value="{{ $detail->due_on?->format('Y-m-d') }}" @change="$wire.patch('due_on', $event.target.value)" class="input h-9 text-micro">
                </div>
            </div>

            <div class="border-t border-line pt-3.5">
                <p class="mb-1 text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-400">Description</p>
                <textarea @change="$wire.patch('description', $event.target.value)" rows="3" class="input text-xs" placeholder="What does this task involve?">{{ $detail->description }}</textarea>
            </div>

            {{-- checklist --}}
            <div class="border-t border-line pt-3.5">
                <p class="mb-2 text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-400">Checklist @if ($ct)<span class="text-navy-300">· {{ $cd }}/{{ $ct }}</span>@endif</p>
                <div class="space-y-1">
                    @forelse ($checklist as $idx => $ci)
                        <div wire:key="chk-{{ $detail->id }}-{{ $idx }}" class="group flex items-center gap-2 rounded-lg px-1 py-1 transition hover:bg-page/60">
                            <button type="button" wire:click="toggleCheckItem({{ $idx }})" class="flex h-4 w-4 shrink-0 items-center justify-center rounded border text-eyebrow text-white transition {{ ($ci['done'] ?? false) ? 'border-emerald-500 bg-emerald-500' : 'border-navy-300 hover:border-emerald-400' }}">{{ ($ci['done'] ?? false) ? '✓' : '' }}</button>
                            <span class="min-w-0 flex-1 truncate text-xs {{ ($ci['done'] ?? false) ? 'text-navy-300 line-through' : 'text-navy-700' }}">{{ $ci['text'] ?? '' }}</span>
                            <button type="button" wire:click="removeCheckItem({{ $idx }})" class="shrink-0 text-navy-300 opacity-0 transition hover:text-risk group-hover:opacity-100">✕</button>
                        </div>
                    @empty
                        <p class="text-micro italic text-navy-300">No checklist items yet.</p>
                    @endforelse
                </div>
                <div class="mt-2 flex items-center gap-1.5">
                    <input type="text" wire:model="checkText" wire:keydown.enter="addCheckItem" placeholder="Add a checklist item…" class="input h-8 flex-1 text-micro">
                    <button type="button" wire:click="addCheckItem" class="rounded-lg bg-navy-50 px-2.5 py-1.5 text-eyebrow font-bold text-navy-700 transition hover:bg-navy-100">＋ Add</button>
                </div>
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-2 border-t border-line bg-page/40 px-5 py-3">
            <button type="button" wire:click="deleteTask({{ $detail->id }})" wire:confirm="Delete “{{ $detail->title }}”?" class="rounded-lg px-2.5 py-1.5 text-micro font-bold text-risk transition hover:bg-risk/10">Delete</button>
            <button type="button" wire:click="closeTask" class="btn-navy ml-auto h-8 px-4 text-micro">Done</button>
        </div>
    </aside>
</div>
