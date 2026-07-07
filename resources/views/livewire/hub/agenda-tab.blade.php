<div>
    {{-- Conflict banner --}}
    @php $conflictCount = collect($conflicts)->count(); @endphp
    @if ($conflictCount > 0)
        <div class="mb-4 flex items-center gap-2.5 rounded-2xl border border-risk/30 bg-risk/5 px-4 py-3">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-risk/10 text-risk">⚠</span>
            <p class="text-xs font-semibold text-red-700">
                {{ $conflictCount }} scheduling {{ str('conflict')->plural($conflictCount) }} detected —
                <span class="font-normal text-red-600">sessions overlapping in the same room or with the same speaker are flagged below.</span>
            </p>
        </div>
    @endif

    {{-- Toolbar --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-muted">Drag the ⠿ handle to reorder sessions or move them between days.</p>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('events.run-of-show', $event) }}" class="flex h-9 items-center rounded-xl border border-line bg-white px-3.5 text-xs font-semibold text-navy-700 transition hover:border-gold-300">⧗ Run of Show</a>
            <button type="button" wire:click="$toggle('showImport')" class="h-9 rounded-xl border border-line bg-white px-3.5 text-xs font-semibold text-navy-700 transition hover:border-gold-300">⇪ Import CSV</button>
            <a href="{{ route('events.agenda.pdf', $event) }}" class="flex h-9 items-center rounded-xl border border-line bg-white px-3.5 text-xs font-semibold text-navy-700 transition hover:border-gold-300">⇩ Export PDF</a>
            <button type="button" wire:click="addDay" class="h-9 rounded-xl border border-line bg-white px-3.5 text-xs font-semibold text-navy-700 transition hover:border-gold-300">＋ Add Day</button>
            <button type="button" wire:click="newSession({{ $days->first()?->id ?? 0 }})" @disabled($days->isEmpty()) class="btn-gold h-9 px-4 text-xs disabled:opacity-40">＋ Add Session</button>
        </div>
    </div>

    {{-- Import panel --}}
    @if ($showImport)
        <form wire:submit="import" class="card mb-5 p-5">
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1">
                    <label class="mb-1 block text-xs font-medium text-navy-800" for="import-file">CSV file</label>
                    <input id="import-file" type="file" wire:model="importFile" accept=".csv,text/csv" class="input h-10 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-white">
                    <p class="mt-1 text-[0.65rem] text-muted">Columns: <code>title, type, start, end, room, speaker, moderator, track</code> — imported into the first day.</p>
                    @error('importFile') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                </div>
                <div class="flex gap-2">
                    <button type="button" wire:click="$set('showImport', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
                    <button type="submit" class="btn-navy h-10 px-5 text-xs" wire:loading.attr="disabled" wire:target="import,importFile">
                        <span wire:loading.remove wire:target="import">Import</span>
                        <span wire:loading wire:target="import,importFile">Working…</span>
                    </button>
                </div>
            </div>
        </form>
    @endif

    {{-- Session form --}}
    @if ($showForm)
        <form wire:submit="saveSession" class="card mb-5 grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-4">
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-medium text-navy-800" for="s-title">Session title</label>
                <input id="s-title" type="text" wire:model="title" class="input h-10 text-sm" placeholder="e.g. Keynote: The Future of FinTech">
                @error('title') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-navy-800" for="s-day">Day</label>
                <select id="s-day" wire:model="agenda_day_id" class="input h-10 text-sm">
                    @foreach ($days as $day)<option value="{{ $day->id }}">{{ $day->label }} · {{ $day->date?->format('M j') }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-navy-800" for="s-room">Room</label>
                <select id="s-room" wire:model="room_id" class="input h-10 text-sm">
                    <option value="">—</option>
                    @foreach ($rooms as $room)<option value="{{ $room->id }}">{{ $room->name }}@if ($room->capacity) ({{ number_format($room->capacity) }}) @endif</option>@endforeach
                </select>
                @if ($rooms->isEmpty())
                    <p class="mt-1 text-[0.65rem] text-muted">No rooms yet — add them in the <a href="{{ route('events.hub', [$event, 'tab' => 'venue']) }}" class="font-semibold text-gold-600">Venue tab</a>.</p>
                @endif
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-navy-800" for="s-type">Type</label>
                <select id="s-type" wire:model="type" class="input h-10 text-sm">
                    @foreach (\App\Models\EventAgendaSession::TYPES as $t)<option value="{{ $t }}">{{ str($t)->replace('_', ' ')->title() }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-navy-800" for="s-status">Status</label>
                <select id="s-status" wire:model="status" class="input h-10 text-sm">
                    @foreach (\App\Models\EventAgendaSession::STATUSES as $st)<option value="{{ $st }}">{{ str($st)->replace('_', ' ')->title() }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-navy-800" for="s-start">Start</label>
                <input id="s-start" type="time" wire:model="starts_at" class="input h-10 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-navy-800" for="s-end">End</label>
                <input id="s-end" type="time" wire:model="ends_at" class="input h-10 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-navy-800" for="s-speaker">Speaker</label>
                <input id="s-speaker" type="text" wire:model="speaker" class="input h-10 text-sm" placeholder="Optional">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-navy-800" for="s-track">Track</label>
                <input id="s-track" type="text" wire:model="track" class="input h-10 text-sm" placeholder="Optional">
            </div>
            <div class="flex items-end justify-end gap-2 sm:col-span-2 xl:col-span-4">
                <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
                <button type="submit" class="btn-navy h-10 px-5 text-xs">{{ $editingId ? 'Update Session' : 'Add Session' }}</button>
            </div>
        </form>
    @endif

    {{-- Day columns --}}
    @if ($days->isEmpty())
        <div class="card flex flex-col items-center px-8 py-16 text-center">
            <h3 class="text-sm font-bold text-navy-900">No agenda yet</h3>
            <p class="mt-1 max-w-md text-xs text-muted">Add a day to start building the program, or import sessions from a CSV.</p>
            <button type="button" wire:click="addDay" class="btn-gold mt-4 h-9 px-4 text-xs">＋ Add First Day</button>
        </div>
    @else
        <div class="flex gap-4 overflow-x-auto pb-2">
            @foreach ($days as $day)
                <div class="w-[320px] shrink-0" wire:key="day-{{ $day->id }}">
                    <div class="card flex h-full flex-col overflow-hidden">
                        <div class="flex items-center justify-between border-b border-line px-4 py-3">
                            <div>
                                <p class="text-sm font-bold text-navy-900">{{ $day->label }}</p>
                                <p class="text-[0.65rem] text-muted">{{ $day->date?->format('l, M j, Y') }} · {{ $day->sessions->count() }} sessions</p>
                            </div>
                            <details class="relative">
                                <summary class="flex cursor-pointer list-none rotate-90 text-navy-400 hover:text-navy-700 [&::-webkit-details-marker]:hidden"><x-icon name="dots" class="h-4 w-4" /></summary>
                                <div class="absolute right-0 top-6 z-30 w-40 overflow-hidden rounded-xl border border-line bg-white shadow-lg">
                                    <button type="button" wire:click="newSession({{ $day->id }})" class="block w-full px-3.5 py-2.5 text-left text-xs font-semibold text-navy-700 hover:bg-gold-50/60">＋ Add session here</button>
                                    <button type="button" wire:click="duplicateDay({{ $day->id }})" class="block w-full px-3.5 py-2.5 text-left text-xs font-semibold text-navy-700 hover:bg-gold-50/60">Duplicate day</button>
                                    <button type="button" wire:click="deleteDay({{ $day->id }})" wire:confirm="Delete “{{ $day->label }}” and its sessions?" class="block w-full border-t border-line px-3.5 py-2.5 text-left text-xs font-semibold text-risk hover:bg-risk/5">Delete day</button>
                                </div>
                            </details>
                        </div>

                        <div data-agenda-list data-day-id="{{ $day->id }}" class="min-h-[80px] flex-1 space-y-2 p-3">
                            @foreach ($day->sessions as $session)
                                @php
                                    $tone = match ($session->status) {
                                        'final', 'confirmed' => 'border-l-track',
                                        'waiting_speaker', 'needs_review' => 'border-l-warn',
                                        default => 'border-l-navy-200',
                                    };
                                @endphp
                                @php $sessionConflicts = $conflicts[$session->id] ?? []; @endphp
                                <div data-session="{{ $session->id }}" wire:key="sess-{{ $session->id }}"
                                     class="group rounded-xl border border-l-[3px] {{ $tone }} bg-white p-3 shadow-[0_1px_3px_rgba(15,23,42,0.05)] {{ $sessionConflicts ? 'border-risk/50 ring-1 ring-risk/20' : 'border-line' }}">
                                    <div class="flex items-start gap-2">
                                        <button type="button" data-drag class="mt-0.5 cursor-grab text-navy-300 transition hover:text-navy-600 active:cursor-grabbing" title="Drag to reorder">⠿</button>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center justify-between gap-2">
                                                <p class="text-xs font-bold text-navy-900">{{ substr($session->starts_at, 0, 5) }}–{{ substr($session->ends_at, 0, 5) }}</p>
                                                @if ($sessionConflicts)
                                                    <span class="shrink-0 rounded-full bg-risk/10 px-1.5 py-0.5 text-[0.55rem] font-bold uppercase text-red-700"
                                                          title="{{ collect($sessionConflicts)->pluck('text')->implode(' · ') }}">⚠ Conflict</span>
                                                @endif
                                            </div>
                                            <p class="truncate text-sm font-semibold text-navy-900">{{ $session->title }}</p>
                                            <p class="mt-0.5 truncate text-[0.65rem] text-muted">
                                                {{ str($session->type)->replace('_', ' ')->title() }}
                                                @if ($session->room) · 📍 {{ $session->room->name }} @endif
                                                @if ($session->speaker) · 🎤 {{ $session->speaker }} @endif
                                            </p>
                                            @foreach ($sessionConflicts as $c)
                                                <p class="mt-1 flex items-start gap-1 text-[0.6rem] font-medium text-red-600">
                                                    <span class="shrink-0">{{ $c['type'] === 'room' ? '📍' : '🎤' }}</span> {{ $c['text'] }}
                                                </p>
                                            @endforeach
                                            <div class="mt-1.5 flex items-center justify-between">
                                                <x-status-badge :status="$session->status" class="!text-[0.55rem]" />
                                                <span class="flex gap-1 opacity-0 transition group-hover:opacity-100">
                                                    <button type="button" wire:click="editSession({{ $session->id }})" class="rounded-lg bg-navy-50 px-1.5 py-0.5 text-[0.6rem] font-bold text-navy-600 hover:bg-navy-100" title="Edit">✎</button>
                                                    <button type="button" wire:click="deleteSession({{ $session->id }})" wire:confirm="Delete this session?" class="rounded-lg bg-risk/10 px-1.5 py-0.5 text-[0.6rem] font-bold text-red-700 hover:bg-risk/20" title="Delete">✕</button>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @script
    <script>
        const initAgenda = () => {
            document.querySelectorAll('[data-agenda-list]').forEach((el) => {
                if (el._sortable) return;
                el._sortable = window.Sortable.create(el, {
                    group: 'agenda',
                    animation: 150,
                    handle: '[data-drag]',
                    ghostClass: 'opacity-40',
                    onEnd: (evt) => {
                        const collect = (list) => ({
                            dayId: parseInt(list.dataset.dayId),
                            ids: [...list.querySelectorAll('[data-session]')].map((n) => parseInt(n.dataset.session)),
                        });
                        const groups = [collect(evt.to)];
                        if (evt.from !== evt.to) groups.push(collect(evt.from));
                        $wire.reorder(groups);
                    },
                });
            });
        };
        initAgenda();
        Livewire.hook('morph.updated', () => initAgenda());
    </script>
    @endscript
</div>
