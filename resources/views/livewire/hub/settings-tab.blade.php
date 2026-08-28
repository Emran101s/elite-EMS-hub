<div class="cx-canvas">
    @php
        $settingsHex = \App\Models\Event::moduleColor('settings') !== '#0B1F3A'
            ? \App\Models\Event::moduleColor('settings')
            : '#0B1F3A';
        $enabledCount = is_array($event->enabled_modules)
            ? count($event->enabled_modules)
            : count(\App\Models\Event::HUB_MODULES);
    @endphp
    <div class="mb-3 flex flex-wrap items-center gap-1.5">
        <span class="inline-flex h-7 items-center gap-2 rounded-full bg-white px-2.5 text-eyebrow font-bold text-ink ring-1 ring-line">
            <span class="cx-cathex" style="width:18px;height:20px;background: {{ $settingsHex }}">
                <x-icon name="cog" class="h-2.5 w-2.5" />
            </span>
            Event settings
        </span>
        <span class="inline-flex h-7 items-center gap-1.5 rounded-full bg-white px-2.5 text-eyebrow font-bold text-ink ring-1 ring-line">
            <span class="text-muted">Stage</span>
            <span>{{ str($event->stage)->replace('_', ' ')->title() }}</span>
        </span>
        <span class="inline-flex h-7 items-center gap-1.5 rounded-full bg-white px-2.5 text-eyebrow font-bold text-ink ring-1 ring-line">
            <span class="text-muted">Modules on</span>
            <span class="tabular-nums">{{ $enabledCount }}</span>
        </span>
        <span class="inline-flex h-7 items-center gap-1.5 rounded-full bg-white px-2.5 text-eyebrow font-bold text-ink ring-1 ring-line">
            <span class="text-muted">Currency</span>
            <span>{{ $event->currency }}</span>
        </span>
    </div>

    <div class="flex gap-4">
        {{-- section rail --}}
        <aside class="hidden w-52 shrink-0 lg:block">
            <nav class="sticky top-12 space-y-0.5">
                <p class="mb-2 px-2 text-eyebrow font-bold uppercase tracking-[0.2em] text-muted">Sections</p>
                <a href="#s-details" class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-micro text-muted transition hover:bg-page hover:text-ink">
                    <span class="w-4 text-right text-eyebrow font-bold text-muted">01</span>
                    <span class="truncate">Event Details</span>
                </a>
                <a href="#s-ownership" class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-micro text-muted transition hover:bg-page hover:text-ink">
                    <span class="w-4 text-right text-eyebrow font-bold text-muted">02</span>
                    <span class="truncate">Ownership & Lifecycle</span>
                </a>
                <a href="#s-team" class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-micro text-muted transition hover:bg-page hover:text-ink">
                    <span class="w-4 text-right text-eyebrow font-bold text-muted">03</span>
                    <span class="truncate">Event Team</span>
                </a>
                <a href="#s-theme" class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-micro text-muted transition hover:bg-page hover:text-ink">
                    <span class="w-4 text-right text-eyebrow font-bold text-muted">04</span>
                    <span class="truncate">Avatar & Theme</span>
                </a>
                <a href="#s-modules" class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-micro text-muted transition hover:bg-page hover:text-ink">
                    <span class="w-4 text-right text-eyebrow font-bold text-muted">05</span>
                    <span class="truncate">Enabled Modules</span>
                </a>
                <a href="#s-manage" class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-micro text-muted transition hover:bg-page hover:text-ink">
                    <span class="w-4 text-right text-eyebrow font-bold text-muted">06</span>
                    <span class="truncate">Manage Event</span>
                </a>
            </nav>
        </aside>

        {{-- constrained column — no more edge-to-edge sprawl --}}
        <div class="min-w-0 flex-1">
            <div class="mx-auto max-w-3xl space-y-6">
    <form wire:submit="save" class="space-y-6">

        {{-- ── Event details ── --}}
        <div id="s-details" class="cx-lcard scroll-mt-32 p-4">
            <div class="mb-5 flex items-baseline gap-2.5 border-b border-line pb-2">
                <span class="text-xl font-bold leading-none text-line">01</span>
                <h3 class="text-base font-bold text-ink">Event Details</h3>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="st-name">Event name</label>
                    <input id="st-name" type="text" wire:model="name" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none">
                    @error('name') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="st-desc">Description</label>
                    <textarea id="st-desc" wire:model="description" rows="2" class="w-full rounded-lg border border-line bg-white px-2.5 py-2 text-sm text-ink focus:border-navy-300 focus:outline-none" placeholder="Scope, audience, objectives…"></textarea>
                </div>
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="st-client">Client</label>
                    <select id="st-client" wire:model="client_id" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none">
                        <option value="">— Select client —</option>
                        @foreach ($clients as $client)<option value="{{ $client->id }}">{{ $client->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="st-newclient">…or new client</label>
                    <input id="st-newclient" type="text" wire:model="new_client" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none" placeholder="Creates on save">
                </div>
                <div>
                    {{-- The type sets the crest and was previously fixed at creation.
                         The options come from Settings → Types & lists. --}}
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="st-type">Event type</label>
                    <select id="st-type" wire:model="type" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none">
                        @foreach (\App\Support\Taxonomy::options('event_type') as $tv => $tl)
                            <option value="{{ $tv }}">{{ $tl }}</option>
                        @endforeach
                        {{-- A type that has since been retired must still show as this event's. --}}
                        @unless (array_key_exists($event->type, \App\Support\Taxonomy::options('event_type')))
                            <option value="{{ $event->type }}">{{ \App\Support\Taxonomy::label('event_type', $event->type) }}</option>
                        @endunless
                    </select>
                    @error('type') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="st-part">Expected participants</label>
                    <input id="st-part" type="number" min="0" wire:model="expected_participants" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none" placeholder="e.g. 400">
                    @error('expected_participants') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="st-currency">Currency</label>
                    <select id="st-currency" wire:model="currency" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none">
                        @foreach (\App\Models\Event::CURRENCIES as $code => [$symbol, $label])<option value="{{ $code }}">{{ $code }} — {{ $label }} ({{ $symbol }})</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="st-budget">Budget ({{ $currency }})</label>
                    <input id="st-budget" type="number" step="0.01" min="0" wire:model="budget" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none" placeholder="250000">
                </div>
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="st-city">City</label>
                    <input id="st-city" type="text" wire:model="city" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none" placeholder="Amman">
                    @error('city') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="st-country">Country</label>
                    <select id="st-country" wire:model="country" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none">
                        @foreach (['Jordan', 'Bahrain', 'UAE', 'Qatar', 'KSA', 'Kuwait', 'Oman', 'Egypt'] as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="st-start">Start date</label>
                    <input id="st-start" type="date" wire:model.live="starts_at" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none">
                    @error('starts_at') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="st-end">End date</label>
                    <input id="st-end" type="date" wire:model.live="ends_at" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none">
                    @error('ends_at') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
                </div>
            </div>
            @php
                $span = 0;
                if ($starts_at) {
                    try {
                        $s = \Carbon\Carbon::parse($starts_at);
                        $e = $ends_at ? \Carbon\Carbon::parse($ends_at) : $s;
                        $span = $e->gte($s) ? (int) $s->diffInDays($e) + 1 : 0;
                    } catch (\Throwable) {}
                }
            @endphp
            @if ($span > 0)
                <div class="mt-4 flex items-center gap-2 rounded-lg bg-page px-4 py-2.5 text-xs font-semibold text-ink">
                    <x-icon name="calendar" class="h-4 w-4 text-gold-700" />
                    {{ $span }}-day event · saving keeps the agenda &amp; Run of Show at {{ $span }} {{ str('day')->plural($span) }} (Day 1–{{ $span }}).
                </div>
            @endif
        </div>

        {{-- ── Ownership ── --}}
        <div id="s-ownership" class="cx-lcard scroll-mt-32 p-4">
            <div class="mb-5 flex items-baseline gap-2.5 border-b border-line pb-2">
                <span class="text-xl font-bold leading-none text-line">02</span>
                <h3 class="text-base font-bold text-ink">Ownership &amp; Lifecycle</h3>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="st-pm">Project manager</label>
                    <select id="st-pm" wire:model="project_manager_id" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none">
                        <option value="">— Unassigned —</option>
                        @foreach ($managers as $manager)<option value="{{ $manager->id }}">{{ $manager->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="st-venue">Location / Venue</label>
                    <select id="st-venue" wire:model="venue_id" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none">
                        <option value="">— Not assigned —</option>
                        @foreach ($venues as $venue)<option value="{{ $venue->id }}">{{ $venue->name }}@if ($venue->type) — {{ $venue->type }}@endif@if ($venue->city) · {{ $venue->city }}@endif</option>@endforeach
                    </select>
                    <p class="mt-1 text-eyebrow text-muted">Where the event is held. <a href="{{ route('venues.index') }}" class="font-semibold text-gold-700">Manage venues →</a></p>
                </div>
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="st-stage">Lifecycle stage</label>
                    <select id="st-stage" wire:model="stage" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none">
                        @foreach (\App\Models\Event::STAGES as $s)<option value="{{ $s }}">{{ str($s)->replace('_', ' ')->title() }}</option>@endforeach
                    </select>
                    <p class="mt-1 text-eyebrow text-muted">The event’s status over time — not the location.</p>
                </div>
            </div>
            <p class="mt-2 text-eyebrow text-muted">Rooms &amp; halls inside the venue are managed in the <a href="{{ route('events.hub', [$event, 'tab' => 'venue']) }}" class="font-semibold text-gold-700">Venue tab</a>.</p>
        </div>

        {{-- ── Event Team ── --}}
        <div id="s-team" class="cx-lcard scroll-mt-32 p-4">
            <div class="mb-5 flex items-baseline gap-2.5 border-b border-line pb-2">
                <span class="text-xl font-bold leading-none text-line">03</span>
                <h3 class="text-base font-bold text-ink">Event Team</h3>
            </div>
            <p class="mb-4 text-eyebrow text-muted">Assign workspace members to this event with a role. Manage the full staff list in <a href="{{ route('team.index') }}" class="font-semibold text-gold-700">Settings → Team</a>.</p>

            <div class="space-y-1.5">
                @forelse ($team as $m)
                    <div wire:key="team-{{ $m->id }}-{{ $m->pivot->role }}" class="flex flex-wrap items-center gap-x-3 gap-y-1.5 rounded-lg border border-line bg-page/60 px-3 py-2">
                        <x-user-avatar :user="$m" size="h-8 w-8" text="text-eyebrow" />
                        <div class="order-last w-full min-w-0 sm:order-none sm:w-auto sm:flex-1">
                            <p class="truncate text-sm font-semibold text-ink">{{ $m->name }}</p>
                            <p class="truncate text-eyebrow text-muted">{{ $m->title ?: $m->email }}</p>
                        </div>
                        <span class="shrink-0 rounded-full bg-page px-2.5 py-0.5 text-eyebrow font-bold text-muted">{{ $roleLabels[$m->pivot->role] ?? $m->pivot->role }}</span>
                        <button type="button" wire:click="removeTeamMember({{ $m->id }}, '{{ $m->pivot->role }}')" class="shrink-0 text-sm text-muted transition hover:text-red-600" title="Remove from team">✕</button>
                    </div>
                @empty
                    <p class="py-2 text-xs text-muted">No team assigned yet — add members below.</p>
                @endforelse
            </div>

            <div class="mt-3 flex flex-wrap items-end gap-2 border-t border-line pt-3">
                <div class="min-w-[150px] flex-1">
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Member</label>
                    <select wire:model="teamUserId" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none">
                        <option value="">— Select member —</option>
                        @foreach ($managers as $manager)<option value="{{ $manager->id }}">{{ $manager->name }}</option>@endforeach
                    </select>
                </div>
                <div class="min-w-[150px] flex-1">
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Role</label>
                    <select wire:model="teamRole" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none">
                        @foreach ($roleLabels as $val => $lbl)<option value="{{ $val }}">{{ $lbl }}</option>@endforeach
                    </select>
                </div>
                <button type="button" wire:click="addTeamMember" class="h-10 rounded-full bg-navy-900 px-4 text-xs font-bold text-white transition hover:bg-navy-800">＋ Add to team</button>
            </div>
        </div>

        {{-- ── Identity: avatar + theme ── --}}
        <div id="s-theme" class="cx-lcard scroll-mt-32 p-4">
            <div class="mb-5 flex items-baseline gap-2.5 border-b border-line pb-2">
                <span class="text-xl font-bold leading-none text-line">04</span>
                <h3 class="text-base font-bold text-ink">Identity &amp; Theme</h3>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">

                {{-- Cover image + logo uploads --}}
                <div class="sm:col-span-2 grid gap-4 sm:grid-cols-2">
                    {{-- cover --}}
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Cover image</label>
                        <div class="relative h-28 overflow-hidden rounded-lg border border-line bg-page">
                            @if ($cover)
                                <img src="{{ $cover->temporaryUrl() }}" alt="Cover" class="h-full w-full object-cover">
                            @elseif ($event->cover_path)
                                <img src="{{ $event->coverUrl() }}" alt="Cover" class="h-full w-full object-cover">
                            @else
                                <x-event-crest :event="$event" :name="$event->name" :type="$event->type" class="h-full w-full" />
                            @endif
                        </div>
                        <div class="mt-1.5 flex items-center gap-3">
                            <label class="cursor-pointer text-eyebrow font-bold text-gold-700 hover:text-gold-800">
                                <span wire:loading.remove wire:target="cover">{{ $event->cover_path || $cover ? 'Replace' : 'Upload' }}</span>
                                <span wire:loading wire:target="cover">Uploading…</span>
                                <input type="file" wire:model="cover" accept="image/*" class="hidden">
                            </label>
                            @if ($event->cover_path || $cover)
                                <button type="button" wire:click="removeCover" class="text-eyebrow font-bold text-muted hover:text-danger-ink">Remove</button>
                            @endif
                        </div>
                        @error('cover') <p class="mt-1 text-eyebrow text-danger-ink">{{ $message }}</p> @enderror
                    </div>
                    {{-- logo --}}
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Logo</label>
                        <div class="flex h-28 items-center justify-center overflow-hidden rounded-lg border border-line bg-white">
                            @if ($logo)
                                <img src="{{ $logo->temporaryUrl() }}" alt="Logo" class="h-full w-full object-contain p-3">
                            @elseif ($event->logo_path)
                                <img src="{{ $event->logoUrl() }}" alt="Logo" class="h-full w-full object-contain p-3">
                            @else
                                <span class="text-eyebrow text-muted">No logo</span>
                            @endif
                        </div>
                        <div class="mt-1.5 flex items-center gap-3">
                            <label class="cursor-pointer text-eyebrow font-bold text-gold-700 hover:text-gold-800">
                                <span wire:loading.remove wire:target="logo">{{ $event->logo_path || $logo ? 'Replace' : 'Upload' }}</span>
                                <span wire:loading wire:target="logo">Uploading…</span>
                                <input type="file" wire:model="logo" accept="image/*" class="hidden">
                            </label>
                            @if ($event->logo_path || $logo)
                                <button type="button" wire:click="removeLogo" class="text-eyebrow font-bold text-muted hover:text-danger-ink">Remove</button>
                            @endif
                        </div>
                        @error('logo') <p class="mt-1 text-eyebrow text-danger-ink">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Color theme --}}
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Color Theme</label>
                    <div class="flex flex-wrap items-center gap-2">
                        @foreach ($palettes as $key => [$label, $primary, $secondary, $accent, $text])
                            <button type="button" wire:click="usePalette('{{ $key }}')" title="{{ $label }}"
                                    @class([
                                        'flex items-center gap-1.5 rounded-lg border px-2 py-1.5 transition',
                                        'border-navy-900 ring-1 ring-navy-900/30' => $primary_color === $primary && $accent_color === $accent,
                                        'border-line hover:border-navy-300' => ! ($primary_color === $primary && $accent_color === $accent),
                                    ])>
                                <span class="flex gap-0.5">
                                    <span class="h-4 w-4 rounded ring-1 ring-line" style="background: {{ $primary }}"></span>
                                    <span class="h-4 w-4 rounded ring-1 ring-line" style="background: {{ $accent }}"></span>
                                </span>
                                <span class="text-eyebrow font-semibold text-ink">{{ str($label)->before(' +') }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Modules ── --}}
        <div id="s-modules" class="cx-lcard scroll-mt-32 p-4">
            <div class="mb-5 flex items-baseline gap-2.5 border-b border-line pb-2">
                <span class="text-xl font-bold leading-none text-line">05</span>
                <h3 class="text-base font-bold text-ink">Enabled Modules</h3>
            </div>
            <p class="mb-1 text-xs text-muted">Turn tabs on or off for this event's control room. Every module is available to every event.</p>
            <p class="mb-4 flex items-center gap-1.5 text-micro font-semibold text-success-ink">
                <span class="h-1.5 w-1.5 rounded-full bg-success"></span>
                Module changes apply immediately — no need to press Save.
            </p>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($hubModules as $key => [$label, $category, $icon])
                    @php $on = in_array($key, $modules, true); @endphp
                    <button type="button" wire:click="toggleModule('{{ $key }}')"
                            @class([
                                'flex items-center gap-3 rounded-lg border p-3 text-left transition',
                                'border-navy-900/30 bg-navy-50 shadow-sm' => $on,
                                'border-line bg-white hover:border-navy-200' => ! $on,
                            ])>
                        <span @class([
                            'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg transition',
                            'bg-navy-900 text-gold-400' => $on,
                            'bg-page text-muted' => ! $on,
                        ])><x-icon :name="$icon" class="h-4 w-4" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-xs font-bold {{ $on ? 'text-ink' : 'text-muted' }}">{{ $label }}</span>
                            <span class="block text-eyebrow {{ $on ? 'text-gold-700' : 'text-muted' }}">{{ $category }}</span>
                        </span>
                        <span @class(['relative flex h-5 w-9 shrink-0 items-center rounded-full transition', 'bg-navy-900' => $on, 'bg-line' => ! $on])>
                            <span class="absolute h-4 w-4 rounded-full bg-white shadow transition-all {{ $on ? 'left-[18px]' : 'left-0.5' }}"></span>
                        </span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- sticky save bar --}}
        <div class="sticky bottom-4 z-10 flex items-center justify-between gap-3 rounded-lg bg-navy-900 px-5 py-3 shadow-overlay ring-1 ring-white/10">
            <p class="text-micro text-white/50">
                <span wire:loading.remove>Changes are saved when you press Save.</span>
                <span wire:loading class="text-gold-400">Saving…</span>
            </p>
            <button type="submit" class="flex h-9 items-center rounded-lg bg-gold-500 px-5 text-xs font-bold text-navy-900 shadow transition hover:bg-gold-400">Save Settings</button>
        </div>
    </form>

    {{-- ── Danger zone ── --}}
    <div id="s-manage" class="cx-lcard scroll-mt-32 p-4">
        <div class="mb-5 flex items-baseline gap-2.5 border-b border-line pb-2">
            <span class="text-xl font-bold leading-none text-line">06</span>
            <h3 class="text-base font-bold text-ink">Manage Event</h3>
        </div>

        {{-- Duplicate is the product's "start from this event as a template"
             path — framed first so it reads as reuse, not a danger action. --}}
        <div class="mb-4 rounded-lg border border-gold-200 bg-gold-50 p-4">
            <div class="flex flex-wrap items-start gap-3">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-gold-100 text-gold-700">
                    <x-icon name="share" class="h-4 w-4" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-[13px] font-bold text-ink">Start another event from this one</p>
                    <p class="mt-0.5 text-[11.5px] text-muted">Copies the structure, modules and settings into a new draft — the cleanest “save as template” the platform has today.</p>
                </div>
                <button type="button" wire:click="duplicate"
                        class="shrink-0 rounded-lg bg-navy-900 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-navy-800">
                    Duplicate event →
                </button>
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <x-confirm title="Archive “{{ $event->name }}”?"
                       body="It disappears from lists and the Operations Hub (recoverable)."
                       confirm="Archive" tone="warn" run="$wire.archive()"
                       class="rounded-lg border border-danger/30 bg-danger-soft/40 px-4 py-2.5 text-xs font-semibold text-danger-ink transition hover:bg-danger-soft">⌫ Archive event</x-confirm>

            @unless ($showDelete)
                <button type="button" wire:click="askToDelete"
                        class="ms-auto rounded-lg px-4 py-2.5 text-xs font-semibold text-muted transition hover:bg-red-50 hover:text-red-600">
                    Delete permanently…
                </button>
            @endunless
        </div>

        {{-- ══ Deleting, with the bill in front of you ══

             Archive is the reversible one and stays a click. This is not, and
             it takes twenty tables with it — so it says what goes, what
             survives, and whether money has moved, and then asks for the name
             in writing. --}}
        @if ($showDelete && $inventory)
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50/50 p-4">
                <p class="text-[13px] font-black text-red-800">Delete “{{ $event->name }}” permanently?</p>
                <p class="mt-0.5 text-[11.5px] text-red-900/70">This cannot be undone.</p>

                @if ($inventory['money'])
                    <p class="mt-3 rounded-lg bg-red-100/70 px-3 py-2 text-[11.5px] font-bold text-red-900">
                        Money has moved on this event — {{ implode(' · ', $inventory['money']) }}.
                        Deleting it does not undo any of that.
                    </p>
                @endif

                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-red-700">This goes</p>
                        <p class="mt-1 text-[11.5px] leading-relaxed text-ink">
                            @forelse ($inventory['destroys'] as $what => $n)
                                {{ $n }} {{ str($what)->plural($n) }}@if (! $loop->last), @endif
                            @empty
                                Nothing but the event itself.
                            @endforelse
                        </p>
                    </div>

                    <div>
                        <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">This stays</p>
                        <p class="mt-1 text-[11.5px] leading-relaxed text-ink">
                            @forelse ($inventory['keeps'] as $what => $n)
                                {{ $n }} {{ str($what)->plural($n) }}@if (! $loop->last), @endif
                            @empty
                                —
                            @endforelse
                            @if ($inventory['keeps'])
                                <span class="block text-[10.5px] text-muted">Kept, no longer attached to an event.</span>
                            @endif
                        </p>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-end gap-2">
                    <label class="block">
                        <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-red-700">Type the event name to confirm</span>
                        <input type="text" wire:model.live="confirmName" placeholder="{{ $event->name }}"
                               class="h-9 w-72 max-w-full rounded-lg border border-line bg-white px-2.5 text-xs text-ink focus:border-navy-300 focus:outline-none">
                    </label>

                    <button type="button" wire:click="destroyEvent"
                            @disabled(mb_strtolower(trim($confirmName)) !== mb_strtolower($event->name))
                            class="h-9 rounded-lg bg-red-600 px-4 text-xs font-bold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-40">
                        Delete this event
                    </button>

                    <button type="button" wire:click="cancelDelete"
                            class="h-9 rounded-lg px-3 text-xs font-bold text-muted transition hover:text-ink">Cancel</button>
                </div>

                @error('confirmName') <p class="mt-1.5 text-[11px] font-semibold text-danger-ink">{{ $message }}</p> @enderror
            </div>
        @endif
    </div>
        </div>
        </div>
    </div>
</div>
