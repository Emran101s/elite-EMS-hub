<div>
    @php
        $allSessions = $days->flatMap->sessions;
        $toMin = fn ($t) => (int) substr((string) $t, 0, 2) * 60 + (int) substr((string) $t, 3, 2);
        $totalSessions = $allSessions->count();
        $settled = $allSessions->filter(fn ($s) => $s->isSettled())->count();
        $flaggedCount = $allSessions->where('flagged', true)->count();
        $agendaHours = round($allSessions->sum(fn ($s) => max($toMin($s->ends_at) - $toMin($s->starts_at), 0)) / 60, 1);
        $speakerTotal = $allSessions->flatMap(fn ($s) => $s->speakers->pluck('id'))->unique()->count();
        $roomTotal = $allSessions->pluck('room.name')->filter()->unique()->count();
        $confPct = $totalSessions ? (int) round($settled / $totalSessions * 100) : 0;
        $hdrRing = 2 * M_PI * 26;
        $unconfirmed = $totalSessions - $settled;
    @endphp

    {{-- ══════════ COMMAND HEADER ══════════ --}}
    <div class="mb-4 flex flex-wrap items-stretch gap-3">
        <div class="strip-dark relative flex min-w-0 flex-1 flex-wrap items-center gap-x-8 gap-y-4 overflow-hidden px-5 py-4">
            <div class="pointer-events-none absolute -right-8 -top-12 h-40 w-40 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.25),transparent_70%)]"></div>
            <div class="relative flex shrink-0 items-center gap-3">
                <div class="relative">
                    <svg class="h-[70px] w-[70px] -rotate-90" viewBox="0 0 60 60">
                        <circle cx="30" cy="30" r="26" fill="none" stroke="rgba(255,255,255,.12)" stroke-width="6"/>
                        <circle cx="30" cy="30" r="26" fill="none" stroke="var(--color-gold-500)" stroke-width="6" stroke-linecap="round" stroke-dasharray="{{ $hdrRing }}" stroke-dashoffset="{{ $hdrRing - ($hdrRing * $confPct / 100) }}"/>
                    </svg>
                    <span class="absolute inset-0 flex items-center justify-center text-sm font-black text-white">{{ $confPct }}%</span>
                </div>
                <div>
                    <p class="text-eyebrow font-bold uppercase tracking-[0.2em] text-gold-300">Agenda</p>
                    <p class="pf text-2xl font-black leading-none text-white">{{ $settled }}<span class="text-base text-white/40">/{{ $totalSessions }}</span></p>
                    <p class="mt-0.5 text-eyebrow font-semibold text-white/55">sessions confirmed</p>
                </div>
            </div>

            <div class="relative flex min-w-0 flex-1 flex-wrap items-center gap-2">
                @foreach ([['Days', $days->count()], ['Sessions', $totalSessions], ['Hours', $agendaHours], ['Speakers', $speakerTotal], ['Rooms', $roomTotal]] as [$lbl, $val])
                    <div class="flex min-w-[3.6rem] flex-1 flex-col items-center rounded-lg bg-white/[0.06] py-1.5 ring-1 ring-white/10">
                        <span class="text-base font-black text-white">{{ $val }}</span>
                        <span class="text-eyebrow font-bold uppercase tracking-wide text-white/45">{{ $lbl }}</span>
                    </div>
                @endforeach
            </div>

            <div class="relative flex shrink-0 gap-2">
                <div class="flex h-[62px] w-[4.4rem] flex-col justify-center rounded-xl bg-black/20 px-2 text-center ring-1 ring-white/10">
                    <span class="text-xl font-black leading-none" style="color: {{ $unconfirmed > 0 ? 'var(--color-warning-on-dark)' : 'rgba(255,255,255,.4)' }}">{{ $unconfirmed }}</span>
                    <span class="mt-1 text-eyebrow font-bold uppercase leading-tight tracking-wider text-white/50">Unconfirmed</span>
                </div>
                <div class="flex h-[62px] w-[4.4rem] flex-col justify-center rounded-xl bg-black/20 px-2 text-center ring-1 ring-white/10">
                    <span class="text-xl font-black leading-none" style="color: {{ $flaggedCount > 0 ? 'var(--color-danger-on-dark)' : 'rgba(255,255,255,.4)' }}">{{ $flaggedCount }}</span>
                    <span class="mt-1 text-eyebrow font-bold uppercase leading-tight tracking-wider text-white/50">Flagged</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Toolbar ══ --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        {{-- View switch: room timeline ⇄ programme board --}}
        <div class="flex rounded-xl border border-line bg-white p-0.5 shadow-sm">
            <button type="button" wire:click="setView('timeline')"
                    @class(['flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-micro font-bold transition', 'bg-navy-900 text-white' => $view === 'timeline', 'text-navy-400 hover:text-navy-700' => $view !== 'timeline'])>
                <x-icon name="chart" class="h-3.5 w-3.5" /> Timeline
            </button>
            <button type="button" wire:click="setView('program')"
                    @class(['flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-micro font-bold transition', 'bg-navy-900 text-white' => $view === 'program', 'text-navy-400 hover:text-navy-700' => $view !== 'program'])>
                <x-icon name="calendar" class="h-3.5 w-3.5" /> Programme
            </button>
        </div>

        @if ($view === 'program')
            {{-- Who the programme is for: ops sees build/press time, delegates don't. --}}
            <div class="flex rounded-xl border border-line bg-white p-0.5 shadow-sm" title="Public hides setup, press and registration">
                <button type="button" wire:click="setAudience('internal')"
                        @class(['rounded-lg px-3 py-1.5 text-micro font-bold transition', 'bg-navy-900 text-white' => $audience === 'internal', 'text-navy-400 hover:text-navy-700' => $audience !== 'internal'])>Internal</button>
                <button type="button" wire:click="setAudience('public')"
                        @class(['rounded-lg px-3 py-1.5 text-micro font-bold transition', 'bg-navy-900 text-white' => $audience === 'public', 'text-navy-400 hover:text-navy-700' => $audience !== 'public'])>Public</button>
            </div>
        @endif

        <div class="ml-auto flex flex-wrap items-center gap-2">
            <button type="button" wire:click="$toggle('showImport')" class="btn-ghost btn-sm">⇪ Import CSV</button>
            <a href="{{ route('events.run-of-show', $event) }}" class="btn-ghost btn-sm gap-1.5"><x-icon name="calendar" class="h-3.5 w-3.5" /> Run of Show</a>
            @if ($view === 'program')
                <div class="flex items-center overflow-hidden rounded-xl border border-gold-300 bg-gold-50 text-xs font-semibold text-gold-800">
                    <span class="flex h-9 items-center gap-1.5 border-r border-gold-300/70 pl-3 pr-2.5"><x-icon name="chart" class="h-3.5 w-3.5" /> Programme PDF</span>
                    @if ($day)
                        <a href="{{ route('events.agenda.program.pdf', [$event, 'day' => $day->id, 'audience' => $audience]) }}" class="flex h-9 items-center border-r border-gold-300/70 px-3 transition hover:bg-gold-100">This day</a>
                    @endif
                    <a href="{{ route('events.agenda.program.pdf', [$event, 'audience' => $audience]) }}" class="flex h-9 items-center px-3 transition hover:bg-gold-100">All days</a>
                </div>
            @else
                <a href="{{ route('events.agenda.master.pdf', $event) }}" class="btn-ghost btn-sm gap-1.5" title="Every session incl. crew, with status"><x-icon name="chart" class="h-3.5 w-3.5" /> Master Schedule</a>
                <a href="{{ route('events.run-of-show.pdf', [$event, 'day' => $day?->id]) }}" class="btn-ghost btn-sm gap-1.5" title="Show-day cue sheet for this day"><x-icon name="calendar" class="h-3.5 w-3.5" /> Run of Show</a>
            @endif
            <button type="button" wire:click="newSession" @disabled($days->isEmpty()) class="flex h-9 items-center gap-1.5 rounded-xl bg-gradient-to-r from-gold-400 to-gold-500 px-4 text-xs font-bold text-navy-950 shadow-sm transition hover:brightness-105 disabled:opacity-40">＋ Add Session</button>
        </div>
    </div>

    {{-- Import panel --}}
    @if ($showImport)
        <form wire:submit="import" class="card mb-4 flex flex-wrap items-end gap-3 p-4">
            <div class="flex-1">
                <label class="field-label !mb-1 !text-eyebrow" for="import-file">CSV file — columns: title, type, start, end, room, speaker, moderator (separate several names with ; )</label>
                <input id="import-file" type="file" wire:model="importFile" accept=".csv,text/csv" class="input h-10 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-white">
                @error('importFile') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
            </div>
            <button type="button" wire:click="$set('showImport', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
            <button type="submit" class="btn-navy h-10 px-5 text-xs" wire:loading.attr="disabled" wire:target="import,importFile">Import</button>
        </form>
    @endif

    @if ($days->isEmpty())
        <div class="card flex flex-col items-center px-8 py-16 text-center">
            <h3 class="pf text-base font-bold text-navy-900">No agenda days yet</h3>
            <p class="mt-1 max-w-md text-xs text-muted">Days are created from the event's date range — set the dates in Settings, or add one here.</p>
            <button type="button" wire:click="addDay" class="btn-gold mt-4 h-9 px-4 text-xs">＋ Add Day</button>
        </div>
    @else
        {{-- ══ Day tabs + stats ══ --}}
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap gap-2.5">
                @foreach ($days as $d)
                    <button type="button" wire:click="selectDay({{ $d->id }})"
                            @class([
                                'w-32 rounded-2xl border px-3.5 py-2.5 text-left transition',
                                'border-navy-900 bg-navy-900 text-white' => $day && $day->id === $d->id,
                                'border-line bg-white text-navy-600 hover:border-navy-200' => ! ($day && $day->id === $d->id),
                            ])>
                        <p class="text-eyebrow font-bold uppercase tracking-widest {{ $day && $day->id === $d->id ? 'text-gold-400' : 'text-muted' }}">Day {{ $loop->iteration }}</p>
                        <p class="mt-0.5 text-sm font-bold">{{ $d->date?->format('D, j M') }}</p>
                        <p class="text-eyebrow {{ $day && $day->id === $d->id ? 'text-white/60' : 'text-muted' }}">{{ $d->sessions->count() }} {{ str('session')->plural($d->sessions->count()) }}</p>
                    </button>
                @endforeach
                <button type="button" wire:click="addDay" class="flex w-11 items-center justify-center rounded-2xl border border-dashed border-line text-navy-400 transition hover:border-gold-300 hover:text-gold-600" title="Add day">+</button>
            </div>

            @if ($day)
                @php $dayUnconfirmed = $day->sessions->reject(fn ($s) => $s->isSettled())->count(); @endphp
                <div class="flex items-center gap-2 text-xs">
                    <span class="text-micro text-muted"><b class="text-navy-900">{{ $day->sessions->count() }}</b> {{ \Illuminate\Support\Str::plural('session', $day->sessions->count()) }} this day</span>
                    @if ($dayUnconfirmed > 0)
                        <button type="button" wire:click="confirmDay"
                                wire:confirm="Confirm all {{ $dayUnconfirmed }} unconfirmed {{ \Illuminate\Support\Str::plural('session', $dayUnconfirmed) }} on this day?"
                                class="rounded-lg border border-gold-300 bg-gold-50 px-2.5 py-1.5 font-semibold text-gold-800 transition hover:bg-gold-100"
                                title="{{ $dayUnconfirmed }} session(s) still unconfirmed">✓ Confirm day ({{ $dayUnconfirmed }})</button>
                    @endif
                    <button type="button" wire:click="duplicateDay({{ $day->id }})" class="btn-ghost btn-xs">Copy day →</button>
                </div>
            @endif
        </div>

        {{-- Conflict banner --}}
        @php $conflictCount = collect($conflicts)->count(); @endphp
        @if ($conflictCount > 0)
            <div class="mb-4 rounded-2xl border border-risk/30 bg-risk/5 px-4 py-3">
                <p class="flex items-center gap-2 text-xs font-semibold text-red-700">
                    <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-risk/10">⚠</span>
                    {{ $conflictCount }} scheduling {{ str('conflict')->plural($conflictCount) }} detected on this day
                </p>
                <ul class="mt-1.5 space-y-0.5 pl-8 text-micro text-red-600">
                    @foreach (collect($conflicts)->flatten() as $reason)<li>• {{ $reason }}</li>@endforeach
                </ul>
            </div>
        @endif

        {{-- ══ Programme board ══ --}}
        @if ($view === 'program')
            <div class="card overflow-hidden p-5">
                <x-agenda-program :days="$programDays" :legend="false" />
            </div>
        @else

        {{-- ══ Timeline ══ --}}
        <div class="card overflow-hidden">
            @if ($timeline)
                {{-- Legend --}}
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 pt-3.5">
                    <span class="text-eyebrow font-bold uppercase tracking-[0.14em] text-navy-300">Type</span>
                    @foreach ($legend as [$label, $hex])
                        <span class="flex items-center gap-1.5 text-micro font-medium text-navy-700"><span class="h-3 w-4 rounded" style="background: {{ $hex }}"></span> {{ $label }}</span>
                    @endforeach
                    <span class="ml-auto hidden items-center gap-1.5 text-eyebrow text-muted sm:flex">✥ Drag a block to reschedule · hover for 🚩 / ✕ · click to edit</span>
                </div>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 border-b border-line/60 px-5 pb-2.5 pt-2">
                    <span class="text-eyebrow font-bold uppercase tracking-[0.14em] text-navy-300">Status</span>
                    @foreach (\App\Models\EventAgendaSession::STATUS_META as [$slbl, $ssettled, $shex])
                        <span class="flex items-center gap-1.5 text-eyebrow font-medium text-navy-600"><span class="h-2 w-2 rounded-full" style="background: {{ $shex }}"></span>{{ $slbl }}</span>
                    @endforeach
                    <span class="text-eyebrow italic text-navy-300">dashed outline = not yet confirmed</span>
                </div>

                <div class="overflow-x-auto p-3">
                    <div class="min-w-[820px]" data-agenda-timeline data-start-min="{{ $timeline['startMin'] }}" data-span-min="{{ $timeline['span'] }}">
                        <div class="relative ml-[150px] h-7 border-b border-line">
                            @foreach ($timeline['hours'] as $hour)
                                <span class="absolute top-1.5 -translate-x-1/2 text-eyebrow font-semibold text-navy-300" style="left: {{ $hour['left'] }}%">{{ $hour['label'] }}</span>
                            @endforeach
                        </div>
                        @foreach ($timeline['lanes'] as $lane)
                            <div class="flex items-stretch border-b border-line last:border-b-0">
                                <div class="flex w-[150px] shrink-0 flex-col justify-center gap-0.5 px-3 py-4">
                                    <p class="flex items-center gap-1.5 text-xs font-bold text-navy-900"><x-icon name="pin" class="h-3.5 w-3.5 text-navy-300" /> {{ $lane['room'] }}</p>
                                    <p class="pl-5 text-eyebrow text-muted">{{ $lane['blocks']->count() }} {{ str('session')->plural($lane['blocks']->count()) }}</p>
                                </div>
                                <div class="relative flex-1" data-room-track data-room-id="{{ $lane['room_id'] }}">
                                    @foreach ($timeline['hours'] as $hour)
                                        <span class="absolute inset-y-0 w-px bg-line/70" style="left: {{ $hour['left'] }}%"></span>
                                    @endforeach
                                    <div class="relative py-3" style="min-height: 66px">
                                        @foreach ($lane['blocks'] as $b)
                                            @php $hasConflict = isset($conflicts[$b['session']->id]); $sess = $b['session']; @endphp
                                            <div wire:key="blk-{{ $sess->id }}"
                                                 class="agenda-block group/blk absolute top-3 flex h-[46px] cursor-grab touch-none select-none flex-col justify-center rounded-xl px-2.5 text-left text-white shadow-sm transition hover:brightness-95 hover:ring-2 hover:ring-white/40 {{ $hasConflict ? 'ring-2 ring-risk ring-offset-1' : '' }}"
                                                 data-session-id="{{ $sess->id }}" data-start-min="{{ $b['startMin'] }}" data-dur-min="{{ $b['durMin'] }}" data-room-id="{{ $lane['room_id'] }}"
                                                 style="left: {{ $b['left'] }}%; width: {{ $b['width'] }}%; background: {{ $b['hex'] }}{{ $sess->isSettled() ? '' : '; outline: 2px dashed rgba(255,255,255,.55); outline-offset: -3px' }}"
                                                 title="{{ $sess->title }} · {{ substr($sess->starts_at, 0, 5) }}–{{ substr($sess->ends_at, 0, 5) }} · {{ $sess->statusLabel() }} — drag to reschedule, click to edit">
                                                <span class="pointer-events-none flex items-center gap-1 overflow-hidden text-micro font-bold leading-tight">
                                                    @if ($sess->flagged)<span class="shrink-0">🚩</span>@endif
                                                    <span class="truncate">{{ $sess->title }}</span>
                                                </span>
                                                <span class="pointer-events-none flex items-center gap-1.5 overflow-hidden text-eyebrow text-white/80">
                                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full ring-1 ring-white/60" style="background: {{ $sess->statusHex() }}" title="{{ $sess->statusLabel() }}"></span>
                                                    <span class="truncate">{{ substr($sess->starts_at, 0, 5) }}–{{ substr($sess->ends_at, 0, 5) }}</span>
                                                </span>

                                                {{-- hover quick actions --}}
                                                <span class="absolute -top-2.5 right-1 z-10 hidden items-center gap-1 group-hover/blk:flex">
                                                    <button type="button" data-block-action wire:click.stop="toggleFlag({{ $sess->id }})"
                                                            class="flex h-5 w-5 items-center justify-center rounded-md bg-white/95 text-eyebrow shadow ring-1 ring-black/5 transition hover:bg-white {{ $sess->flagged ? '' : 'grayscale' }}"
                                                            title="{{ $sess->flagged ? 'Remove flag' : 'Flag' }}">🚩</button>
                                                    <button type="button" data-block-action wire:click.stop="deleteSession({{ $sess->id }})" wire:confirm="Delete “{{ $sess->title }}”?"
                                                            class="flex h-5 w-5 items-center justify-center rounded-md bg-white/95 text-micro font-bold text-red-600 shadow ring-1 ring-black/5 transition hover:bg-white" title="Delete session">✕</button>
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="flex flex-col items-center px-8 py-16 text-center">
                    <h3 class="pf text-base font-bold text-navy-900">Nothing scheduled for this day</h3>
                    <p class="mt-1 max-w-md text-xs text-muted">Add a session and it'll plot on the timeline here.</p>
                    <button type="button" wire:click="newSession" class="btn-gold mt-4 h-9 px-4 text-xs">＋ Add Session</button>
                </div>
            @endif
        </div>
        @endif
    @endif

    {{-- ══════════ Add / Edit Session modal ══════════ --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit Session' : 'Add Session'" max="2xl" close="closeForm" flush wire:key="session-modal">
                <form wire:submit="saveSession">

                    <div class="space-y-4 px-6 py-5">
                        <div class="grid gap-4 sm:grid-cols-[1fr_auto]">
                            <div>
                                <label class="field-label !mb-1.5" for="m-title">Title <span class="text-risk">*</span></label>
                                <input id="m-title" type="text" wire:model="title" class="field !py-2.5" placeholder="Opening Keynote">
                                @error('title') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:w-44">
                                <label class="field-label !mb-1.5" for="m-type">Type</label>
                                <select id="m-type" wire:model="type" class="field !py-2.5">
                                    @foreach (\App\Models\EventAgendaSession::TYPES as $t)<option value="{{ $t }}">{{ str($t)->replace('_', ' ')->title() }}</option>@endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="field-label !mb-1.5">Format</label>
                            <div class="grid grid-cols-3 gap-2.5">
                                @foreach (\App\Models\EventAgendaSession::FORMATS as $val => $lbl)
                                    <button type="button" wire:click="$set('format', '{{ $val }}')"
                                            @class([
                                                'rounded-2xl border py-2.5 text-sm font-bold transition',
                                                'border-navy-900 bg-navy-900 text-white' => $format === $val,
                                                'border-line text-navy-500 hover:text-navy-800' => $format !== $val,
                                            ])>{{ $lbl }}</button>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label class="field-label !mb-1.5" for="m-status">Status</label>
                            <select id="m-status" wire:model="status" class="field !py-2.5">
                                @foreach (\App\Models\EventAgendaSession::STATUS_META as $val => [$lbl, $settled])
                                    <option value="{{ $val }}">{{ $lbl }}{{ $settled ? '' : ' — not for distribution' }}</option>
                                @endforeach
                            </select>
                            @error('status') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="field-label !mb-1.5" for="m-cap">Seat capacity <span class="font-normal normal-case tracking-normal text-navy-300">optional — limits attendee sign-ups</span></label>
                            <input id="m-cap" type="number" min="0" wire:model="capacity" class="field !py-2.5" placeholder="Leave blank for unlimited">
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="field-label !mb-1.5" for="m-day">Day <span class="text-risk">*</span></label>
                                <select id="m-day" wire:model="agenda_day_id" class="field !py-2.5">
                                    @foreach ($days as $d)<option value="{{ $d->id }}">Day {{ $loop->iteration }} — {{ $d->date?->format('D j M') }}</option>@endforeach
                                </select>
                            </div>
                            <div>
                                <label class="field-label !mb-1.5" for="m-start">Start <span class="text-risk">*</span></label>
                                <input id="m-start" type="time" wire:model="starts_at" class="field !py-2.5">
                            </div>
                            <div>
                                <label class="field-label !mb-1.5" for="m-end">End <span class="text-risk">*</span></label>
                                <input id="m-end" type="time" wire:model="ends_at" class="field !py-2.5">
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <div class="mb-1.5 flex items-center justify-between">
                                    <label class="field-label !mb-0">Speaker line-up</label>
                                    <span class="text-eyebrow text-muted">Pick from the roster or type a new name — new people are added to Speakers automatically</span>
                                </div>

                                <datalist id="speaker-roster">
                                    @foreach ($speakerOptions as $sp)<option value="{{ $sp }}"></option>@endforeach
                                </datalist>

                                <div class="space-y-2">
                                    @forelse ($speakerRows as $i => $row)
                                        <div class="flex items-center gap-2" wire:key="spk-{{ $i }}">
                                            <input type="text" list="speaker-roster" wire:model.blur="speakerRows.{{ $i }}.name"
                                                   class="field !py-2.5 flex-1" placeholder="Speaker name">
                                            <select wire:model="speakerRows.{{ $i }}.role" class="field !py-2.5 w-40 shrink-0">
                                                @foreach ($roles as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <button type="button" wire:click="removeSpeakerRow({{ $i }})"
                                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-navy-300 transition hover:bg-red-50 hover:text-red-600"
                                                    title="Remove">✕</button>
                                        </div>
                                    @empty
                                        <p class="rounded-xl border border-dashed border-line px-3 py-3 text-center text-micro text-muted">
                                            No speakers billed yet — a panel can carry a moderator plus several panellists.
                                        </p>
                                    @endforelse
                                </div>

                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button type="button" wire:click="addSpeakerRow('keynote')" class="rounded-lg border border-line bg-white px-2.5 py-1.5 text-micro font-semibold text-navy-700 transition hover:border-gold-300">＋ Keynote</button>
                                    <button type="button" wire:click="addSpeakerRow('moderator')" class="rounded-lg border border-line bg-white px-2.5 py-1.5 text-micro font-semibold text-navy-700 transition hover:border-gold-300">＋ Moderator</button>
                                    <button type="button" wire:click="addSpeakerRow('panelist')" class="rounded-lg border border-line bg-white px-2.5 py-1.5 text-micro font-semibold text-navy-700 transition hover:border-gold-300">＋ Panellist</button>
                                </div>
                            </div>
                            <div>
                                <label class="field-label !mb-1.5" for="m-room">Venue / Room</label>
                                <select id="m-room" wire:model="room_id" class="field mb-2 !py-2.5">
                                    <option value="">— Select a venue —</option>
                                    @foreach ($rooms as $room)<option value="{{ $room->id }}">{{ $room->name }}</option>@endforeach
                                </select>
                                <input type="text" wire:model="newRoomName" class="field !py-2.5" placeholder="…or type a room">
                            </div>
                        </div>

                        <div>
                            <label class="field-label !mb-1.5" for="m-desc">Description / Notes</label>
                            <textarea id="m-desc" wire:model="description" rows="2" class="field !py-2.5" placeholder="Optional details…"></textarea>
                        </div>
                    </div>

                    {{-- footer --}}
                    <div class="flex items-center justify-end gap-3 rounded-b-[24px] border-t border-line bg-page/60 px-6 py-4">
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-navy-700">
                            <input type="checkbox" wire:model="flagged" class="h-4 w-4 rounded border-line text-gold-500 focus:ring-gold-400">
                            <span>🚩 Flag</span>
                        </label>
                        @if ($editingId)
                            <button type="button" wire:click="deleteSession({{ $editingId }})" wire:confirm="Delete this session?" class="rounded-2xl px-4 py-2.5 text-sm font-bold text-risk transition hover:bg-risk/5">Delete</button>
                        @endif
                        <span class="mr-auto"></span>
                        <button type="button" wire:click="closeForm" class="rounded-2xl bg-fill px-6 py-2.5 text-sm font-bold text-navy-700 transition hover:bg-line">Cancel</button>
                        <button type="submit" class="rounded-2xl bg-navy-900 px-7 py-2.5 text-sm font-bold text-white shadow-[0_10px_25px_rgba(11,31,58,0.25)] transition hover:bg-navy-800">{{ $editingId ? 'Save Session' : 'Add Session' }}</button>
                    </div>
                </form>
        </x-modal>
    @endif

    @script
    <script>
        // Keep the newest component reference; bind global drag handlers only once.
        window.__agendaWire = $wire;
        if (! window.__agendaDragBound) {
            window.__agendaDragBound = true;
            let drag = null;
            const pad = n => String(n).padStart(2, '0');
            const fmt = m => pad(Math.floor(m / 60)) + ':' + pad(((m % 60) + 60) % 60);

            document.addEventListener('pointerdown', e => {
                const block = e.target.closest('.agenda-block');
                if (! block || e.button !== 0 || e.target.closest('[data-block-action]')) return;
                const tl = block.closest('[data-agenda-timeline]');
                const track = block.closest('[data-room-track]');
                if (! tl || ! track) return;
                drag = {
                    block, id: +block.dataset.sessionId,
                    startMin: +block.dataset.startMin, durMin: +block.dataset.durMin,
                    span: +tl.dataset.spanMin, base: +tl.dataset.startMin,
                    trackW: track.getBoundingClientRect().width,
                    x: e.clientX, y: e.clientY, moved: false,
                    newStart: +block.dataset.startMin, roomId: block.dataset.roomId || '',
                };
                e.preventDefault();
            });

            document.addEventListener('pointermove', e => {
                if (! drag) return;
                const dx = e.clientX - drag.x, dy = e.clientY - drag.y;
                if (! drag.moved && Math.abs(dx) < 4 && Math.abs(dy) < 4) return;
                drag.moved = true;
                drag.block.style.opacity = '0.8';
                drag.block.style.cursor = 'grabbing';
                drag.block.style.pointerEvents = 'none'; // let elementFromPoint see the lane beneath

                const pxPerMin = drag.trackW / drag.span;
                let ns = drag.startMin + Math.round((dx / pxPerMin) / 5) * 5;
                ns = Math.max(0, Math.min(ns, 1440 - drag.durMin));
                drag.newStart = ns;
                drag.block.style.left = ((ns - drag.base) / drag.span * 100) + '%';

                const lane = document.elementFromPoint(e.clientX, e.clientY)?.closest('[data-room-track]');
                if (lane) drag.roomId = lane.dataset.roomId || '';
                drag.block.title = fmt(ns) + '–' + fmt(ns + drag.durMin);
            });

            window.addEventListener('pointerup', () => {
                if (! drag) return;
                const d = drag; drag = null;
                d.block.style.opacity = ''; d.block.style.cursor = ''; d.block.style.pointerEvents = '';
                const wire = window.__agendaWire;
                if (! d.moved) { wire.editSession(d.id); return; }
                wire.moveSession(d.id, d.newStart, d.roomId === '' ? null : +d.roomId);
            });
        }
    </script>
    @endscript
</div>
