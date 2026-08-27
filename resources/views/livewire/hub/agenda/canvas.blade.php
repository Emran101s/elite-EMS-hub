            {{-- ═══ CENTER CANVAS ═══ --}}
            <div class="rounded-lg border border-line bg-white min-w-0 overflow-hidden !p-0">
                @if ($view === 'program')
                    @php $programEmpty = collect($programDays)->every(fn ($d) => \App\Services\AgendaProgram::isEmpty($d['program'])); @endphp
                    @if ($programEmpty)
                        <x-empty icon="calendar" class="!border-0 !shadow-none"
                                 :title="$daySessions ? 'Nothing on the public programme for this day' : 'Nothing scheduled for this day'"
                                 :hint="$daySessions
                                     ? 'Everything on this day sits on a back-of-house track, so a delegate would see an empty page. Switch to Internal to work on it.'
                                     : 'Add a session and it appears here as a programme card, ready to print.'">
                            <x-slot:actions>
                                @if ($daySessions)
                                    <button type="button" wire:click="setAudience('internal')" class="btn-gold btn-sm">Show the internal programme</button>
                                @else
                                    <button type="button" wire:click="newSession" class="btn-gold btn-sm">＋ Add the first session</button>
                                @endif
                            </x-slot:actions>
                        </x-empty>
                    @else
                        <div class="p-5"><x-agenda-program :days="$programDays" :legend="false" /></div>
                    @endif
                @elseif ($view === 'timeline')
                    @if ($timeline)
                        @include('livewire.hub.partials.agenda-gantt', ['lanes' => $timeline['lanes']])
                    @else
                        <x-empty icon="chart" class="!border-0 !shadow-none" title="Nothing scheduled for this day"
                                 hint="Sessions plot themselves against the clock as you add them, one lane per room. Copy yesterday's running order if this day repeats it.">
                            <x-slot:actions>
                                <button type="button" wire:click="newSession" class="btn-gold btn-sm">＋ Add the first session</button>
                                @if ($days->count() > 1)
                                    <button type="button" wire:click="$toggle('showImport')" class="btn-ghost btn-sm">Import from CSV</button>
                                @endif
                            </x-slot:actions>
                        </x-empty>
                    @endif

                {{-- ═══ ROOMS VIEW ═══ the same board, seen one room at a
                     time (or every room, same as Timeline) — same lanes,
                     same blocks, same drag. Use existing EventRoom data;
                     no parallel room system. ═══ --}}
                @elseif ($view === 'rooms')
                    <div class="flex flex-wrap items-center gap-1.5 border-b border-line px-4 py-2.5">
                        <span class="shrink-0 text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Room</span>
                        <button type="button" wire:click="selectRoomFilter(null)"
                                @class(['rounded-full border px-2.5 py-1 text-[10.5px] font-bold transition', 'border-navy-900 bg-navy-900 text-white' => ! $roomsViewFilter, 'border-line bg-white text-muted hover:border-gold-300' => $roomsViewFilter])>
                            All Rooms
                        </button>
                        @foreach ($rooms as $room)
                            <button type="button" wire:click="selectRoomFilter({{ $room->id }})"
                                    @class(['rounded-full border px-2.5 py-1 text-[10.5px] font-bold transition', 'border-navy-900 bg-navy-900 text-white' => $roomsViewFilter === $room->id, 'border-line bg-white text-muted hover:border-gold-300' => $roomsViewFilter !== $room->id])>
                                {{ $room->name }}
                            </button>
                        @endforeach
                    </div>

                    @php
                        $roomWorkload = $roomsViewFilter ? $venues->firstWhere('room.id', $roomsViewFilter) : null;
                        $roomsLanes = $timeline
                            ? ($roomsViewFilter ? $timeline['lanes']->filter(fn ($l) => $l['room_id'] === $roomsViewFilter)->values() : $timeline['lanes'])
                            : collect();
                    @endphp

                    @if ($roomWorkload)
                        {{-- Workload — sessions, conflicts, capacity, read off
                             the same venue-rail figures the left rail already
                             shows; nothing recomputed for this header. --}}
                        <div class="grid grid-cols-3 divide-x divide-line border-b border-line text-center">
                            <div class="px-3 py-3"><p class="text-[18px] font-black text-ink">{{ $roomWorkload['sessions'] }}</p><p class="text-[10px] text-muted">{{ str('Session')->plural($roomWorkload['sessions']) }} today</p></div>
                            <div class="px-3 py-3"><p class="text-[18px] font-black {{ $roomWorkload['conflicts'] ? 'text-danger-ink' : 'text-ink' }}">{{ $roomWorkload['conflicts'] }}</p><p class="text-[10px] text-muted">{{ str('Conflict')->plural($roomWorkload['conflicts']) }}</p></div>
                            <div class="px-3 py-3"><p class="text-[18px] font-black text-ink">{{ $roomWorkload['room']->capacity ? number_format($roomWorkload['room']->capacity) : '—' }}</p><p class="text-[10px] text-muted">Capacity</p></div>
                        </div>
                    @endif

                    @if ($roomsViewFilter && $roomsLanes->isEmpty())
                        <x-empty icon="building" class="!border-0 !shadow-none" title="Empty room — nothing scheduled today"
                                 hint="This room has no sessions on this day. Add one, or drag a session here from another lane in All Rooms.">
                            <x-slot:actions>
                                <button type="button" wire:click="newSession" class="btn-gold btn-sm">＋ Add a session</button>
                            </x-slot:actions>
                        </x-empty>
                    @else
                        @include('livewire.hub.partials.agenda-gantt', ['lanes' => $roomsLanes])
                    @endif

                {{-- ═══ TRACKS VIEW ═══ the day's sessions grouped by track,
                     in running order — continuity across the day rather
                     than across rooms. Uses the existing free-text track
                     column; no new schema. ═══ --}}
                @elseif ($view === 'tracks')
                    <div class="space-y-5 p-4">
                        @forelse ($trackGroups as $group)
                            <div>
                                <div class="mb-2 flex items-center gap-2 px-0.5">
                                    <p class="text-[12.5px] font-bold text-ink">{{ $group['name'] }}</p>
                                    <span class="rounded-full bg-page px-2 py-0.5 text-[10px] font-bold text-muted">{{ $group['sessions']->count() }}</span>
                                    @if ($group['name'] === 'Unassigned')
                                        <span class="text-[10px] italic text-muted">no track chosen yet</span>
                                    @endif
                                </div>
                                <div class="space-y-1.5">
                                    @foreach ($group['sessions'] as $s)
                                        @php
                                            $hasConflict = isset($conflicts[$s->id]);
                                            $severity = $severityBySession[$s->id] ?? null;
                                            $selected = $selectedSession && $selectedSession->id === $s->id;
                                            $tTone = match (true) {
                                                $hasConflict => 'risk',
                                                in_array($s->status, ['confirmed', 'final'], true) => 'ok',
                                                in_array($s->status, ['needs_review', 'waiting_speaker'], true) => 'warn',
                                                default => 'pending',
                                            };
                                        @endphp
                                        <button type="button" wire:click="selectSession({{ $s->id }})"
                                                @class([
                                                    'flex w-full items-center gap-3 rounded-xl border px-3 py-2.5 text-left transition hover:border-gold-300',
                                                    'border-gold-400 bg-gold-50/30' => $selected,
                                                    'border-line bg-white' => ! $selected,
                                                ])>
                                            <span class="w-12 shrink-0 font-mono text-[11px] text-muted">{{ substr($s->starts_at, 0, 5) }}</span>
                                            <span class="min-w-0 flex-1">
                                                <span class="flex items-center gap-1.5">
                                                    @if ($severity === 'critical')
                                                        <span class="shrink-0 text-danger" title="Critical — scheduling conflict">⚠</span>
                                                    @elseif ($severity === 'high')
                                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-warning" title="High priority"></span>
                                                    @elseif ($severity === 'medium')
                                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-warning/50" title="Needs attention"></span>
                                                    @endif
                                                    <span class="truncate text-[12.5px] font-bold text-ink">{{ $s->title }}</span>
                                                </span>
                                                <span class="block truncate text-[10.5px] text-muted">{{ $s->room?->name ?? 'No room' }}@if ($s->speakers->isNotEmpty()) · {{ $s->speakers->first()->name }}@endif</span>
                                            </span>
                                            <x-eo.status-pill :tone="$tTone" class="shrink-0 !text-[9px]">{{ $s->statusLabel() }}</x-eo.status-pill>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <x-empty icon="columns" class="!border-0 !shadow-none" title="Nothing scheduled for this day"
                                     hint="Sessions will group themselves by track as you add them — give one a track in the session form and it gets its own lane here.">
                                <x-slot:actions>
                                    <button type="button" wire:click="newSession" class="btn-gold btn-sm">＋ Add the first session</button>
                                </x-slot:actions>
                            </x-empty>
                        @endforelse
                    </div>

                {{-- ═══ SESSION LIST VIEW ═══ every session, every day — the
                     audit view. Row click opens the Inspector; no drag here,
                     a table row is the wrong gesture for it. ═══ --}}
                @elseif ($view === 'sessions')
                    <div class="border-b border-line p-3">
                        <div class="relative max-w-xs">
                            <x-icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted" />
                            <input type="search" wire:model.live.debounce.250ms="sessionSearch" placeholder="Search title or speaker…"
                                   class="h-9 w-full rounded-lg border border-line bg-white ps-8 pe-2 text-[12px] text-ink focus:border-navy-300 focus:outline-none">
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-[12px]">
                            <thead>
                                <tr class="border-b border-line bg-page text-left text-[10px] font-bold uppercase tracking-[0.06em] text-muted">
                                    <th class="whitespace-nowrap px-3 py-2">Title</th>
                                    <th class="whitespace-nowrap px-3 py-2">Day</th>
                                    <th class="whitespace-nowrap px-3 py-2">Time</th>
                                    <th class="whitespace-nowrap px-3 py-2">Room</th>
                                    <th class="whitespace-nowrap px-3 py-2">Track</th>
                                    <th class="whitespace-nowrap px-3 py-2">Speaker</th>
                                    <th class="whitespace-nowrap px-3 py-2">Status</th>
                                    <th class="whitespace-nowrap px-3 py-2">Capacity</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($sessionList as $s)
                                    @php
                                        $rowConflict = isset($conflictsByDay[$s->agenda_day_id][$s->id]);
                                        $rowSeverity = $severityBySession[$s->id] ?? null;
                                        $selected = $selectedSession && $selectedSession->id === $s->id;
                                        $lTone = match (true) {
                                            $rowConflict => 'risk',
                                            in_array($s->status, ['confirmed', 'final'], true) => 'ok',
                                            in_array($s->status, ['needs_review', 'waiting_speaker'], true) => 'warn',
                                            default => 'pending',
                                        };
                                    @endphp
                                    <tr wire:click="selectSession({{ $s->id }})"
                                        @class(['cursor-pointer border-b border-line transition last:border-b-0 hover:bg-page', 'bg-gold-50/20' => $selected])>
                                        <td class="max-w-[220px] truncate px-3 py-2 font-bold text-ink">
                                            <span class="flex items-center gap-1.5">
                                                @if ($rowSeverity === 'critical')
                                                    <span class="shrink-0 text-danger" title="Critical — scheduling conflict">⚠</span>
                                                @elseif ($rowSeverity === 'high')
                                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-warning" title="High priority"></span>
                                                @elseif ($rowSeverity === 'medium')
                                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-warning/50" title="Needs attention"></span>
                                                @endif
                                                <span class="truncate">{{ $s->title }}</span>
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2 text-muted">{{ $s->day?->date?->format('D j M') ?? '—' }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 font-mono text-muted">{{ substr($s->starts_at, 0, 5) }}–{{ substr($s->ends_at, 0, 5) }}</td>
                                        <td class="max-w-[120px] truncate px-3 py-2 text-muted">{{ $s->room?->name ?? '—' }}</td>
                                        <td class="max-w-[110px] truncate px-3 py-2 text-muted">{{ $s->track ?: '—' }}</td>
                                        <td class="max-w-[140px] truncate px-3 py-2 text-muted">{{ $s->speakers->pluck('name')->implode(', ') ?: '—' }}</td>
                                        <td class="whitespace-nowrap px-3 py-2"><x-eo.status-pill :tone="$lTone" class="!text-[9px]">{{ $s->statusLabel() }}</x-eo.status-pill></td>
                                        <td class="whitespace-nowrap px-3 py-2 text-muted">{{ $s->bookedCount() }}{{ $s->capacity ? '/'.number_format($s->capacity) : '' }}</td>
                                        <td class="whitespace-nowrap px-3 py-2">
                                            <div class="flex items-center justify-end gap-1">
                                                <button type="button" wire:click.stop="editSession({{ $s->id }})" class="rounded-md p-1 text-muted transition hover:bg-page hover:text-ink" title="Edit">✎</button>
                                                <button type="button" wire:click.stop="duplicateSession({{ $s->id }})" class="rounded-md p-1 text-muted transition hover:bg-page hover:text-ink" title="Duplicate">⧉</button>
                                                <x-confirm title="Delete “{{ $s->title }}”?" confirm="Delete" run="$wire.deleteSession({{ $s->id }})"
                                                           class="rounded-md p-1 text-muted transition hover:bg-danger-soft hover:text-danger" title="Delete">✕</x-confirm>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-3 py-10 text-center text-muted">
                                            {{ trim($sessionSearch) !== '' ? 'No sessions match "'.$sessionSearch.'".' : 'No sessions yet.' }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                {{-- ═══ SPEAKER VIEW ═══ every roster speaker's schedule
                     across the whole event — who's assigned, who isn't,
                     what's missing, what's unconfirmed, and same-day
                     conflicts the board already knows about. Row click
                     opens the Inspector, same as everywhere else; no drag,
                     no new cross-day conflict engine. ═══ --}}
                @elseif ($view === 'speakers')
                    @php
                        $spkTotal = $speakerBoard->count();
                        $spkAssigned = $speakerBoard->where('sessionCount', '>', 0)->count();
                        $spkNeedsAction = $speakerBoard->where('needsAction', true)->count();
                    @endphp

                    <div class="grid grid-cols-4 divide-x divide-line border-b border-line text-center">
                        <div class="px-3 py-3"><p class="text-[18px] font-black text-ink">{{ $spkTotal }}</p><p class="text-[10px] text-muted">{{ str('Speaker')->plural($spkTotal) }}</p></div>
                        <div class="px-3 py-3"><p class="text-[18px] font-black text-ink">{{ $spkAssigned }}</p><p class="text-[10px] text-muted">Assigned</p></div>
                        <div class="px-3 py-3"><p class="text-[18px] font-black {{ $spkNeedsAction ? 'text-warning-ink' : 'text-ink' }}">{{ $spkNeedsAction }}</p><p class="text-[10px] text-muted">Need action</p></div>
                        <div class="px-3 py-3"><p class="text-[18px] font-black {{ $unassignedSessions->isNotEmpty() ? 'text-danger-ink' : 'text-ink' }}">{{ $unassignedSessions->count() }}</p><p class="text-[10px] text-muted">Unassigned {{ str('session')->plural($unassignedSessions->count()) }}</p></div>
                    </div>

                    <div class="flex items-center justify-between border-b border-line px-4 py-2.5">
                        <p class="text-[11px] text-muted">Speaker profiles and billing live on the Speakers tab — add someone there and they appear here the moment they're billed on a session.</p>
                        <a href="{{ route('events.hub', [$event, 'tab' => 'speakers']) }}" class="shrink-0 text-[11px] font-semibold text-gold-700 hover:underline">＋ Add Speaker</a>
                    </div>

                    {{-- Highlight missing speaker assignment: sessions
                         nobody is billed on yet, surfaced up front rather
                         than only discoverable by scrolling every speaker. --}}
                    @if ($unassignedSessions->isNotEmpty())
                        <div class="border-b border-line bg-danger-soft/40 px-4 py-3">
                            <p class="mb-1.5 text-[11px] font-bold text-danger-ink">⚠ {{ $unassignedSessions->count() }} {{ str('session')->plural($unassignedSessions->count()) }} with no speaker billed</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($unassignedSessions as $u)
                                    <button type="button" wire:click="selectSession({{ $u->id }})"
                                            class="rounded-lg border border-danger/25 bg-white px-2.5 py-1 text-[11px] font-semibold text-ink transition hover:border-danger/50">
                                        {{ substr($u->starts_at, 0, 5) }} · {{ $u->title }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="divide-y divide-line">
                        @forelse ($speakerBoard as $row)
                            @php
                                $sp = $row['speaker'];
                                $spTone = match (true) {
                                    $sp->status === 'declined' || $sp->status === 'cancelled' => 'risk',
                                    $sp->status === 'confirmed' => 'ok',
                                    default => 'pending',
                                };
                            @endphp
                            <div class="p-4">
                                <details>
                                    <summary class="flex cursor-pointer list-none items-center gap-3">
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-navy-900 text-[11px] font-bold text-white">{{ $sp->initials() }}</span>
                                        <span class="min-w-0 flex-1">
                                            <span class="flex flex-wrap items-center gap-1.5">
                                                <span class="text-[13px] font-bold text-ink">{{ $sp->name }}</span>
                                                @if ($row['worstSeverity'] === 'medium')
                                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-warning/50" title="Needs attention"></span>
                                                @endif
                                                @if ($sp->is_keynote)<span title="Keynote">⭐</span>@endif
                                                <x-eo.status-pill :tone="$spTone" class="!text-[9px]">{{ ucfirst($sp->status) }}</x-eo.status-pill>
                                                @if ($row['hasConflict'])
                                                    <x-eo.status-pill tone="risk" class="!text-[9px]">Conflict</x-eo.status-pill>
                                                @elseif ($row['worstSeverity'] === 'high')
                                                    <x-eo.status-pill tone="warn" class="!text-[9px]">High priority</x-eo.status-pill>
                                                @endif
                                                @if ($row['unconfirmedCount'])<x-eo.status-pill tone="warn" class="!text-[9px]">{{ $row['unconfirmedCount'] }} unconfirmed</x-eo.status-pill>@endif
                                                @if ($row['missingDetails']->isNotEmpty())<x-eo.status-pill tone="warn" class="!text-[9px]">Missing {{ $row['missingDetails']->implode(', ') }}</x-eo.status-pill>@endif
                                            </span>
                                            <span class="mt-0.5 block text-[10.5px] text-muted">{{ $sp->title ?: 'No title on file' }}{{ $sp->organization ? ' · '.$sp->organization : '' }} — {{ $row['sessionCount'] }} {{ str('session')->plural($row['sessionCount']) }}</span>
                                        </span>
                                        <span class="shrink-0 text-[10px] font-semibold text-gold-700">Details ▾</span>
                                    </summary>
                                    <div class="mt-3 grid gap-x-6 gap-y-1 rounded-xl bg-page px-3.5 py-3 text-[11.5px] sm:grid-cols-2">
                                        <p><span class="text-muted">Topic</span> — {{ $sp->topic ?: 'Not set' }}</p>
                                        <p><span class="text-muted">Email</span> — {{ $sp->email ?: 'Not set' }}</p>
                                        <p><span class="text-muted">Phone</span> — {{ $sp->phone ?: 'Not set' }}</p>
                                        <p><span class="text-muted">Fee</span> — {{ $sp->fee_cents ? number_format($sp->fee_cents / 100, 2) : 'Not set' }}</p>
                                    </div>
                                </details>

                                <div class="ms-12 mt-2.5 space-y-1.5">
                                    @forelse ($row['rows'] as $r)
                                        @php
                                            $s = $r['session'];
                                            $rTone = match (true) {
                                                $r['hasConflict'] => 'risk',
                                                in_array($s->status, ['confirmed', 'final'], true) => 'ok',
                                                in_array($s->status, ['needs_review', 'waiting_speaker'], true) => 'warn',
                                                default => 'pending',
                                            };
                                        @endphp
                                        <button type="button" wire:click="selectSession({{ $s->id }})"
                                                class="flex w-full items-center gap-3 rounded-xl border border-line bg-white px-3 py-2 text-left transition hover:border-gold-300">
                                            <span class="w-12 shrink-0 font-mono text-[11px] text-muted">{{ $r['missingTime'] ? '—' : substr($s->starts_at, 0, 5) }}</span>
                                            <span class="min-w-0 flex-1">
                                                <span class="flex items-center gap-1.5">
                                                    @if ($r['severity'] === 'critical')
                                                        <span class="shrink-0 text-danger" title="Critical — scheduling conflict">⚠</span>
                                                    @elseif ($r['severity'] === 'high')
                                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-warning" title="High priority"></span>
                                                    @elseif ($r['severity'] === 'medium')
                                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-warning/50" title="Needs attention"></span>
                                                    @endif
                                                    <span class="truncate text-[12px] font-bold text-ink">{{ $s->title }}</span>
                                                </span>
                                                <span class="block truncate text-[10.5px] text-muted">
                                                    {{ $s->day?->date?->format('D j M') ?? '—' }} ·
                                                    {{ $r['missingRoom'] ? 'No room' : $s->room?->name }}
                                                    @if ($s->track) · {{ $s->track }} @endif
                                                </span>
                                            </span>
                                            <x-eo.status-pill :tone="$rTone" class="shrink-0 !text-[9px]">{{ $s->statusLabel() }}</x-eo.status-pill>
                                        </button>
                                    @empty
                                        <p class="text-[11px] italic text-muted">Not yet assigned to a session.</p>
                                    @endforelse
                                </div>
                            </div>
                        @empty
                            <x-empty icon="users" class="!border-0 !shadow-none" title="No speakers on the roster yet"
                                     hint="Bill someone onto a session and they'll appear here, or add them directly on the Speakers tab.">
                                <x-slot:actions>
                                    <a href="{{ route('events.hub', [$event, 'tab' => 'speakers']) }}" class="btn-gold btn-sm">＋ Add Speaker</a>
                                </x-slot:actions>
                            </x-empty>
                        @endforelse
                    </div>
                @endif
            </div>

