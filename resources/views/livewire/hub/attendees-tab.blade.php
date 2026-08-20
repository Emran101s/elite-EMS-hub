<div>
    @if ($checkinMode)
        {{-- ══════════ Check-in mode: the door on show day ══════════ --}}
        <div class="relative overflow-hidden rounded-[22px] bg-gradient-to-br from-eo-navy to-eo-navy-deep text-white shadow-eo-float mb-4 flex flex-wrap items-center gap-x-8 gap-y-4 px-6 py-5">
            <div class="pointer-events-none absolute -right-10 -top-20 h-56 w-56 rounded-full bg-[radial-gradient(circle,rgba(30,172,172,0.22),transparent_70%)]"></div>
            <div class="relative">
                <p class="text-eyebrow font-bold uppercase tracking-[0.28em] text-eo-teal-lit">Check-in · {{ $event->name }}</p>
                <p class="mt-0.5 flex items-baseline gap-2">
                    <span class="text-4xl font-black leading-none text-eo-teal-lit">{{ $stats['checkedIn'] }}</span>
                    <span class="text-sm font-semibold text-white">of {{ $stats['confirmed'] }} arrived</span>
                </p>
                <p class="mt-1 text-eyebrow text-white/50">{{ $lastHour }} in the last hour</p>
            </div>
            <div class="relative h-2 min-w-[10rem] flex-1 self-center overflow-hidden rounded-full bg-white/10">
                <div class="h-full rounded-full bg-eo-teal" style="width: {{ $stats['confirmed'] ? (int) round($stats['checkedIn'] / $stats['confirmed'] * 100) : 0 }}%"></div>
            </div>
            <button type="button" wire:click="toggleCheckinMode" class="eo-btn-ghost btn-sm relative">Exit check-in</button>
        </div>

        {{-- The queue --}}
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <input type="search" wire:model.live.debounce.200ms="search" placeholder="Type a name, email or company…"
                   class="eo-input h-12 flex-1 text-base" autofocus>
        </div>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_280px]">
            <div class="eo-soft-card divide-y divide-eo-line px-4">
                @forelse ($doorList->take(30) as $a)
                    @php $in = $a->status === 'checked_in'; @endphp
                    <div wire:key="door-{{ $a->id }}" class="flex items-center gap-3 py-3">
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2">
                                <span class="truncate text-sm font-bold {{ $in ? 'text-eo-muted line-through decoration-navy-200' : 'text-eo-text' }}">{{ $a->name }}</span>
                                @if ($a->vip)<span class="chip-gold shrink-0">VIP</span>@endif
                            </span>
                            <span class="block truncate text-micro text-eo-muted">{{ $a->organization ?: $a->email }}{{ $a->ticket_type ? ' · '.$a->ticket_type : '' }}</span>
                        </span>
                        @if ($in)
                            <span class="shrink-0 text-micro text-eo-muted">{{ $a->checked_in_at?->format('H:i') }}</span>
                            <button type="button" wire:click="toggleCheckIn({{ $a->id }})" class="eo-btn-ghost btn-xs shrink-0">↺ Undo</button>
                        @else
                            <button type="button" wire:click="toggleCheckIn({{ $a->id }})" class="eo-btn-primary h-11 shrink-0 px-6 text-sm">✓ Check in</button>
                        @endif
                    </div>
                @empty
                    <p class="py-8 text-center text-xs text-eo-muted">
                        {{ $search !== '' ? 'Nobody matches — register them as a walk-in →' : 'No attendees yet.' }}
                    </p>
                @endforelse
                @if ($doorList->count() > 30)
                    <p class="py-2 text-center text-micro text-eo-muted">Showing 30 of {{ $doorList->count() }} — keep typing to narrow the queue.</p>
                @endif
            </div>

            {{-- Walk-in --}}
            <div class="eo-soft-card h-fit p-4 xl:sticky xl:top-12">
                <p class="eo-label !mb-2">Walk-in registration</p>
                <form wire:submit="walkIn" class="space-y-2">
                    <input type="text" wire:model="walkinName" class="eo-input h-10 text-sm" placeholder="Full name">
                    @error('walkinName') <p class="text-micro text-eo-risk-ink">{{ $message }}</p> @enderror
                    <input type="text" wire:model="walkinOrg" class="eo-input h-10 text-sm" placeholder="Organization (optional)">
                    <button type="submit" class="eo-btn-navy h-10 w-full text-xs">＋ Register &amp; check in</button>
                </form>
                <p class="mt-3 text-micro leading-relaxed text-eo-muted">They're added with a <b>Walk-in</b> ticket and counted as arrived immediately.</p>
            </div>
        </div>
    @else
    {{-- Registered / Checked in / VIPs / Revenue already live in the
         Universal Module Header above this — the stat strip that used to
         repeat them here is gone rather than kept as a second copy. --}}

    {{-- ══ Ticket-type breakdown ══ --}}
    @if ($byTicket->isNotEmpty())
        <div class="mb-4 flex flex-wrap gap-2">
            @foreach ($byTicket as $type => $count)
                <button type="button" wire:click="$set('filterTicket', '{{ $filterTicket === $type ? '' : $type }}')"
                        class="flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition {{ $filterTicket === $type ? 'border-eo-navy bg-eo-navy text-white' : 'border-eo-line bg-white text-eo-text hover:border-eo-teal' }}">
                    {{ $type }} <span class="rounded-full {{ $filterTicket === $type ? 'bg-white/20' : 'bg-eo-bg text-eo-muted' }} px-1.5 text-eyebrow">{{ $count }}</span>
                </button>
            @endforeach
        </div>
    @endif

    {{-- ══ Toolbar ══ --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name, email, org…" class="eo-input h-10 w-56 text-sm">
        <select wire:model.live="filterStatus" class="eo-input h-10 w-40 text-sm">
            <option value="">All statuses</option>
            @foreach (\App\Models\EventAttendee::STATUS_META as $val => $meta)<option value="{{ $val }}">{{ $meta[0] }}</option>@endforeach
        </select>
        <div class="ml-auto flex items-center gap-2">
            <button type="button" wire:click="toggleCheckinMode" class="flex h-10 items-center gap-1.5 rounded-xl border border-eo-teal/30 bg-eo-teal-soft px-3 text-xs font-bold text-eo-teal-deep transition hover:bg-eo-teal-soft/70" title="Full-screen arrival flow for show day">✓ Check-in mode</button>
            {{-- Show day: a screen for somebody standing at the door. --}}
            <a href="{{ route('events.arrivals', $event) }}"
               class="inline-flex h-9 items-center gap-2 rounded-xl bg-eo-navy px-3.5 text-xs font-bold text-white transition hover:bg-eo-navy-mid"
               title="Find and admit people who arrive without a badge">
                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400"></span> Arrivals desk
            </a>

            <button type="button" wire:click="$toggle('showRegistration')"
                    @class(['flex h-10 items-center gap-1.5 rounded-xl border px-3 text-xs font-semibold transition',
                            'border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100' => $event->registration_open,
                            'border-eo-line bg-white text-eo-text hover:border-eo-teal' => ! $event->registration_open])>
                <span class="h-1.5 w-1.5 rounded-full {{ $event->registration_open ? 'bg-emerald-500' : 'bg-eo-line' }}"></span>
                {{ $event->registration_open ? 'Registration open' : 'Registration closed' }}
            </button>
            <button type="button" wire:click="$toggle('showBadge')" class="flex h-10 items-center gap-1.5 rounded-xl border border-eo-line bg-white px-3 text-xs font-semibold text-eo-text transition hover:border-eo-teal">▣ Badges</button>
            <button type="button" wire:click="$toggle('showImport')" class="flex h-10 items-center gap-1.5 rounded-xl border border-eo-line bg-white px-3 text-xs font-semibold text-eo-text transition hover:border-eo-teal">⇪ Import</button>
            <button type="button" wire:click="newItem" class="eo-btn-navy h-10 gap-1.5 px-4 text-xs"><span class="text-eo-teal-lit">＋</span> Add attendee</button>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50/60 px-4 py-2 text-xs font-semibold text-emerald-800">{{ session('status') }}</div>
    @endif

    {{-- ══════════ Badge design ══════════ --}}
    @if ($showBadge)
        @include('events.partials.badge-css')
        <div class="eo-soft-card mb-4 p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <h3 class="text-base font-bold text-eo-text">Badge design</h3>
                    <p class="mt-0.5 max-w-[56ch] text-xs text-eo-muted">
                        The preview is the badge — the same markup prints, so what you see here is what comes
                        off the printer. The QR opens a check-in page for that person on any phone camera.
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <a href="{{ route('events.badges.pdf', $event) }}" target="_blank" class="eo-btn-ghost btn-sm">Print all ↗</a>
                    @if ($this->selectedCount())
                        <a href="{{ route('events.badges.pdf', [$event, 'ids' => implode(',', $selected)]) }}" target="_blank" class="eo-btn-navy btn-sm">
                            Print {{ $this->selectedCount() }} selected ↗
                        </a>
                    @endif
                    <button type="button" wire:click="saveBadge" class="eo-btn-primary btn-sm">Save</button>
                </div>
            </div>

            <div class="mt-4 grid gap-5 lg:grid-cols-[minmax(0,260px)_minmax(0,1fr)]">
                {{-- ── the controls ── --}}
                <div class="space-y-3">
                    <label class="block">
                        <span class="eo-label !mb-1 !text-eyebrow">Size</span>
                        <select wire:model.live="badge.size" class="eo-input h-10 text-sm">
                            @foreach (\App\Support\Badge::SIZES as $key => [$label, $w, $h])
                                <option value="{{ $key }}">{{ $label }} — {{ $w }} × {{ $h }} mm</option>
                            @endforeach
                            <option value="{{ \App\Support\Badge::CUSTOM_SIZE }}">Custom size…</option>
                        </select>
                    </label>

                    @if (($badge['size'] ?? null) === \App\Support\Badge::CUSTOM_SIZE)
                        <div class="grid grid-cols-2 gap-2">
                            <label class="block">
                                <span class="eo-label !mb-1 !text-eyebrow">Width (mm)</span>
                                <input type="number" wire:model.live="badge.custom_width"
                                       min="{{ \App\Support\Badge::CUSTOM_MIN_MM }}" max="{{ \App\Support\Badge::CUSTOM_MAX_MM }}"
                                       class="eo-input h-10 text-sm">
                                @error('badge.custom_width')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                            </label>
                            <label class="block">
                                <span class="eo-label !mb-1 !text-eyebrow">Height (mm)</span>
                                <input type="number" wire:model.live="badge.custom_height"
                                       min="{{ \App\Support\Badge::CUSTOM_MIN_MM }}" max="{{ \App\Support\Badge::CUSTOM_MAX_MM }}"
                                       class="eo-input h-10 text-sm">
                                @error('badge.custom_height')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                            </label>
                        </div>
                    @endif

                    <label class="block">
                        <span class="eo-label !mb-1 !text-eyebrow">Accent</span>
                        <input type="color" wire:model.live="badge.accent" class="eo-input h-10 w-full p-1">
                        @error('badge.accent')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                        <span class="mt-1 block text-eyebrow text-eo-muted">Leave as the event's own accent unless the badge needs its own.</span>
                    </label>

                    <div class="space-y-1.5 rounded-xl border border-eo-line p-3">
                        <p class="eyebrow mb-1">Show on the badge</p>
                        @foreach ([
                            'show_logo' => 'Event logo',
                            'show_organisation' => 'Organisation',
                            'show_job_title' => 'Job title',
                            'show_ticket' => 'Ticket type',
                            'show_qr' => 'QR code',
                            'show_reference' => 'Reference',
                        ] as $key => $label)
                            <label class="flex cursor-pointer items-center gap-2 text-[12.5px] font-semibold text-eo-text">
                                <input type="checkbox" wire:model.live="badge.{{ $key }}" class="h-3.5 w-3.5 rounded border-eo-line text-eo-text focus:ring-eo-teal">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>

                    {{-- Anything else the form asked. A badge that can print
                         the workshop track is a badge somebody can be pointed
                         to a room by. --}}
                    @php $printable = $this->badgeFieldChoices(); @endphp
                    @if ($printable->isNotEmpty())
                        <div>
                            <span class="eo-label !mb-1 !text-eyebrow">Also print</span>
                            <div class="grid gap-1.5">
                                @foreach ($printable as $field)
                                    <label class="flex cursor-pointer items-center gap-2 text-[12.5px] font-semibold text-eo-text">
                                        <input type="checkbox" value="{{ $field->key }}" wire:model.live="badge.lines"
                                               class="h-3.5 w-3.5 rounded border-eo-line text-eo-text focus:ring-eo-teal">
                                        {{ $field->label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <label class="block">
                        <span class="eo-label !mb-1 !text-eyebrow">Footer line</span>
                        <input type="text" wire:model.live.debounce.400ms="badge.footer" maxlength="80" class="eo-input h-10 text-sm" placeholder="e.g. Wear at all times">
                        @error('badge.footer')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                    </label>

                    <x-confirm title="Put the badge design back to the default?"
                               confirm="Reset" tone="warn"
                               run="$wire.resetBadge()"
                               class="eo-btn-ghost btn-xs">Reset to default</x-confirm>
                </div>

                {{-- ── the preview ── --}}
                <div class="flex min-w-0 items-start justify-center rounded-2xl bg-eo-workspace/70 p-6">
                    @php $sample = $this->badgeSample(); @endphp
                    @include('events.partials.badge', [
                        'event' => $event,
                        'attendee' => $sample,
                        'template' => array_merge(\App\Support\Badge::DEFAULTS, $badge),
                        'qr' => ($badge['show_qr'] ?? true) && $sample->exists
                            ? \App\Support\Badge::qr(\App\Support\Badge::checkInUrl($sample))
                            : null,
                    ])
                </div>
            </div>
        </div>
    @endif

    {{-- ══════════ Public registration ══════════ --}}
    @if ($showRegistration)
        <div class="eo-soft-card mb-4 bg-white/90 p-5 backdrop-blur-xl">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <h3 class="text-base font-bold text-eo-text">Public registration</h3>
                    <p class="mt-0.5 max-w-[56ch] text-xs text-eo-muted">
                        Share this link and people fill the form themselves. Everyone who registers lands in
                        this list, ready for a badge — nobody retypes anything.
                    </p>
                </div>
                <button type="button" wire:click="toggleRegistration"
                        class="{{ $event->registration_open ? 'eo-btn-ghost' : 'eo-btn-primary' }} btn-sm shrink-0">
                    {{ $event->registration_open ? 'Close registration' : 'Open registration' }}
                </button>
            </div>

            {{-- The link. Selectable rather than behind a copy button that may
                 not have clipboard permission on an unsecured origin. --}}
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <input type="text" readonly value="{{ $event->registrationUrl() }}"
                       onfocus="this.select()"
                       class="eo-input h-10 min-w-0 flex-1 bg-eo-workspace/60 font-mono text-[12px] text-eo-muted">
                <a href="{{ $event->registrationUrl() }}" target="_blank" class="eo-btn-ghost btn-sm shrink-0">Open ↗</a>
                <x-confirm title="Make a new link?"
                        body="The current one stops working immediately. Everyone who has already registered keeps their place."
                        confirm="New link" tone="warn"
                        run="$wire.newRegistrationLink()"
                        class="eo-btn-ghost btn-sm shrink-0">New link</x-confirm>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-[160px_minmax(0,1fr)]">
                <label class="block">
                    <span class="eo-label !mb-1 !text-eyebrow">Capacity</span>
                    <input type="number" min="1" wire:model="registrationCapacity" class="eo-input h-10 text-sm" placeholder="no limit">
                    @error('registrationCapacity')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                </label>
                <label class="block">
                    <span class="eo-label !mb-1 !text-eyebrow">What they should know</span>
                    <input type="text" wire:model="registrationNote" class="eo-input h-10 text-sm" placeholder="e.g. Doors open at 08:30. Please bring photo ID.">
                </label>
            </div>

            <div class="mt-3 flex items-center justify-between gap-3">
                {{-- Note the space before every @if: Blade will not recognise a
                     directive glued to a word character, but it will recognise
                     the @endif, which then closes the wrong block. --}}
                <p class="text-eyebrow text-eo-muted">
                    {{ $stats['registered'] }} registered
                    @if ($event->registration_capacity) of {{ $event->registration_capacity }} @endif
                    @if ($event->registrationIsFull())
                        · <span class="font-bold text-warn">Full — the form is refusing new names.</span>
                    @endif
                </p>
                <button type="button" wire:click="saveRegistrationSettings" class="eo-btn-navy btn-sm">Save</button>
            </div>
        </div>

        {{-- The questions the form asks. Here rather than on a screen of its
             own, because "what does the form ask" and "is the form open" are
             the same thought. --}}
        <div class="mb-4">
            <livewire:hub.registration-form :event="$event" :key="'regform-'.$event->id" />
        </div>
    @endif

    @if ($showImport)
        <form wire:submit="import" class="eo-soft-card mb-4 flex flex-wrap items-end gap-3 p-4">
            <div class="flex-1">
                {{-- The columns are this event's own questions now, so naming
                     a fixed set here would be a lie the moment somebody adds
                     one. The template is the answer. --}}
                <label class="eo-label !mb-1 !text-eyebrow" for="att-import">
                    Excel (.xlsx) or CSV — the columns are
                    <a href="{{ route('events.attendees.template', $event) }}" class="font-semibold text-eo-teal-ink hover:underline">this event's questions</a>,
                    plus amount and VIP · re-importing updates by email
                </label>
                <input id="att-import" type="file" wire:model="importFile" accept=".xlsx,.xls,.csv,text/csv" class="eo-input h-10 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-eo-navy file:px-3 file:py-1 file:text-xs file:font-semibold file:text-white">
                @error('importFile') <p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p> @enderror
            </div>
            <a href="{{ route('events.attendees.template', $event) }}" class="h-10 rounded-xl border border-eo-teal/30 bg-eo-teal-soft px-3.5 text-xs font-bold leading-10 text-eo-teal-deep transition hover:bg-eo-teal-soft/70" title="Download a ready-to-fill Excel template">↧ Template</a>
            <button type="button" wire:click="$set('showImport', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-eo-muted hover:text-eo-text">Cancel</button>
            <button type="submit" class="eo-btn-navy h-10 px-5 text-xs" wire:loading.attr="disabled" wire:target="import,importFile">Import</button>
        </form>
    @endif

    {{-- ══ Table ══ --}}
    <div class="eo-soft-card overflow-x-auto">
        <table class="w-full min-w-[760px]">
            <thead>
                <tr class="border-b border-eo-line bg-eo-workspace/40 text-left text-eyebrow font-bold uppercase tracking-wide text-eo-muted">
                    <th class="px-4 py-2.5">Attendee</th>
                    <th class="px-3 py-2.5">Ticket</th>
                    <th class="px-3 py-2.5">Status</th>
                    <th class="px-3 py-2.5 text-right">Fee</th>
                    <th class="px-3 py-2.5 text-center">Check-in</th>
                    <th class="px-3 py-2.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($attendees as $a)
                    @php [$sl, $sc] = $a->statusMeta(); @endphp
                    <tr wire:key="att-{{ $a->id }}" wire:click="edit({{ $a->id }})" class="group cursor-pointer border-b border-eo-line last:border-0 hover:bg-eo-workspace/30">
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-eo-navy text-eyebrow font-bold text-white">{{ $a->initials() }}</span>
                                <div class="min-w-0">
                                    <p class="flex items-center gap-1.5 truncate text-sm font-bold text-eo-text">{{ $a->name }}@if ($a->vip)<span class="text-gold-700" title="VIP">★</span>@endif</p>
                                    <p class="truncate text-micro text-eo-muted">{{ $a->organization ?: $a->email ?: '—' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-2.5"><span class="rounded-full bg-eo-bg px-2.5 py-0.5 text-eyebrow font-bold text-eo-muted">{{ $a->ticket_type }}</span></td>
                        <td class="px-3 py-2.5"><span class="rounded-full px-2.5 py-0.5 text-eyebrow font-bold {{ $sc }}">{{ $sl }}</span></td>
                        <td class="px-3 py-2.5 text-right text-xs font-semibold text-eo-text">{{ $a->amount_cents ? $event->money($a->amount_cents) : '—' }}</td>
                        <td class="px-3 py-2.5 text-center">
                            <button type="button" wire:click.stop="toggleCheckIn({{ $a->id }})"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-sm transition {{ $a->status === 'checked_in' ? 'bg-emerald-100 text-emerald-700' : 'bg-eo-bg text-eo-muted hover:bg-eo-bg hover:text-eo-muted' }}"
                                    title="{{ $a->status === 'checked_in' ? 'Undo check-in' : 'Check in' }}">✓</button>
                        </td>
                        <td class="px-3 py-2.5">
                            <div class="flex items-center justify-end gap-1">
                                <span class="text-eyebrow font-semibold text-eo-muted opacity-100 transition sm:opacity-0 sm:group-hover:opacity-100">Edit ✎</span>
                                <x-confirm title="Remove {{ $a->name }}?"
                                           confirm="Remove"
                                           run="$wire.delete({{ $a->id }})"
                                           class="rounded-lg bg-eo-risk/10 px-2 py-1 text-eyebrow font-bold text-red-700 opacity-100 transition sm:opacity-0 hover:bg-eo-risk/20 sm:group-hover:opacity-100">✕</x-confirm>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-16 text-center">
                        <p class="text-sm font-semibold text-eo-text">{{ $search || $filterStatus || $filterTicket ? 'No attendees match your filters' : 'No attendees yet' }}</p>
                        @unless ($search || $filterStatus || $filterTicket)
                            <p class="mx-auto mt-1 max-w-md text-xs text-eo-muted">Add attendees one by one, or import your registration list from Excel.</p>
                            <div class="mt-4 flex justify-center gap-2">
                                <button type="button" wire:click="newItem" class="eo-btn-navy h-10 px-5 text-xs">＋ Add attendee</button>
                                <button type="button" wire:click="$set('showImport', true)" class="h-10 rounded-xl border border-eo-line bg-white px-4 text-xs font-semibold text-eo-text hover:border-eo-teal">⇪ Import list</button>
                            </div>
                        @endunless
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-center text-eyebrow text-eo-muted">{{ $attendees->count() }} shown</p>

    {{-- ══ Modal ══ --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit attendee' : 'Add attendee'" max="lg" close="set('showForm', false)">
                <form wire:submit="save" class="grid gap-3.5">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="eo-label !mb-1 !text-eyebrow">Full name</label>
                            <input type="text" wire:model="name" class="eo-input h-10 text-sm" placeholder="e.g. Layla Haddad">
                            @error('name')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="eo-label !mb-1 !text-eyebrow">Email</label>
                            <input type="email" wire:model="email" class="eo-input h-10 text-sm" placeholder="name@company.com">
                            @error('email')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="eo-label !mb-1 !text-eyebrow">Organization</label>
                            <input type="text" wire:model="organization" class="eo-input h-10 text-sm">
                        </div>
                        <div>
                            <label class="eo-label !mb-1 !text-eyebrow">Phone</label>
                            <input type="text" wire:model="phone" class="eo-input h-10 text-sm">
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="eo-label !mb-1 !text-eyebrow">Ticket type</label>
                            <select wire:model="ticket_type" class="eo-input h-10 text-sm">
                                @foreach ($ticketTypes as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="eo-label !mb-1 !text-eyebrow">Status</label>
                            <select wire:model="status" class="eo-input h-10 text-sm">
                                @foreach (\App\Models\EventAttendee::STATUS_META as $val => $meta)<option value="{{ $val }}">{{ $meta[0] }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="eo-label !mb-1 !text-eyebrow">Fee ({{ $event->currencySymbol() }})</label>
                            <input type="number" step="0.01" min="0" wire:model="amount" class="eo-input h-10 text-sm" placeholder="0">
                            @error('amount')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex items-center gap-2 text-xs font-semibold text-eo-text"><input type="checkbox" wire:model="vip" class="h-4 w-4 rounded border-eo-line text-gold-700 focus:ring-gold-400"> ★ VIP</label>
                        <div>
                            <label class="eo-label !mb-1 !text-eyebrow">Dietary / access</label>
                            <input type="text" wire:model="dietary" class="eo-input h-9 text-sm" placeholder="e.g. Vegetarian, wheelchair">
                        </div>
                    </div>
                    <div>
                        <label class="eo-label !mb-1 !text-eyebrow">Notes</label>
                        <textarea wire:model="notes" rows="2" class="eo-input text-sm"></textarea>
                    </div>
                    <div class="mt-1 flex items-center gap-3">
                        @if ($editingId)
                            <x-confirm title="Remove this attendee?"
                                       confirm="Remove"
                                       run="$wire.delete({{ $editingId }})"
                                       class="rounded-xl px-3 py-2 text-xs font-bold text-eo-risk-ink transition hover:bg-eo-risk/5">Delete</x-confirm>
                        @endif
                        <div class="ml-auto flex gap-2">
                            <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-eo-muted hover:text-eo-text">Cancel</button>
                            <button type="submit" class="eo-btn-navy h-10 px-6 text-xs">{{ $editingId ? 'Save' : 'Add attendee' }}</button>
                        </div>
                    </div>
                </form>
        </x-modal>
    @endif

    <x-bulk-bar :count="$this->selectedCount()" noun="attendee" />
    @endif
</div>
