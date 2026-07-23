<div>
    <style>.pf{font-family:'Spectral',Georgia,serif}</style>
    <div class="flex gap-6">
        {{-- section rail --}}
        <aside class="hidden w-52 shrink-0 lg:block">
            <nav class="sticky top-[112px] space-y-0.5">
                <p class="mb-2 px-2 text-eyebrow font-bold uppercase tracking-[0.2em] text-navy-300">Event Settings</p>
                <a href="#s-details" class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-micro text-navy-500 transition hover:bg-gold-50/60 hover:text-navy-900">
                    <span class="w-4 text-right text-eyebrow font-bold text-gold-500/70">01</span>
                    <span class="truncate">Event Details</span>
                </a>
                <a href="#s-ownership" class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-micro text-navy-500 transition hover:bg-gold-50/60 hover:text-navy-900">
                    <span class="w-4 text-right text-eyebrow font-bold text-gold-500/70">02</span>
                    <span class="truncate">Ownership & Lifecycle</span>
                </a>
                <a href="#s-team" class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-micro text-navy-500 transition hover:bg-gold-50/60 hover:text-navy-900">
                    <span class="w-4 text-right text-eyebrow font-bold text-gold-500/70">03</span>
                    <span class="truncate">Event Team</span>
                </a>
                <a href="#s-theme" class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-micro text-navy-500 transition hover:bg-gold-50/60 hover:text-navy-900">
                    <span class="w-4 text-right text-eyebrow font-bold text-gold-500/70">04</span>
                    <span class="truncate">Avatar & Theme</span>
                </a>
                <a href="#s-modules" class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-micro text-navy-500 transition hover:bg-gold-50/60 hover:text-navy-900">
                    <span class="w-4 text-right text-eyebrow font-bold text-gold-500/70">05</span>
                    <span class="truncate">Enabled Modules</span>
                </a>
                <a href="#s-manage" class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-micro text-navy-500 transition hover:bg-gold-50/60 hover:text-navy-900">
                    <span class="w-4 text-right text-eyebrow font-bold text-gold-500/70">06</span>
                    <span class="truncate">Manage Event</span>
                </a>
            </nav>
        </aside>

        {{-- constrained column — no more edge-to-edge sprawl --}}
        <div class="min-w-0 flex-1">
            <div class="mx-auto max-w-3xl space-y-6">
    <form wire:submit="save" class="space-y-6">

        {{-- ── Event details ── --}}
        <div id="s-details" class="card scroll-mt-32 p-6">
            <div class="mb-5 flex items-baseline gap-2.5 border-b border-line pb-2">
                <span class="pf text-xl font-bold leading-none text-gold-400/50">01</span>
                <h3 class="pf text-base font-bold text-navy-900">Event Details</h3>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="field-label !mb-1 !text-eyebrow" for="st-name">Event name</label>
                    <input id="st-name" type="text" wire:model="name" class="input h-10 text-sm">
                    @error('name') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="field-label !mb-1 !text-eyebrow" for="st-desc">Description</label>
                    <textarea id="st-desc" wire:model="description" rows="2" class="input text-sm" placeholder="Scope, audience, objectives…"></textarea>
                </div>
                <div>
                    <label class="field-label !mb-1 !text-eyebrow" for="st-client">Client</label>
                    <select id="st-client" wire:model="client_id" class="input h-10 text-sm">
                        <option value="">— Select client —</option>
                        @foreach ($clients as $client)<option value="{{ $client->id }}">{{ $client->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label !mb-1 !text-eyebrow" for="st-newclient">…or new client</label>
                    <input id="st-newclient" type="text" wire:model="new_client" class="input h-10 text-sm" placeholder="Creates on save">
                </div>
                <div>
                    <label class="field-label !mb-1 !text-eyebrow" for="st-part">Expected participants</label>
                    <input id="st-part" type="number" min="0" wire:model="expected_participants" class="input h-10 text-sm" placeholder="e.g. 400">
                    @error('expected_participants') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="field-label !mb-1 !text-eyebrow" for="st-currency">Currency</label>
                    <select id="st-currency" wire:model="currency" class="input h-10 text-sm">
                        @foreach (\App\Models\Event::CURRENCIES as $code => [$symbol, $label])<option value="{{ $code }}">{{ $code }} — {{ $label }} ({{ $symbol }})</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label !mb-1 !text-eyebrow" for="st-budget">Budget ({{ $currency }})</label>
                    <input id="st-budget" type="number" step="0.01" min="0" wire:model="budget" class="input h-10 text-sm" placeholder="250000">
                </div>
                <div>
                    <label class="field-label !mb-1 !text-eyebrow" for="st-city">City</label>
                    <input id="st-city" type="text" wire:model="city" class="input h-10 text-sm" placeholder="Amman">
                    @error('city') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="field-label !mb-1 !text-eyebrow" for="st-country">Country</label>
                    <select id="st-country" wire:model="country" class="input h-10 text-sm">
                        @foreach (['Jordan', 'Bahrain', 'UAE', 'Qatar', 'KSA', 'Kuwait', 'Oman', 'Egypt'] as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label !mb-1 !text-eyebrow" for="st-start">Start date</label>
                    <input id="st-start" type="date" wire:model.live="starts_at" class="input h-10 text-sm">
                    @error('starts_at') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="field-label !mb-1 !text-eyebrow" for="st-end">End date</label>
                    <input id="st-end" type="date" wire:model.live="ends_at" class="input h-10 text-sm">
                    @error('ends_at') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
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
                <div class="mt-4 flex items-center gap-2 rounded-2xl bg-navy-50 px-4 py-2.5 text-xs font-semibold text-navy-800">
                    <x-icon name="calendar" class="h-4 w-4 text-gold-600" />
                    {{ $span }}-day event · saving keeps the agenda &amp; Run of Show at {{ $span }} {{ str('day')->plural($span) }} (Day 1–{{ $span }}).
                </div>
            @endif
        </div>

        {{-- ── Ownership ── --}}
        <div id="s-ownership" class="card scroll-mt-32 p-6">
            <div class="mb-5 flex items-baseline gap-2.5 border-b border-line pb-2">
                <span class="pf text-xl font-bold leading-none text-gold-400/50">02</span>
                <h3 class="pf text-base font-bold text-navy-900">Ownership &amp; Lifecycle</h3>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="field-label !mb-1 !text-eyebrow" for="st-pm">Project manager</label>
                    <select id="st-pm" wire:model="project_manager_id" class="input h-10 text-sm">
                        <option value="">— Unassigned —</option>
                        @foreach ($managers as $manager)<option value="{{ $manager->id }}">{{ $manager->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label !mb-1 !text-eyebrow" for="st-venue">Location / Venue</label>
                    <select id="st-venue" wire:model="venue_id" class="input h-10 text-sm">
                        <option value="">— Not assigned —</option>
                        @foreach ($venues as $venue)<option value="{{ $venue->id }}">{{ $venue->name }}@if ($venue->type) — {{ $venue->type }}@endif@if ($venue->city) · {{ $venue->city }}@endif</option>@endforeach
                    </select>
                    <p class="mt-1 text-eyebrow text-muted">Where the event is held. <a href="{{ route('venues.index') }}" class="font-semibold text-gold-600">Manage venues →</a></p>
                </div>
                <div>
                    <label class="field-label !mb-1 !text-eyebrow" for="st-stage">Lifecycle stage</label>
                    <select id="st-stage" wire:model="stage" class="input h-10 text-sm">
                        @foreach (\App\Models\Event::STAGES as $s)<option value="{{ $s }}">{{ str($s)->replace('_', ' ')->title() }}</option>@endforeach
                    </select>
                    <p class="mt-1 text-eyebrow text-muted">The event’s status over time — not the location.</p>
                </div>
            </div>
            <p class="mt-2 text-eyebrow text-muted">Rooms &amp; halls inside the venue are managed in the <a href="{{ route('events.hub', [$event, 'tab' => 'venue']) }}" class="font-semibold text-gold-600">Venue tab</a>.</p>
        </div>

        {{-- ── Event Team ── --}}
        <div id="s-team" class="card scroll-mt-32 p-6">
            <div class="mb-5 flex items-baseline gap-2.5 border-b border-line pb-2">
                <span class="pf text-xl font-bold leading-none text-gold-400/50">03</span>
                <h3 class="pf text-base font-bold text-navy-900">Event Team</h3>
            </div>
            <p class="mb-4 text-eyebrow text-muted">Assign workspace members to this event with a role. Manage the full staff list in <a href="{{ route('team.index') }}" class="font-semibold text-gold-600">Settings → Team</a>.</p>

            <div class="space-y-1.5">
                @forelse ($team as $m)
                    <div wire:key="team-{{ $m->id }}-{{ $m->pivot->role }}" class="flex items-center gap-3 rounded-xl border border-line bg-page/40 px-3 py-2">
                        <x-user-avatar :user="$m" size="h-8 w-8" text="text-eyebrow" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-navy-900">{{ $m->name }}</p>
                            <p class="truncate text-eyebrow text-muted">{{ $m->title ?: $m->email }}</p>
                        </div>
                        <span class="shrink-0 rounded-full bg-navy-50 px-2.5 py-0.5 text-eyebrow font-bold text-navy-600">{{ $roleLabels[$m->pivot->role] ?? $m->pivot->role }}</span>
                        <button type="button" wire:click="removeTeamMember({{ $m->id }}, '{{ $m->pivot->role }}')" class="shrink-0 text-sm text-navy-300 transition hover:text-red-600" title="Remove from team">✕</button>
                    </div>
                @empty
                    <p class="py-2 text-xs text-muted">No team assigned yet — add members below.</p>
                @endforelse
            </div>

            <div class="mt-3 flex flex-wrap items-end gap-2 border-t border-line pt-3">
                <div class="min-w-[150px] flex-1">
                    <label class="field-label !mb-1 !text-eyebrow">Member</label>
                    <select wire:model="teamUserId" class="input h-10 text-sm">
                        <option value="">— Select member —</option>
                        @foreach ($managers as $manager)<option value="{{ $manager->id }}">{{ $manager->name }}</option>@endforeach
                    </select>
                </div>
                <div class="min-w-[150px] flex-1">
                    <label class="field-label !mb-1 !text-eyebrow">Role</label>
                    <select wire:model="teamRole" class="input h-10 text-sm">
                        @foreach ($roleLabels as $val => $lbl)<option value="{{ $val }}">{{ $lbl }}</option>@endforeach
                    </select>
                </div>
                <button type="button" wire:click="addTeamMember" class="btn-navy h-10 px-4 text-xs">＋ Add to team</button>
            </div>
        </div>

        {{-- ── Identity: avatar + theme ── --}}
        <div id="s-theme" class="card scroll-mt-32 p-6">
            <div class="mb-5 flex items-baseline gap-2.5 border-b border-line pb-2">
                <span class="pf text-xl font-bold leading-none text-gold-400/50">04</span>
                <h3 class="pf text-base font-bold text-navy-900">Identity &amp; Theme</h3>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">

                {{-- Cover image + logo uploads --}}
                <div class="sm:col-span-2 grid gap-4 sm:grid-cols-2">
                    {{-- cover --}}
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Cover image</label>
                        <div class="relative h-28 overflow-hidden rounded-2xl border border-line bg-navy-50">
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
                                <button type="button" wire:click="removeCover" class="text-eyebrow font-bold text-muted hover:text-risk">Remove</button>
                            @endif
                        </div>
                        @error('cover') <p class="mt-1 text-eyebrow text-risk">{{ $message }}</p> @enderror
                    </div>
                    {{-- logo --}}
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Logo</label>
                        <div class="flex h-28 items-center justify-center overflow-hidden rounded-2xl border border-line bg-white">
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
                                <button type="button" wire:click="removeLogo" class="text-eyebrow font-bold text-muted hover:text-risk">Remove</button>
                            @endif
                        </div>
                        @error('logo') <p class="mt-1 text-eyebrow text-risk">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Color theme --}}
                <div>
                    <label class="field-label !mb-1 !text-eyebrow">Color Theme</label>
                    <div class="flex flex-wrap items-center gap-2">
                        @foreach ($palettes as $key => [$label, $primary, $secondary, $accent, $text])
                            <button type="button" wire:click="usePalette('{{ $key }}')" title="{{ $label }}"
                                    @class([
                                        'flex items-center gap-1.5 rounded-xl border px-2 py-1.5 transition',
                                        'border-gold-500 ring-1 ring-gold-300' => $primary_color === $primary && $accent_color === $accent,
                                        'border-line hover:border-gold-300' => ! ($primary_color === $primary && $accent_color === $accent),
                                    ])>
                                <span class="flex gap-0.5">
                                    <span class="h-4 w-4 rounded ring-1 ring-line" style="background: {{ $primary }}"></span>
                                    <span class="h-4 w-4 rounded ring-1 ring-line" style="background: {{ $accent }}"></span>
                                </span>
                                <span class="text-eyebrow font-semibold text-navy-800">{{ str($label)->before(' +') }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Modules ── --}}
        <div id="s-modules" class="card scroll-mt-32 p-6">
            <div class="mb-5 flex items-baseline gap-2.5 border-b border-line pb-2">
                <span class="pf text-xl font-bold leading-none text-gold-400/50">05</span>
                <h3 class="pf text-base font-bold text-navy-900">Enabled Modules</h3>
            </div>
            <p class="mb-1 text-xs text-muted">Turn tabs on or off for this event's control room. Every module is available to every event.</p>
            <p class="mb-4 flex items-center gap-1.5 text-micro font-semibold text-emerald-700">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                Module changes apply immediately — no need to press Save.
            </p>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($hubModules as $key => [$label, $category, $icon])
                    @php $on = in_array($key, $modules, true); @endphp
                    <button type="button" wire:click="toggleModule('{{ $key }}')"
                            @class([
                                'flex items-center gap-3 rounded-2xl border p-3 text-left transition',
                                'border-gold-300 bg-gold-50/40 shadow-sm' => $on,
                                'border-line bg-white hover:border-navy-200' => ! $on,
                            ])>
                        <span @class([
                            'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg transition',
                            'bg-gradient-to-br from-navy-800 to-navy-950 text-gold-400' => $on,
                            'bg-navy-50 text-navy-400' => ! $on,
                        ])><x-icon :name="$icon" class="h-4 w-4" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-xs font-bold {{ $on ? 'text-navy-900' : 'text-navy-500' }}">{{ $label }}</span>
                            <span class="block text-eyebrow {{ $on ? 'text-gold-700' : 'text-muted' }}">{{ $category }}</span>
                        </span>
                        <span @class(['relative flex h-5 w-9 shrink-0 items-center rounded-full transition', 'bg-navy-900' => $on, 'bg-navy-200' => ! $on])>
                            <span class="absolute h-4 w-4 rounded-full bg-white shadow transition-all {{ $on ? 'left-[18px]' : 'left-0.5' }}"></span>
                        </span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- sticky save bar --}}
        <div class="sticky bottom-4 z-10 flex items-center justify-between gap-3 rounded-2xl bg-navy-950 px-5 py-3 shadow-[0_18px_45px_-18px_rgba(11,31,58,0.7)] ring-1 ring-white/10">
            <p class="text-micro text-white/50">
                <span wire:loading.remove>Changes are saved when you press Save.</span>
                <span wire:loading class="text-gold-300">Saving…</span>
            </p>
            <button type="submit" class="flex h-9 items-center rounded-xl bg-gradient-to-r from-gold-400 to-gold-600 px-5 text-xs font-bold text-navy-950 shadow transition hover:brightness-105">Save Settings</button>
        </div>
    </form>

    {{-- ── Danger zone ── --}}
    <div id="s-manage" class="card scroll-mt-32 border-risk/20 p-6">
        <div class="mb-5 flex items-baseline gap-2.5 border-b border-line pb-2">
            <span class="pf text-xl font-bold leading-none text-gold-400/50">06</span>
            <h3 class="pf text-base font-bold text-navy-900">Manage Event</h3>
        </div>
        <div class="flex flex-wrap gap-3">
            <button type="button" wire:click="duplicate" class="rounded-xl border border-line bg-white px-4 py-2.5 text-xs font-semibold text-navy-700 transition hover:border-gold-300">⧉ Duplicate event</button>
            <button type="button" wire:click="archive" wire:confirm="Archive “{{ $event->name }}”? It disappears from lists and the Operations Hub (recoverable)."
                    class="rounded-xl border border-risk/30 bg-risk/5 px-4 py-2.5 text-xs font-semibold text-risk transition hover:bg-risk/10">⌫ Archive event</button>
        </div>
    </div>
        </div>
        </div>
    </div>
</div>
