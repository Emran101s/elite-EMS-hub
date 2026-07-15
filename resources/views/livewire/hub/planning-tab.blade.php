<div>
    @php $rowH = 34; $gutter = 'w-[168px]'; @endphp

    {{-- ══ Header: countdown + stats ══ --}}
    <div class="mb-4 grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto]">
        <div class="relative overflow-hidden rounded-2xl border border-line bg-gradient-to-br from-navy-900 to-[#16294A] px-5 py-4 text-white">
            <div class="pointer-events-none absolute -right-6 -top-8 h-28 w-28 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.35),transparent_70%)]"></div>
            <div class="relative flex flex-wrap items-end gap-x-6 gap-y-2">
                <div>
                    <p class="text-[0.6rem] font-bold uppercase tracking-[0.2em] text-gold-300">Countdown to Event Day</p>
                    @if ($daysToEvent === null)
                        <p class="mt-1 text-lg font-bold">No event date set</p>
                    @elseif ($daysToEvent > 0)
                        <p class="mt-0.5 flex items-baseline gap-2"><span class="text-3xl font-black leading-none text-gold-400">{{ $daysToEvent }}</span><span class="text-sm font-semibold">days to go</span></p>
                    @elseif ($daysToEvent === 0)
                        <p class="mt-1 text-2xl font-black text-gold-400">It's Event Day 🎉</p>
                    @else
                        <p class="mt-1 text-lg font-bold">Event was {{ abs($daysToEvent) }} days ago</p>
                    @endif
                    @if ($eventDay)<p class="text-[0.68rem] text-white/60">{{ $eventDay->format('l, j F Y') }}</p>@endif
                </div>
                <div class="min-w-[160px] flex-1">
                    <div class="mb-1 flex items-center justify-between text-[0.68rem]">
                        <span class="text-white/70">{{ $stats['done'] }} / {{ $stats['total'] }} done</span>
                        <span class="font-bold text-gold-300">{{ $stats['progress'] }}%</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-white/15">
                        <div class="h-full rounded-full bg-gold-400 transition-all" style="width: {{ $stats['progress'] }}%"></div>
                    </div>
                    @if ($stats['overdue'] > 0)
                        <p class="mt-1.5 flex items-center gap-1 text-[0.66rem] font-semibold text-red-300">⚠ {{ $stats['overdue'] }} overdue {{ str('task')->plural($stats['overdue']) }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2">
            <a href="{{ route('events.planning.pdf', $event) }}" class="flex h-10 items-center gap-1.5 rounded-xl border border-line bg-white px-3.5 text-xs font-semibold text-navy-700 transition hover:border-gold-300"><x-icon name="chart" class="h-3.5 w-3.5" /> Export PDF</a>
            <button type="button" wire:click="newItem" class="btn-navy h-10 gap-1.5 px-4 text-xs"><span class="text-gold-400">＋</span> Add task</button>
        </div>
    </div>

    {{-- ══ Legend ══ --}}
    <div class="mb-2.5 flex flex-wrap items-center gap-x-4 gap-y-1.5 px-1 text-[0.66rem] text-navy-600">
        @foreach (\App\Models\EventPlanItem::STATUS_BAR as [$label, $hex])
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background: {{ $hex }}"></span>{{ $label }}</span>
        @endforeach
        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rotate-45 rounded-[2px] bg-navy-400"></span>Milestone</span>
        <span class="ml-auto hidden items-center gap-1.5 text-muted sm:flex">Drag a bar to reschedule · click a task to edit · ▸ expand for subtasks</span>
    </div>

    {{-- items-stretch (default) so the aside is as tall as the Gantt — sticky needs room to travel --}}
    <div class="flex gap-4">
    {{-- ══ Plan Gantt — phases + main tasks; subtasks reveal on expand ══ --}}
    <div class="min-w-0 flex-1 overflow-hidden rounded-2xl border border-line bg-white shadow-[0_10px_40px_-18px_rgba(11,31,58,0.35)]">
        <div class="overflow-x-auto">
            <div class="min-w-[900px]">
                {{-- column heads + month axis --}}
                {{-- offset clears the module rail above (which can wrap to two rows) --}}
                <div class="sticky top-[100px] z-10 flex items-stretch border-b border-line bg-white">
                    <div class="flex w-[320px] shrink-0 items-center px-4 py-3 text-[0.56rem] font-bold uppercase tracking-[0.16em] text-navy-400">Task</div>
                    <div class="relative h-11 min-w-0 flex-1">
                        @foreach ($roadmap['ticks'] as $t)
                            <span class="absolute top-2.5 -translate-x-1/2 text-center text-[0.6rem] font-bold uppercase tracking-wide text-navy-400" style="left: {{ $t['left'] }}%">{{ $t['label'] }}<span class="block text-[0.5rem] font-medium text-navy-300">{{ $t['sub'] }}</span></span>
                        @endforeach
                        @if ($roadmap['todayIn'])
                            <span class="absolute top-0.5 z-10 -translate-x-1/2 rounded-full bg-navy-900 px-2 py-0.5 text-[0.52rem] font-bold tracking-wide text-white shadow" style="left: {{ $roadmap['todayLeft'] }}%">TODAY</span>
                        @endif
                        @if ($roadmap['eventLeft'] !== null)
                            <span class="absolute top-0.5 z-10 -translate-x-1/2 rounded-full bg-gradient-to-r from-gold-400 to-gold-500 px-2 py-0.5 text-[0.52rem] font-black tracking-wide text-navy-900 shadow" style="left: {{ $roadmap['eventLeft'] }}%">★ EVENT</span>
                        @endif
                    </div>
                    <div class="flex w-[66px] shrink-0 items-center justify-center py-3 text-[0.56rem] font-bold uppercase tracking-wide text-navy-400">Start</div>
                    <div class="flex w-[66px] shrink-0 items-center justify-center py-3 text-[0.56rem] font-bold uppercase tracking-wide text-navy-400">Due</div>
                    <div class="flex w-[92px] shrink-0 items-center justify-center py-3 text-[0.56rem] font-bold uppercase tracking-wide text-navy-400">Status</div>
                </div>

                @forelse ($planTree as $group)
                    {{-- phase band — serif name under a gold rule --}}
                    <div wire:key="ph-{{ $group['phase']->id }}" class="group/ph flex items-center gap-2 border-b-2 border-gold-400 px-4 pb-1.5 pt-5">
                        @if ($editingCategoryId === $group['phase']->id)
                            <input type="text" wire:model="categoryEditName" wire:keydown.enter="saveCategoryName" wire:blur="saveCategoryName" autofocus class="input h-7 w-56 text-xs">
                        @else
                            <button type="button" wire:click="startRenameCategory({{ $group['phase']->id }})" class="text-[1rem] font-bold text-navy-900 transition hover:text-gold-700" style="font-family:'Playfair Display',Georgia,serif" title="Rename phase">{{ $group['phase']->name }}</button>
                            <span class="text-[0.62rem] font-medium text-navy-300">· {{ $group['total'] }} {{ \Illuminate\Support\Str::plural('task', $group['total']) }}</span>
                            <button type="button" wire:click="deleteCategory({{ $group['phase']->id }})" wire:confirm="Delete phase “{{ $group['phase']->name }}”? Its tasks move to another phase." class="text-[0.6rem] text-navy-300 opacity-0 transition hover:text-risk group-hover/ph:opacity-100">✕</button>
                            <div class="ml-auto flex items-center gap-2.5">
                                <div class="h-1.5 w-24 overflow-hidden rounded-full bg-navy-100"><div class="h-full rounded-full bg-gradient-to-r from-gold-400 to-gold-500 transition-all" style="width: {{ $group['pct'] }}%"></div></div>
                                <span class="text-[0.58rem] font-bold text-navy-400">{{ $group['done'] }}/{{ $group['total'] }}</span>
                                <button type="button" wire:click="newItem({{ $group['phase']->id }})" class="rounded-lg bg-white px-2 py-1 text-[0.58rem] font-bold text-navy-500 shadow-sm ring-1 ring-line transition hover:text-gold-600 hover:ring-gold-300">＋ Task</button>
                            </div>
                        @endif
                    </div>

                    @forelse ($group['tasks'] as $node)
                        @include('livewire.hub.partials.plan-row', ['node' => $node, 'depth' => 0, 'users' => $users, 'roadmap' => $roadmap])
                    @empty
                        <div class="border-b border-line px-4 py-3 text-[0.7rem] italic text-navy-300">No tasks in this phase yet — <button type="button" wire:click="newItem({{ $group['phase']->id }})" class="font-semibold not-italic text-gold-600 hover:underline">add the first one</button>.</div>
                    @endforelse
                @empty
                    <div class="px-6 py-12 text-center text-xs text-muted">No phases yet.</div>
                @endforelse

                {{-- add phase --}}
                <div class="flex items-center gap-2 bg-page/30 px-4 py-3">
                    <input type="text" wire:model="newCategoryName" wire:keydown.enter="addCategory" placeholder="New phase name…" class="input h-8 w-52 text-xs">
                    <button type="button" wire:click="addCategory" class="rounded-lg bg-navy-50 px-2.5 py-1.5 text-[0.62rem] font-bold text-navy-700 transition hover:bg-navy-100">＋ Add phase</button>
                    @error('newCategoryName')<span class="text-[0.62rem] text-risk">{{ $message }}</span>@enderror
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Task Inspector — docked, sticky, autosaving ══ --}}
    @if ($showForm)
        <aside class="w-[340px] shrink-0" wire:key="inspector">
            {{-- Docked below the module rail (which can wrap to two rows) so nothing ever slides over it. --}}
            <div class="sticky top-[112px] z-[9] flex max-h-[calc(100vh-8.5rem)] flex-col overflow-hidden rounded-2xl border border-line bg-white shadow-[0_24px_60px_-24px_rgba(11,31,58,0.5)]">

                {{-- header --}}
                <div class="relative shrink-0 overflow-hidden bg-gradient-to-br from-navy-900 to-[#071528] px-4 pb-4 pt-3 text-white">
                    <div class="pointer-events-none absolute -right-10 -top-12 h-32 w-32 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.3),transparent_70%)]"></div>
                    <div class="relative">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-[0.52rem] font-bold uppercase tracking-[0.24em] text-gold-400">
                                {{ $editingId ? ($formParentId ? 'Subtask' : 'Task') : 'New '.($formParentId ? 'subtask' : 'task') }}
                            </p>
                            <button type="button" wire:click="closePanel" class="-mr-1 -mt-1 text-white/40 transition hover:text-white">✕</button>
                        </div>

                        <p class="mt-1 truncate text-[0.6rem] text-white/45">
                            {{ optional($categories->firstWhere('id', (int) $formCategoryId))->name }}
                            @if ($selectedParent)<span class="text-gold-400/70"> › </span>{{ \Illuminate\Support\Str::limit($selectedParent->title, 24) }}@endif
                        </p>

                        <input type="text" wire:model.blur="title" placeholder="Untitled task"
                               class="mt-2 w-full border-0 border-b border-white/15 bg-transparent px-0 pb-1 text-lg font-bold text-white placeholder:text-white/25 focus:border-gold-400 focus:outline-none focus:ring-0"
                               style="font-family:'Playfair Display',Georgia,serif">
                        @error('title')<p class="mt-1 text-[0.62rem] text-red-300">{{ $message }}</p>@enderror

                        <div class="mt-3 flex flex-wrap items-center gap-1.5">
                            @if ($selectedKids->count())
                                @php $kd = $selectedKids->where('status', 'done')->count(); $kt = $selectedKids->count(); @endphp
                                <span class="rounded-full bg-white/10 px-2 py-0.5 text-[0.58rem] font-bold text-white/70">{{ $kd }}/{{ $kt }} subtasks · {{ (int) round($kd / $kt * 100) }}%</span>
                            @endif
                            @if ($starts_on && $due_on)
                                @php $dur = \Illuminate\Support\Carbon::parse($starts_on)->diffInDays(\Illuminate\Support\Carbon::parse($due_on)) + 1; @endphp
                                <span class="rounded-full bg-gold-400/15 px-2 py-0.5 text-[0.58rem] font-bold text-gold-300">{{ $dur }} {{ \Illuminate\Support\Str::plural('day', $dur) }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
                    {{-- status --}}
                    <div>
                        <p class="mb-1 text-[0.55rem] font-bold uppercase tracking-[0.14em] text-navy-400">Status</p>
                        <div class="grid grid-cols-2 gap-1.5">
                            @foreach (\App\Models\EventPlanItem::STATUS_BAR as $val => [$lbl, $hex])
                                <button type="button" wire:click="$set('status', '{{ $val }}')"
                                        class="flex items-center gap-1.5 rounded-lg border px-2 py-1.5 text-[0.66rem] font-bold transition {{ $status === $val ? 'text-white shadow-sm' : 'border-line bg-white text-navy-500 hover:border-navy-200' }}"
                                        style="{{ $status === $val ? 'background:'.$hex.'; border-color:'.$hex.';' : '' }}">
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full" style="background: {{ $status === $val ? '#fff' : $hex }}"></span>{{ $lbl }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- priority --}}
                    <div>
                        <p class="mb-1 text-[0.55rem] font-bold uppercase tracking-[0.14em] text-navy-400">Priority</p>
                        <div class="flex gap-1">
                            @foreach (\App\Models\EventPlanItem::PRIORITIES as $val => $lbl)
                                <button type="button" wire:click="$set('priority', '{{ $val }}')"
                                        class="flex-1 rounded-lg border px-1 py-1.5 text-[0.6rem] font-bold transition {{ $priority === $val ? 'border-navy-900 bg-navy-900 text-white shadow-sm' : 'border-line bg-white text-navy-500 hover:border-navy-200' }}">{{ $lbl }}</button>
                            @endforeach
                        </div>
                    </div>

                    {{-- owners (multi) --}}
                    <div>
                        <p class="mb-1 text-[0.55rem] font-bold uppercase tracking-[0.14em] text-navy-400">
                            Owners @if (count($owner_ids))<span class="text-navy-300">· {{ count($owner_ids) }}</span>@endif
                        </p>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($users as $u)
                                @php
                                    $on = in_array($u->id, $owner_ids);
                                    $ini = \Illuminate\Support\Str::of($u->name)->explode(' ')->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
                                @endphp
                                <button type="button" wire:click="toggleOwner({{ $u->id }})" title="{{ $u->name }}"
                                        class="flex h-7 items-center gap-1.5 rounded-full border pl-0.5 pr-2 text-[0.62rem] font-semibold transition {{ $on ? 'border-gold-400 bg-gold-50 text-navy-900' : 'border-line bg-white text-navy-400 hover:border-navy-200' }}">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[0.45rem] font-bold {{ $on ? 'bg-gradient-to-br from-navy-800 to-navy-950 text-gold-300' : 'bg-navy-50 text-navy-400' }}">{{ $ini }}</span>
                                    {{ \Illuminate\Support\Str::of($u->name)->explode(' ')->first() }}
                                </button>
                            @endforeach
                        </div>
                        @if (! count($owner_ids))
                            <p class="mt-1 text-[0.58rem] italic text-navy-300">Unassigned — click a person to add them.</p>
                        @endif
                    </div>

                    {{-- phase --}}
                    <div>
                        <p class="mb-1 text-[0.55rem] font-bold uppercase tracking-[0.14em] text-navy-400">Phase</p>
                        <select wire:model.live="formCategoryId" class="input h-9 text-[0.75rem]">
                            @foreach ($categories as $cat)<option value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach
                        </select>
                    </div>

                    {{-- dates --}}
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <p class="mb-1 text-[0.55rem] font-bold uppercase tracking-[0.14em] text-navy-400">Start</p>
                            <input type="date" wire:model.live="starts_on" class="input h-9 text-[0.72rem]">
                        </div>
                        <div>
                            <p class="mb-1 text-[0.55rem] font-bold uppercase tracking-[0.14em] text-navy-400">Due</p>
                            <input type="date" wire:model.live="due_on" class="input h-9 text-[0.72rem]">
                        </div>
                    </div>
                    @error('due_on')<p class="-mt-1 text-[0.62rem] text-risk">{{ $message }}</p>@enderror

                    {{-- notes --}}
                    <div>
                        <p class="mb-1 text-[0.55rem] font-bold uppercase tracking-[0.14em] text-navy-400">Notes</p>
                        <textarea wire:model.blur="notes" rows="2" class="input text-[0.78rem]" placeholder="Details…"></textarea>
                    </div>

                    {{-- subtasks --}}
                    @if ($editingId)
                        <div class="border-t border-line pt-3">
                            <div class="mb-1.5 flex items-center justify-between">
                                <p class="text-[0.55rem] font-bold uppercase tracking-[0.14em] text-navy-400">Subtasks @if ($selectedKids->count())<span class="text-navy-300">· {{ $selectedKids->count() }}</span>@endif</p>
                                <button type="button" wire:click="newSubItem({{ $editingId }})" class="text-[0.6rem] font-bold text-gold-600 transition hover:text-gold-700">＋ Add</button>
                            </div>
                            <div class="max-h-28 space-y-0.5 overflow-y-auto pr-1">
                                @forelse ($selectedKids as $kid)
                                    @php $done = $kid->status === 'done'; @endphp
                                    <div wire:key="ik-{{ $kid->id }}" class="flex items-center gap-2 rounded-lg px-1.5 py-1 transition hover:bg-page/60">
                                        <button type="button" wire:click="toggleDone({{ $kid->id }})"
                                                class="flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded border text-[0.5rem] text-white transition {{ $done ? 'border-emerald-500 bg-emerald-500' : 'border-navy-300 hover:border-emerald-400' }}">{{ $done ? '✓' : '' }}</button>
                                        <button type="button" wire:click="editItem({{ $kid->id }})" class="flex-1 truncate text-left text-[0.72rem] {{ $done ? 'text-navy-300 line-through' : 'text-navy-600' }}">{{ $kid->title }}</button>
                                    </div>
                                @empty
                                    <p class="text-[0.68rem] italic text-navy-300">No subtasks yet.</p>
                                @endforelse
                            </div>
                        </div>
                    @endif
                </div>

                {{-- footer --}}
                <div class="flex shrink-0 items-center gap-2 border-t border-line bg-page/40 px-4 py-2.5">
                    @if ($editingId)
                        <button type="button" wire:click="deleteItem({{ $editingId }})" wire:confirm="Delete “{{ $title }}”? Any subtasks go with it."
                                class="rounded-lg px-2.5 py-1.5 text-[0.66rem] font-bold text-risk transition hover:bg-risk/10">Delete</button>

                        <div class="ml-auto flex items-center gap-2">
                            @if ($justSaved)
                                <span wire:loading.remove class="flex items-center gap-1 text-[0.62rem] font-semibold text-emerald-600"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Saved</span>
                            @endif
                            <span wire:loading class="flex items-center gap-1 text-[0.62rem] font-semibold text-gold-600"><span class="h-1.5 w-1.5 animate-pulse rounded-full bg-gold-500"></span> Saving…</span>
                            <button type="button" wire:click="saveItem" class="btn-navy h-9 px-5 text-[0.7rem]">Save</button>
                        </div>
                    @else
                        <button type="button" wire:click="closePanel" class="rounded-lg px-3 py-1.5 text-[0.68rem] font-semibold text-navy-500 transition hover:text-navy-900">Cancel</button>
                        <button type="button" wire:click="saveItem" class="btn-navy ml-auto h-9 px-5 text-[0.7rem]">Create {{ $formParentId ? 'subtask' : 'task' }}</button>
                    @endif
                </div>
            </div>
        </aside>
    @endif
    </div>

    @script
    <script>
        window.__planWire = $wire;
        if (! window.__planDragBound) {
            window.__planDragBound = true;
            let drag = null;
            const MON = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const shift = (iso, n) => {
                const [y, m, d] = iso.split('-').map(Number);
                const dt = new Date(Date.UTC(y, m - 1, d));
                dt.setUTCDate(dt.getUTCDate() + n);
                return dt.getUTCDate() + ' ' + MON[dt.getUTCMonth()];
            };
            let tip = document.getElementById('plan-drag-tip');
            if (! tip) {
                tip = document.createElement('div');
                tip.id = 'plan-drag-tip';
                tip.style.cssText = 'position:fixed;z-index:9999;pointer-events:none;background:#0B1F3A;color:#fff;font-size:11px;font-weight:600;padding:3px 9px;border-radius:8px;box-shadow:0 8px 22px rgba(11,31,58,.3);display:none';
                document.body.appendChild(tip);
            }

            document.addEventListener('pointerdown', e => {
                const bar = e.target.closest('.plan-bar');
                if (! bar || e.button !== 0 || e.target.closest('[data-block-action]')) return;
                const track = bar.closest('[data-plan-track]');
                if (! track) return;
                const handle = e.target.closest('[data-resize]');
                drag = {
                    bar, id: +bar.dataset.taskId,
                    mode: handle ? handle.dataset.resize : 'move',
                    start: bar.dataset.start, end: bar.dataset.end,
                    span: +track.dataset.spanDays, trackW: track.getBoundingClientRect().width,
                    x: e.clientX, y: e.clientY, moved: false, deltaDays: 0,
                    baseLeft: parseFloat(bar.style.left) || 0,
                    baseWidth: parseFloat(bar.style.width) || 0,
                };
                e.preventDefault();
            });

            document.addEventListener('pointermove', e => {
                if (! drag) return;
                const dx = e.clientX - drag.x, dy = e.clientY - drag.y;
                if (! drag.moved && Math.abs(dx) < 4 && Math.abs(dy) < 4) return;
                drag.moved = true;
                drag.bar.style.opacity = '0.9';
                const pxPerDay = drag.trackW / drag.span;
                drag.deltaDays = Math.round(dx / pxPerDay);
                const dPct = drag.deltaDays / drag.span * 100;

                let label;
                if (drag.mode === 'move') {
                    drag.bar.style.left = (drag.baseLeft + dPct) + '%';
                    label = shift(drag.start, drag.deltaDays) + ' – ' + shift(drag.end, drag.deltaDays);
                } else if (drag.mode === 'left') {
                    drag.bar.style.left = (drag.baseLeft + dPct) + '%';
                    drag.bar.style.width = Math.max(drag.baseWidth - dPct, 0.5) + '%';
                    label = shift(drag.start, drag.deltaDays) + ' – ' + shift(drag.end, 0);
                } else {
                    drag.bar.style.width = Math.max(drag.baseWidth + dPct, 0.5) + '%';
                    label = shift(drag.start, 0) + ' – ' + shift(drag.end, drag.deltaDays);
                }
                tip.textContent = label;
                tip.style.display = 'block';
                tip.style.left = (e.clientX + 14) + 'px';
                tip.style.top = (e.clientY - 32) + 'px';
            });

            window.addEventListener('pointerup', () => {
                if (! drag) return;
                const d = drag; drag = null;
                d.bar.style.opacity = '';
                tip.style.display = 'none';
                const w = window.__planWire;
                if (! d.moved) { w.editItem(d.id); return; }
                if (d.mode === 'move') {
                    w.moveTask(d.id, d.deltaDays);
                } else {
                    w.resizeTask(d.id, d.mode, d.deltaDays);
                }
            });
        }
    </script>
    @endscript
</div>
