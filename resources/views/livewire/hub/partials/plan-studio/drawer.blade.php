@php
    $prog = $selected->progress();
    [$sd, $st] = $selected->subtaskProgress();
    $ini = fn ($n) => \Illuminate\Support\Str::of($n)->explode(' ')->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
@endphp
<div class="fixed inset-0 z-40 flex justify-end" wire:key="drawer-{{ $selected->id }}">
    <div class="absolute inset-0 bg-navy-950/40" wire:click="closeDrawer"></div>
    <aside class="relative flex h-full w-full max-w-[440px] flex-col bg-white shadow-[0_0_80px_-10px_rgba(11,31,58,0.6)]">

        {{-- header --}}
        <div class="relative shrink-0 border-b border-line bg-page/60 px-5 pb-4 pt-4 text-navy-900">
            <div class="relative flex items-start justify-between gap-2">
                <div class="min-w-0 flex-1">
                    <p class="text-eyebrow font-bold uppercase tracking-[0.24em] text-gold-600">Deliverable</p>
                    <input type="text" wire:model.blur="title" placeholder="Untitled item"
                           class="pf mt-1 w-full border-0 border-b border-line bg-transparent px-0 pb-1 text-lg font-bold text-navy-900 placeholder:text-navy-300 focus:border-gold-400 focus:outline-none focus:ring-0">
                    @error('title')<p class="mt-1 text-eyebrow text-risk">{{ $message }}</p>@enderror
                </div>
                <button type="button" wire:click="closeDrawer" class="-mr-1 -mt-1 shrink-0 rounded-lg p-1 text-navy-400 transition hover:bg-navy-50 hover:text-navy-900">✕</button>
            </div>

            {{-- status gates --}}
            <div class="relative mt-3 flex flex-wrap gap-1.5">
                @foreach (\App\Models\PlanItem::STATUSES as $sv => [$sl, $sh])
                    <button type="button" wire:click="setStatus({{ $selected->id }}, '{{ $sv }}')"
                            class="flex items-center gap-1.5 rounded-full px-2.5 py-1 text-eyebrow font-bold transition {{ $selected->status === $sv ? 'text-white shadow' : 'text-navy-500 ring-1 ring-line hover:text-navy-900' }}"
                            style="{{ $selected->status === $sv ? 'background:'.$sh : '' }}"><span class="h-1.5 w-1.5 rounded-full" style="background: {{ $selected->status === $sv ? '#ffffff' : $sh }}"></span>{{ $sl }}</button>
                @endforeach
            </div>

            {{-- approval seal --}}
            @if ($selected->isSigned())
                <div class="relative mt-3 flex items-center gap-2.5 rounded-xl bg-gold-50 px-3 py-2 ring-1 ring-gold-200">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-gold-300 to-gold-500 text-sm font-black text-navy-900 shadow ring-2 ring-white">✓</span>
                    <div class="min-w-0">
                        <p class="text-eyebrow font-black uppercase tracking-wider text-gold-700">Signed off · Approved</p>
                        <p class="truncate text-micro text-navy-600">{{ $selected->approver?->name ?? 'Team' }} · {{ $selected->approved_at?->format('j M Y') }}</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- body --}}
        <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
            {{-- progress --}}
            <div>
                <div class="mb-1.5 flex items-center justify-between text-eyebrow font-bold uppercase tracking-[0.14em] text-navy-400"><span>Progress</span><span class="text-navy-900">{{ $prog }}%</span></div>
                <div class="h-2 overflow-hidden rounded-full bg-navy-50"><div class="h-full rounded-full transition-all" style="width: {{ $prog }}%; background: {{ $selected->statusHex() }}"></div></div>
                <div class="mt-1.5 flex items-center gap-2 text-eyebrow text-navy-400">
                    <span>{{ $st ? "Auto from $sd/$st subtasks" : 'Set manually' }}</span>
                    <span class="ml-auto flex items-center gap-1">Override
                        <input type="number" min="0" max="100" wire:model.blur="progress_override" placeholder="—" class="input h-6 w-14 px-1.5 text-center text-micro">%</span>
                </div>
            </div>

            {{-- meta grid --}}
            <div class="grid grid-cols-2 gap-3 border-t border-line pt-3.5">
                <div>
                    <p class="mb-1 text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-400">Track</p>
                    <select wire:model.live="formTrackId" class="input h-9 text-micro"><option value="">No track</option>@foreach ($tracks as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select>
                </div>
                <div>
                    <p class="mb-1 text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-400">Priority</p>
                    <div class="flex gap-1">@foreach (\App\Models\PlanItem::PRIORITIES as $pv => [$pl, $ph])<button type="button" wire:click="$set('priority', '{{ $pv }}')" class="flex-1 rounded-lg border px-1 py-1.5 text-eyebrow font-bold transition {{ $priority === $pv ? 'text-white' : 'border-line bg-white text-navy-500 hover:border-navy-200' }}" style="{{ $priority === $pv ? 'background:'.$ph.';border-color:'.$ph : '' }}">{{ $pl }}</button>@endforeach</div>
                </div>
                <div>
                    <p class="mb-1 text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-400">Start</p>
                    <input type="date" wire:model.live="start_on" class="input h-9 text-micro">
                </div>
                <div>
                    <p class="mb-1 text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-400">Due</p>
                    <input type="date" wire:model.live="due_on" class="input h-9 text-micro">
                    @error('due_on')<p class="mt-0.5 text-eyebrow text-risk">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- owners --}}
            <div class="border-t border-line pt-3.5">
                <p class="mb-1.5 text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-400">Owners @if (count($owner_ids))<span class="text-navy-300">· {{ count($owner_ids) }}</span>@endif</p>
                <div class="flex flex-wrap gap-1">
                    @foreach ($users as $u)@php $on = in_array($u->id, $owner_ids); @endphp
                        <button type="button" wire:click="toggleOwner({{ $u->id }})" class="flex h-7 items-center gap-1.5 rounded-full border pl-0.5 pr-2 text-eyebrow font-semibold transition {{ $on ? 'border-gold-400 bg-gold-50 text-navy-900' : 'border-line bg-white text-navy-400 hover:border-navy-200' }}">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full text-eyebrow font-bold {{ $on ? 'bg-gradient-to-br from-navy-800 to-navy-950 text-gold-300' : 'bg-navy-50 text-navy-400' }}">{{ $ini($u->name) }}</span>{{ \Illuminate\Support\Str::before($u->name, ' ') }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- description --}}
            <div class="border-t border-line pt-3.5">
                <p class="mb-1 text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-400">Description</p>
                <textarea wire:model.blur="description" rows="3" class="input text-xs" placeholder="What does this deliverable involve?"></textarea>
            </div>

            {{-- subtasks --}}
            <div class="border-t border-line pt-3.5">
                <div class="mb-2 flex items-center justify-between">
                    <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-400">Subtasks @if ($st)<span class="text-navy-300">· {{ $sd }}/{{ $st }}</span>@endif</p>
                </div>
                <div class="space-y-1">
                    @forelse ($selected->subtasks as $sub)
                        <div wire:key="sub-{{ $sub->id }}" class="group flex items-center gap-2 rounded-lg px-1 py-1 transition hover:bg-page/60">
                            <button type="button" wire:click="toggleSubtask({{ $sub->id }})" class="flex h-4 w-4 shrink-0 items-center justify-center rounded border text-eyebrow text-white transition {{ $sub->is_done ? 'border-emerald-500 bg-emerald-500' : 'border-navy-300 hover:border-emerald-400' }}">{{ $sub->is_done ? '✓' : '' }}</button>
                            <input type="text" value="{{ $sub->title }}" @change="$wire.updateSubtask({{ $sub->id }}, 'title', $event.target.value)" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-xs {{ $sub->is_done ? 'text-navy-300 line-through' : 'text-navy-700' }} focus:outline-none focus:ring-0">
                            <select @change="$wire.updateSubtask({{ $sub->id }}, 'owner_id', $event.target.value)" class="h-6 shrink-0 rounded border border-line bg-white px-1 text-eyebrow text-navy-500 focus:outline-none">
                                <option value="">—</option>
                                @foreach ($users as $u)<option value="{{ $u->id }}" @selected($sub->owner_id === $u->id)>{{ \Illuminate\Support\Str::before($u->name, ' ') }}</option>@endforeach
                            </select>
                            <input type="date" value="{{ $sub->due_on?->format('Y-m-d') }}" @change="$wire.updateSubtask({{ $sub->id }}, 'due_on', $event.target.value)" class="h-6 w-[104px] shrink-0 rounded border {{ $sub->isOverdue() ? 'border-red-300 text-red-600' : 'border-line text-navy-500' }} bg-white px-1 text-eyebrow focus:outline-none">
                            <button type="button" wire:click="deleteSubtask({{ $sub->id }})" class="shrink-0 text-navy-300 opacity-0 transition hover:text-risk group-hover:opacity-100">✕</button>
                        </div>
                    @empty
                        <p class="text-micro italic text-navy-300">No subtasks yet — add the first below.</p>
                    @endforelse
                </div>
                <div class="mt-2 flex items-center gap-1.5">
                    <input type="text" wire:model="newSubtask" wire:keydown.enter="addSubtask({{ $selected->id }})" placeholder="Add a subtask…" class="input h-8 flex-1 text-micro">
                    <button type="button" wire:click="addSubtask({{ $selected->id }})" class="rounded-lg bg-navy-50 px-2.5 py-1.5 text-eyebrow font-bold text-navy-700 transition hover:bg-navy-100">＋ Add</button>
                </div>
            </div>
        </div>

        {{-- footer --}}
        <div class="flex shrink-0 items-center gap-2 border-t border-line bg-page/40 px-5 py-3">
            <x-confirm title="Delete “{{ $selected->title }}”?"
                       body="Its subtasks go with it."
                       confirm="Delete"
                       run="$wire.deleteItem({{ $selected->id }})"
                       class="rounded-lg px-2.5 py-1.5 text-micro font-bold text-risk transition hover:bg-risk/10">Delete</x-confirm>
            <span class="ml-auto flex items-center gap-1.5 text-eyebrow font-semibold text-navy-400">
                <span wire:loading class="flex items-center gap-1 text-gold-600"><span class="h-1.5 w-1.5 animate-pulse rounded-full bg-gold-500"></span>Saving…</span>
                <span wire:loading.remove class="flex items-center gap-1 text-emerald-600"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Saved</span>
            </span>
            <button type="button" wire:click="closeDrawer" class="btn-navy h-8 px-4 text-micro">Done</button>
        </div>
    </aside>
</div>
