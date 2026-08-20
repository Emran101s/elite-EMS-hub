@php
    $prog = $selected->progress();
    [$sd, $st] = $selected->subtaskProgress();
    $ini = fn ($n) => \Illuminate\Support\Str::of($n)->explode(' ')->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
@endphp
<div class="fixed inset-0 z-40 flex justify-end" wire:key="drawer-{{ $selected->id }}">
    <div class="absolute inset-0 bg-eo-navy-deep/40" wire:click="closeDrawer"></div>
    <aside class="relative flex h-full w-full max-w-[440px] flex-col bg-white shadow-eo-float">

        {{-- header --}}
        <div class="relative shrink-0 border-b border-eo-line bg-eo-workspace/60 px-5 pb-4 pt-4 text-eo-text">
            <div class="relative flex items-start justify-between gap-2">
                <div class="min-w-0 flex-1">
                    <p class="eo-label !text-eo-teal-ink">Deliverable</p>
                    <input type="text" wire:model.blur="title" placeholder="Untitled item"
                           class="mt-1 w-full border-0 border-b border-eo-line bg-transparent px-0 pb-1 text-lg font-bold text-eo-text placeholder:text-eo-muted focus:border-eo-teal focus:outline-none focus:ring-0">
                    @error('title')<p class="mt-1 text-eyebrow text-eo-risk-ink">{{ $message }}</p>@enderror
                </div>
                <button type="button" wire:click="closeDrawer" class="-mr-1 -mt-1 shrink-0 rounded-lg p-1 text-eo-muted transition hover:bg-eo-bg hover:text-eo-text">✕</button>
            </div>

            {{-- status gates --}}
            <div class="relative mt-3 flex flex-wrap gap-1.5">
                @foreach (\App\Models\PlanItem::STATUSES as $sv => [$sl, $sh])
                    <button type="button" wire:click="setStatus({{ $selected->id }}, '{{ $sv }}')"
                            class="flex items-center gap-1.5 rounded-full px-2.5 py-1 text-eyebrow font-bold transition {{ $selected->status === $sv ? 'text-white shadow' : 'text-eo-muted ring-1 ring-eo-line hover:text-eo-text' }}"
                            style="{{ $selected->status === $sv ? 'background:'.$sh : '' }}"><span class="h-1.5 w-1.5 rounded-full" style="background: {{ $selected->status === $sv ? '#ffffff' : $sh }}"></span>{{ $sl }}</button>
                @endforeach
            </div>

            {{-- approval seal --}}
            @if ($selected->isSigned())
                <div class="relative mt-3 flex items-center gap-2.5 rounded-xl bg-eo-gold/10 px-3 py-2 ring-1 ring-eo-gold/30">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-eo-gold-ink text-sm font-black text-white shadow ring-2 ring-white">✓</span>
                    <div class="min-w-0">
                        <p class="text-eyebrow font-black uppercase tracking-wider text-eo-gold-ink">Signed off · Approved</p>
                        <p class="truncate text-micro text-eo-text">{{ $selected->approver?->name ?? 'Team' }} · {{ $selected->approved_at?->format('j M Y') }}</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- body --}}
        <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
            {{-- progress --}}
            <div>
                <div class="mb-1.5 flex items-center justify-between text-eyebrow font-bold uppercase tracking-[0.14em] text-eo-muted"><span>Progress</span><span class="text-eo-text">{{ $prog }}%</span></div>
                <div class="h-2 overflow-hidden rounded-full bg-eo-bg"><div class="h-full rounded-full transition-all" style="width: {{ $prog }}%; background: {{ $selected->statusHex() }}"></div></div>
                <div class="mt-1.5 flex items-center gap-2 text-eyebrow text-eo-muted">
                    <span>{{ $st ? "Auto from $sd/$st subtasks" : 'Set manually' }}</span>
                    <span class="ml-auto flex items-center gap-1">Override
                        <input type="number" min="0" max="100" wire:model.blur="progress_override" placeholder="—" class="eo-input h-6 w-14 px-1.5 text-center text-micro">%</span>
                </div>
            </div>

            {{-- meta grid --}}
            <div class="grid grid-cols-2 gap-3 border-t border-eo-line pt-3.5">
                <div>
                    <p class="mb-1 text-eyebrow font-bold uppercase tracking-[0.12em] text-eo-muted">Track</p>
                    <select wire:model.live="formTrackId" class="eo-select h-9 text-micro"><option value="">No track</option>@foreach ($tracks as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select>
                </div>
                <div>
                    <p class="mb-1 text-eyebrow font-bold uppercase tracking-[0.12em] text-eo-muted">Priority</p>
                    <div class="flex gap-1">@foreach (\App\Models\PlanItem::PRIORITIES as $pv => [$pl, $ph])<button type="button" wire:click="$set('priority', '{{ $pv }}')" class="flex-1 rounded-lg border px-1 py-1.5 text-eyebrow font-bold transition {{ $priority === $pv ? 'text-white' : 'border-eo-line bg-white text-eo-muted hover:border-eo-teal' }}" style="{{ $priority === $pv ? 'background:'.$ph.';border-color:'.$ph : '' }}">{{ $pl }}</button>@endforeach</div>
                </div>
                <div>
                    <p class="mb-1 text-eyebrow font-bold uppercase tracking-[0.12em] text-eo-muted">Start</p>
                    <input type="date" wire:model.live="start_on" class="eo-input h-9 text-micro">
                </div>
                <div>
                    <p class="mb-1 text-eyebrow font-bold uppercase tracking-[0.12em] text-eo-muted">Due</p>
                    <input type="date" wire:model.live="due_on" class="eo-input h-9 text-micro">
                    @error('due_on')<p class="mt-0.5 text-eyebrow text-eo-risk-ink">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- owners --}}
            <div class="border-t border-eo-line pt-3.5">
                <p class="mb-1.5 text-eyebrow font-bold uppercase tracking-[0.12em] text-eo-muted">Owners @if (count($owner_ids))<span class="text-eo-muted">· {{ count($owner_ids) }}</span>@endif</p>
                <div class="flex flex-wrap gap-1">
                    @foreach ($users as $u)@php $on = in_array($u->id, $owner_ids); @endphp
                        <button type="button" wire:click="toggleOwner({{ $u->id }})" class="flex h-7 items-center gap-1.5 rounded-full border pl-0.5 pr-2 text-eyebrow font-semibold transition {{ $on ? 'border-eo-teal bg-eo-teal-soft text-eo-text' : 'border-eo-line bg-white text-eo-muted hover:border-eo-teal' }}">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full text-eyebrow font-bold {{ $on ? 'bg-eo-navy text-white' : 'bg-eo-bg text-eo-muted' }}">{{ $ini($u->name) }}</span>{{ \Illuminate\Support\Str::before($u->name, ' ') }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- description --}}
            <div class="border-t border-eo-line pt-3.5">
                <p class="mb-1 text-eyebrow font-bold uppercase tracking-[0.12em] text-eo-muted">Description</p>
                <textarea wire:model.blur="description" rows="3" class="eo-textarea text-xs" placeholder="What does this deliverable involve?"></textarea>
            </div>

            {{-- subtasks --}}
            <div class="border-t border-eo-line pt-3.5">
                <div class="mb-2 flex items-center justify-between">
                    <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-eo-muted">Subtasks @if ($st)<span class="text-eo-muted">· {{ $sd }}/{{ $st }}</span>@endif</p>
                </div>
                <div class="space-y-1">
                    @forelse ($selected->subtasks as $sub)
                        <div wire:key="sub-{{ $sub->id }}" class="group flex items-center gap-2 rounded-lg px-1 py-1 transition hover:bg-eo-workspace/60">
                            <button type="button" wire:click="toggleSubtask({{ $sub->id }})" class="flex h-4 w-4 shrink-0 items-center justify-center rounded border text-eyebrow text-white transition {{ $sub->is_done ? 'border-eo-ok bg-eo-ok' : 'border-eo-line hover:border-eo-ok' }}">{{ $sub->is_done ? '✓' : '' }}</button>
                            <input type="text" value="{{ $sub->title }}" @change="$wire.updateSubtask({{ $sub->id }}, 'title', $event.target.value)" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-xs {{ $sub->is_done ? 'text-eo-muted line-through' : 'text-eo-text' }} focus:outline-none focus:ring-0">
                            <select @change="$wire.updateSubtask({{ $sub->id }}, 'owner_id', $event.target.value)" class="h-6 shrink-0 rounded border border-eo-line bg-white px-1 text-eyebrow text-eo-muted focus:outline-none">
                                <option value="">—</option>
                                @foreach ($users as $u)<option value="{{ $u->id }}" @selected($sub->owner_id === $u->id)>{{ \Illuminate\Support\Str::before($u->name, ' ') }}</option>@endforeach
                            </select>
                            <input type="date" value="{{ $sub->due_on?->format('Y-m-d') }}" @change="$wire.updateSubtask({{ $sub->id }}, 'due_on', $event.target.value)" class="h-6 w-[104px] shrink-0 rounded border {{ $sub->isOverdue() ? 'border-eo-risk text-eo-risk-ink' : 'border-eo-line text-eo-muted' }} bg-white px-1 text-eyebrow focus:outline-none">
                            <button type="button" wire:click="deleteSubtask({{ $sub->id }})" class="shrink-0 text-eo-muted opacity-100 transition hover:text-eo-risk sm:opacity-0 sm:group-hover:opacity-100">✕</button>
                        </div>
                    @empty
                        <p class="text-micro italic text-eo-muted">No subtasks yet — add the first below.</p>
                    @endforelse
                </div>
                <div class="mt-2 flex items-center gap-1.5">
                    <input type="text" wire:model="newSubtask" wire:keydown.enter="addSubtask({{ $selected->id }})" placeholder="Add a subtask…" class="eo-input h-8 flex-1 text-micro">
                    <x-eo.button variant="secondary" wire:click="addSubtask({{ $selected->id }})" class="h-8 px-2.5 text-eyebrow">＋ Add</x-eo.button>
                </div>
            </div>
        </div>

        {{-- footer --}}
        <div class="flex shrink-0 items-center gap-2 border-t border-eo-line bg-eo-workspace/40 px-5 py-3">
            <x-confirm title="Delete “{{ $selected->title }}”?"
                       body="Its subtasks go with it."
                       confirm="Delete"
                       run="$wire.deleteItem({{ $selected->id }})"
                       class="rounded-lg px-2.5 py-1.5 text-micro font-bold text-eo-risk-ink transition hover:bg-eo-risk-soft">Delete</x-confirm>
            <span class="ml-auto flex items-center gap-1.5 text-eyebrow font-semibold text-eo-muted">
                <span wire:loading class="flex items-center gap-1 text-eo-teal-ink"><span class="h-1.5 w-1.5 animate-pulse rounded-full bg-eo-teal"></span>Saving…</span>
                <span wire:loading.remove class="flex items-center gap-1 text-eo-ok-ink"><span class="h-1.5 w-1.5 rounded-full bg-eo-ok"></span>Saved</span>
            </span>
            <x-eo.button variant="navy" wire:click="closeDrawer" class="h-8 px-4 text-micro">Done</x-eo.button>
        </div>
    </aside>
</div>
