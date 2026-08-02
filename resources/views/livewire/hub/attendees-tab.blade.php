<div>
    @if ($checkinMode)
        {{-- ══════════ Check-in mode: the door on show day ══════════ --}}
        <div class="strip-dark mb-4 flex flex-wrap items-center gap-x-8 gap-y-4 px-6 py-5">
            <div class="pointer-events-none absolute -right-10 -top-20 h-56 w-56 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.28),transparent_70%)]"></div>
            <div class="relative">
                <p class="text-eyebrow font-bold uppercase tracking-[0.28em] text-gold-300">Check-in · {{ $event->name }}</p>
                <p class="mt-0.5 flex items-baseline gap-2">
                    <span class="text-4xl font-black leading-none text-gold-400">{{ $stats['checkedIn'] }}</span>
                    <span class="text-sm font-semibold text-white">of {{ $stats['confirmed'] }} arrived</span>
                </p>
                <p class="mt-1 text-eyebrow text-white/50">{{ $lastHour }} in the last hour</p>
            </div>
            <div class="relative h-2 min-w-[10rem] flex-1 self-center overflow-hidden rounded-full bg-white/10">
                <div class="h-full rounded-full bg-gradient-to-r from-gold-400 to-gold-600" style="width: {{ $stats['confirmed'] ? (int) round($stats['checkedIn'] / $stats['confirmed'] * 100) : 0 }}%"></div>
            </div>
            <button type="button" wire:click="toggleCheckinMode" class="btn-ghost btn-sm relative">Exit check-in</button>
        </div>

        {{-- The queue --}}
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <input type="search" wire:model.live.debounce.200ms="search" placeholder="Type a name, email or company…"
                   class="input h-12 flex-1 text-base" autofocus>
        </div>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_280px]">
            <div class="card divide-y divide-line px-4">
                @forelse ($doorList->take(30) as $a)
                    @php $in = $a->status === 'checked_in'; @endphp
                    <div wire:key="door-{{ $a->id }}" class="flex items-center gap-3 py-3">
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2">
                                <span class="truncate text-sm font-bold {{ $in ? 'text-muted line-through decoration-navy-200' : 'text-navy-900' }}">{{ $a->name }}</span>
                                @if ($a->vip)<span class="chip-gold shrink-0">VIP</span>@endif
                            </span>
                            <span class="block truncate text-micro text-muted">{{ $a->organization ?: $a->email }}{{ $a->ticket_type ? ' · '.$a->ticket_type : '' }}</span>
                        </span>
                        @if ($in)
                            <span class="shrink-0 text-micro text-muted">{{ $a->checked_in_at?->format('H:i') }}</span>
                            <button type="button" wire:click="toggleCheckIn({{ $a->id }})" class="btn-ghost btn-xs shrink-0">↺ Undo</button>
                        @else
                            <button type="button" wire:click="toggleCheckIn({{ $a->id }})" class="btn-gold h-11 shrink-0 px-6 text-sm">✓ Check in</button>
                        @endif
                    </div>
                @empty
                    <p class="py-8 text-center text-xs text-muted">
                        {{ $search !== '' ? 'Nobody matches — register them as a walk-in →' : 'No attendees yet.' }}
                    </p>
                @endforelse
                @if ($doorList->count() > 30)
                    <p class="py-2 text-center text-micro text-muted">Showing 30 of {{ $doorList->count() }} — keep typing to narrow the queue.</p>
                @endif
            </div>

            {{-- Walk-in --}}
            <div class="card h-fit p-4 xl:sticky xl:top-12">
                <p class="field-label !mb-2">Walk-in registration</p>
                <form wire:submit="walkIn" class="space-y-2">
                    <input type="text" wire:model="walkinName" class="input h-10 text-sm" placeholder="Full name">
                    @error('walkinName') <p class="text-micro text-risk">{{ $message }}</p> @enderror
                    <input type="text" wire:model="walkinOrg" class="input h-10 text-sm" placeholder="Organization (optional)">
                    <button type="submit" class="btn-navy h-10 w-full text-xs">＋ Register &amp; check in</button>
                </form>
                <p class="mt-3 text-micro leading-relaxed text-muted">They're added with a <b>Walk-in</b> ticket and counted as arrived immediately.</p>
            </div>
        </div>
    @else
    {{-- ══ Stat tiles ══ --}}
    <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-line bg-white p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-navy-50 text-navy-500"><x-icon name="users" class="h-5 w-5" /></span>
                <div class="min-w-0">
                    <p class="pf text-2xl font-black leading-none text-navy-900">{{ number_format($stats['registered']) }}@if ($stats['capacity'])<span class="text-sm font-semibold text-muted"> / {{ number_format($stats['capacity']) }}</span>@endif</p>
                    <p class="mt-1 text-eyebrow font-bold uppercase tracking-wide text-muted">Registered</p>
                </div>
            </div>
            @if ($stats['fillPct'] !== null)
                <div class="mt-2.5 h-1.5 overflow-hidden rounded-full bg-page"><div class="h-full rounded-full bg-gold-400" style="width: {{ $stats['fillPct'] }}%"></div></div>
                <p class="mt-1 text-eyebrow text-muted">{{ $stats['fillPct'] }}% of capacity</p>
            @endif
        </div>
        <div class="flex items-center gap-3 rounded-2xl border border-line bg-white p-4 shadow-sm">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><x-icon name="clipboard" class="h-5 w-5" /></span>
            <div class="min-w-0">
                <p class="pf text-2xl font-black leading-none text-emerald-600">{{ number_format($stats['checkedIn']) }}</p>
                <p class="mt-1 text-eyebrow font-bold uppercase tracking-wide text-muted">Checked in · {{ number_format($stats['confirmed']) }} conf.</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-2xl border border-line bg-white p-4 shadow-sm">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gold-50 text-gold-600"><x-icon name="star" class="h-5 w-5" /></span>
            <div class="min-w-0">
                <p class="pf text-2xl font-black leading-none text-gold-600">{{ number_format($stats['vips']) }}</p>
                <p class="mt-1 text-eyebrow font-bold uppercase tracking-wide text-muted">VIPs · {{ number_format($stats['cancelled']) }} cancelled</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-2xl border border-line bg-white p-4 shadow-sm">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-navy-50 text-navy-500"><x-icon name="currency" class="h-5 w-5" /></span>
            <div class="min-w-0">
                <p class="pf text-2xl font-black leading-none text-navy-900">{{ $event->money($stats['revenue']) }}</p>
                <p class="mt-1 text-eyebrow font-bold uppercase tracking-wide text-muted">Ticket revenue</p>
            </div>
        </div>
    </div>

    {{-- ══ Ticket-type breakdown ══ --}}
    @if ($byTicket->isNotEmpty())
        <div class="mb-4 flex flex-wrap gap-2">
            @foreach ($byTicket as $type => $count)
                <button type="button" wire:click="$set('filterTicket', '{{ $filterTicket === $type ? '' : $type }}')"
                        class="flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition {{ $filterTicket === $type ? 'border-navy-900 bg-navy-900 text-white' : 'border-line bg-white text-navy-700 hover:border-gold-300' }}">
                    {{ $type }} <span class="rounded-full {{ $filterTicket === $type ? 'bg-white/20' : 'bg-navy-100 text-navy-600' }} px-1.5 text-eyebrow">{{ $count }}</span>
                </button>
            @endforeach
        </div>
    @endif

    {{-- ══ Toolbar ══ --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name, email, org…" class="input h-10 w-56 text-sm">
        <select wire:model.live="filterStatus" class="input h-10 w-40 text-sm">
            <option value="">All statuses</option>
            @foreach (\App\Models\EventAttendee::STATUS_META as $val => $meta)<option value="{{ $val }}">{{ $meta[0] }}</option>@endforeach
        </select>
        <div class="ml-auto flex items-center gap-2">
            <button type="button" wire:click="toggleCheckinMode" class="flex h-10 items-center gap-1.5 rounded-xl border border-gold-300 bg-gold-50 px-3 text-xs font-bold text-gold-800 transition hover:bg-gold-100" title="Full-screen arrival flow for show day">✓ Check-in mode</button>
            <button type="button" wire:click="$toggle('showRegistration')"
                    @class(['flex h-10 items-center gap-1.5 rounded-xl border px-3 text-xs font-semibold transition',
                            'border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100' => $event->registration_open,
                            'border-line bg-white text-navy-700 hover:border-gold-300' => ! $event->registration_open])>
                <span class="h-1.5 w-1.5 rounded-full {{ $event->registration_open ? 'bg-emerald-500' : 'bg-navy-300' }}"></span>
                {{ $event->registration_open ? 'Registration open' : 'Registration closed' }}
            </button>
            <button type="button" wire:click="$toggle('showBadge')" class="flex h-10 items-center gap-1.5 rounded-xl border border-line bg-white px-3 text-xs font-semibold text-navy-700 transition hover:border-gold-300">▣ Badges</button>
            <button type="button" wire:click="$toggle('showImport')" class="flex h-10 items-center gap-1.5 rounded-xl border border-line bg-white px-3 text-xs font-semibold text-navy-700 transition hover:border-gold-300">⇪ Import</button>
            <button type="button" wire:click="newItem" class="btn-navy h-10 gap-1.5 px-4 text-xs"><span class="text-gold-400">＋</span> Add attendee</button>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50/60 px-4 py-2 text-xs font-semibold text-emerald-800">{{ session('status') }}</div>
    @endif

    {{-- ══════════ Badge design ══════════ --}}
    @if ($showBadge)
        @include('events.partials.badge-css')
        <div class="card mb-4 p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <h3 class="pf text-base font-bold text-navy-900">Badge design</h3>
                    <p class="mt-0.5 max-w-[56ch] text-xs text-muted">
                        The preview is the badge — the same markup prints, so what you see here is what comes
                        off the printer. The QR opens a check-in page for that person on any phone camera.
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <a href="{{ route('events.badges.pdf', $event) }}" target="_blank" class="btn-ghost btn-sm">Print all ↗</a>
                    @if ($this->selectedCount())
                        <a href="{{ route('events.badges.pdf', [$event, 'ids' => implode(',', $selected)]) }}" target="_blank" class="btn-navy btn-sm">
                            Print {{ $this->selectedCount() }} selected ↗
                        </a>
                    @endif
                    <button type="button" wire:click="saveBadge" class="btn-gold btn-sm">Save</button>
                </div>
            </div>

            <div class="mt-4 grid gap-5 lg:grid-cols-[minmax(0,260px)_minmax(0,1fr)]">
                {{-- ── the controls ── --}}
                <div class="space-y-3">
                    <label class="block">
                        <span class="field-label !mb-1 !text-eyebrow">Size</span>
                        <select wire:model.live="badge.size" class="input h-10 text-sm">
                            @foreach (\App\Support\Badge::SIZES as $key => [$label, $w, $h])
                                <option value="{{ $key }}">{{ $label }} — {{ $w }} × {{ $h }} mm</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="field-label !mb-1 !text-eyebrow">Accent</span>
                        <input type="color" wire:model.live="badge.accent" class="input h-10 w-full p-1">
                        @error('badge.accent')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                        <span class="mt-1 block text-eyebrow text-muted">Leave as the event's own accent unless the badge needs its own.</span>
                    </label>

                    <div class="space-y-1.5 rounded-xl border border-line p-3">
                        <p class="eyebrow mb-1">Show on the badge</p>
                        @foreach ([
                            'show_logo' => 'Event logo',
                            'show_organisation' => 'Organisation',
                            'show_job_title' => 'Job title',
                            'show_ticket' => 'Ticket type',
                            'show_qr' => 'QR code',
                            'show_reference' => 'Reference',
                        ] as $key => $label)
                            <label class="flex cursor-pointer items-center gap-2 text-[12.5px] font-semibold text-navy-700">
                                <input type="checkbox" wire:model.live="badge.{{ $key }}" class="h-3.5 w-3.5 rounded border-navy-300 text-navy-900 focus:ring-gold-400">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>

                    <label class="block">
                        <span class="field-label !mb-1 !text-eyebrow">Footer line</span>
                        <input type="text" wire:model.live.debounce.400ms="badge.footer" maxlength="80" class="input h-10 text-sm" placeholder="e.g. Wear at all times">
                        @error('badge.footer')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                    </label>

                    <button type="button" wire:click="resetBadge" wire:confirm="Put the badge design back to the default?" class="btn-ghost btn-xs">Reset to default</button>
                </div>

                {{-- ── the preview ── --}}
                <div class="flex min-w-0 items-start justify-center rounded-2xl bg-page/70 p-6">
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
        <div class="card mb-4 p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <h3 class="pf text-base font-bold text-navy-900">Public registration</h3>
                    <p class="mt-0.5 max-w-[56ch] text-xs text-muted">
                        Share this link and people fill the form themselves. Everyone who registers lands in
                        this list, ready for a badge — nobody retypes anything.
                    </p>
                </div>
                <button type="button" wire:click="toggleRegistration"
                        class="{{ $event->registration_open ? 'btn-ghost' : 'btn-gold' }} btn-sm shrink-0">
                    {{ $event->registration_open ? 'Close registration' : 'Open registration' }}
                </button>
            </div>

            {{-- The link. Selectable rather than behind a copy button that may
                 not have clipboard permission on an unsecured origin. --}}
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <input type="text" readonly value="{{ $event->registrationUrl() }}"
                       onfocus="this.select()"
                       class="input h-10 min-w-0 flex-1 bg-page/60 font-mono text-[12px] text-navy-600">
                <a href="{{ $event->registrationUrl() }}" target="_blank" class="btn-ghost btn-sm shrink-0">Open ↗</a>
                <button type="button" wire:click="newRegistrationLink"
                        wire:confirm="Make a new link? The current one stops working immediately. Everyone who has already registered keeps their place."
                        class="btn-ghost btn-sm shrink-0" title="Use this if the link has been shared somewhere it should not have been">New link</button>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-[160px_minmax(0,1fr)]">
                <label class="block">
                    <span class="field-label !mb-1 !text-eyebrow">Capacity</span>
                    <input type="number" min="1" wire:model="registrationCapacity" class="input h-10 text-sm" placeholder="no limit">
                    @error('registrationCapacity')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                </label>
                <label class="block">
                    <span class="field-label !mb-1 !text-eyebrow">What they should know</span>
                    <input type="text" wire:model="registrationNote" class="input h-10 text-sm" placeholder="e.g. Doors open at 08:30. Please bring photo ID.">
                </label>
            </div>

            <div class="mt-3 flex items-center justify-between gap-3">
                {{-- Note the space before every @if: Blade will not recognise a
                     directive glued to a word character, but it will recognise
                     the @endif, which then closes the wrong block. --}}
                <p class="text-eyebrow text-muted">
                    {{ $stats['registered'] }} registered
                    @if ($event->registration_capacity) of {{ $event->registration_capacity }} @endif
                    @if ($event->registrationIsFull())
                        · <span class="font-bold text-warn">Full — the form is refusing new names.</span>
                    @endif
                </p>
                <button type="button" wire:click="saveRegistrationSettings" class="btn-navy btn-sm">Save</button>
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
        <form wire:submit="import" class="card mb-4 flex flex-wrap items-end gap-3 p-4">
            <div class="flex-1">
                {{-- The columns are this event's own questions now, so naming
                     a fixed set here would be a lie the moment somebody adds
                     one. The template is the answer. --}}
                <label class="field-label !mb-1 !text-eyebrow" for="att-import">
                    Excel (.xlsx) or CSV — the columns are
                    <a href="{{ route('events.attendees.template', $event) }}" class="font-semibold text-gold-600 hover:underline">this event's questions</a>,
                    plus amount and VIP · re-importing updates by email
                </label>
                <input id="att-import" type="file" wire:model="importFile" accept=".xlsx,.xls,.csv,text/csv" class="input h-10 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-white">
                @error('importFile') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
            </div>
            <a href="{{ route('events.attendees.template', $event) }}" class="h-10 rounded-xl border border-gold-300 bg-gold-50 px-3.5 text-xs font-bold leading-10 text-gold-800 transition hover:bg-gold-100" title="Download a ready-to-fill Excel template">↧ Template</a>
            <button type="button" wire:click="$set('showImport', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
            <button type="submit" class="btn-navy h-10 px-5 text-xs" wire:loading.attr="disabled" wire:target="import,importFile">Import</button>
        </form>
    @endif

    {{-- ══ Table ══ --}}
    <div class="card overflow-x-auto">
        <table class="w-full min-w-[760px]">
            <thead>
                <tr class="border-b border-line bg-page/40 text-left text-eyebrow font-bold uppercase tracking-wide text-muted">
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
                    <tr wire:key="att-{{ $a->id }}" wire:click="edit({{ $a->id }})" class="group cursor-pointer border-b border-line last:border-0 hover:bg-page/30">
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-navy-900 text-eyebrow font-bold text-gold-400">{{ $a->initials() }}</span>
                                <div class="min-w-0">
                                    <p class="flex items-center gap-1.5 truncate text-sm font-bold text-navy-900">{{ $a->name }}@if ($a->vip)<span class="text-gold-700" title="VIP">★</span>@endif</p>
                                    <p class="truncate text-micro text-muted">{{ $a->organization ?: $a->email ?: '—' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-2.5"><span class="rounded-full bg-navy-50 px-2.5 py-0.5 text-eyebrow font-bold text-navy-600">{{ $a->ticket_type }}</span></td>
                        <td class="px-3 py-2.5"><span class="rounded-full px-2.5 py-0.5 text-eyebrow font-bold {{ $sc }}">{{ $sl }}</span></td>
                        <td class="px-3 py-2.5 text-right text-xs font-semibold text-navy-700">{{ $a->amount_cents ? $event->money($a->amount_cents) : '—' }}</td>
                        <td class="px-3 py-2.5 text-center">
                            <button type="button" wire:click.stop="toggleCheckIn({{ $a->id }})"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-sm transition {{ $a->status === 'checked_in' ? 'bg-emerald-100 text-emerald-700' : 'bg-navy-50 text-navy-300 hover:bg-navy-100 hover:text-navy-600' }}"
                                    title="{{ $a->status === 'checked_in' ? 'Undo check-in' : 'Check in' }}">✓</button>
                        </td>
                        <td class="px-3 py-2.5">
                            <div class="flex items-center justify-end gap-1">
                                <span class="text-eyebrow font-semibold text-navy-400 opacity-0 transition group-hover:opacity-100">Edit ✎</span>
                                <button type="button" wire:click.stop="delete({{ $a->id }})" wire:confirm="Remove {{ $a->name }}?" class="rounded-lg bg-risk/10 px-2 py-1 text-eyebrow font-bold text-red-700 opacity-0 transition hover:bg-risk/20 group-hover:opacity-100">✕</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-16 text-center">
                        <p class="text-sm font-semibold text-navy-900">{{ $search || $filterStatus || $filterTicket ? 'No attendees match your filters' : 'No attendees yet' }}</p>
                        @unless ($search || $filterStatus || $filterTicket)
                            <p class="mx-auto mt-1 max-w-md text-xs text-muted">Add attendees one by one, or import your registration list from Excel.</p>
                            <div class="mt-4 flex justify-center gap-2">
                                <button type="button" wire:click="newItem" class="btn-navy h-10 px-5 text-xs">＋ Add attendee</button>
                                <button type="button" wire:click="$set('showImport', true)" class="h-10 rounded-xl border border-line bg-white px-4 text-xs font-semibold text-navy-700 hover:border-gold-300">⇪ Import list</button>
                            </div>
                        @endunless
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-center text-eyebrow text-muted">{{ $attendees->count() }} shown</p>

    {{-- ══ Modal ══ --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit attendee' : 'Add attendee'" max="lg" close="set('showForm', false)">
                <form wire:submit="save" class="grid gap-3.5">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="field-label !mb-1 !text-eyebrow">Full name</label>
                            <input type="text" wire:model="name" class="input h-10 text-sm" placeholder="e.g. Layla Haddad">
                            @error('name')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="field-label !mb-1 !text-eyebrow">Email</label>
                            <input type="email" wire:model="email" class="input h-10 text-sm" placeholder="name@company.com">
                            @error('email')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="field-label !mb-1 !text-eyebrow">Organization</label>
                            <input type="text" wire:model="organization" class="input h-10 text-sm">
                        </div>
                        <div>
                            <label class="field-label !mb-1 !text-eyebrow">Phone</label>
                            <input type="text" wire:model="phone" class="input h-10 text-sm">
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="field-label !mb-1 !text-eyebrow">Ticket type</label>
                            <select wire:model="ticket_type" class="input h-10 text-sm">
                                @foreach ($ticketTypes as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="field-label !mb-1 !text-eyebrow">Status</label>
                            <select wire:model="status" class="input h-10 text-sm">
                                @foreach (\App\Models\EventAttendee::STATUS_META as $val => $meta)<option value="{{ $val }}">{{ $meta[0] }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="field-label !mb-1 !text-eyebrow">Fee ({{ $event->currencySymbol() }})</label>
                            <input type="number" step="0.01" min="0" wire:model="amount" class="input h-10 text-sm" placeholder="0">
                            @error('amount')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex items-center gap-2 text-xs font-semibold text-navy-700"><input type="checkbox" wire:model="vip" class="h-4 w-4 rounded border-line text-gold-700 focus:ring-gold-400"> ★ VIP</label>
                        <div>
                            <label class="field-label !mb-1 !text-eyebrow">Dietary / access</label>
                            <input type="text" wire:model="dietary" class="input h-9 text-sm" placeholder="e.g. Vegetarian, wheelchair">
                        </div>
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Notes</label>
                        <textarea wire:model="notes" rows="2" class="input text-sm"></textarea>
                    </div>
                    <div class="mt-1 flex items-center gap-3">
                        @if ($editingId)
                            <button type="button" wire:click="delete({{ $editingId }})" wire:confirm="Remove this attendee?" class="rounded-xl px-3 py-2 text-xs font-bold text-risk transition hover:bg-risk/5">Delete</button>
                        @endif
                        <div class="ml-auto flex gap-2">
                            <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
                            <button type="submit" class="btn-navy h-10 px-6 text-xs">{{ $editingId ? 'Save' : 'Add attendee' }}</button>
                        </div>
                    </div>
                </form>
        </x-modal>
    @endif

    <x-bulk-bar :count="$this->selectedCount()" noun="attendee" />
    @endif
</div>
