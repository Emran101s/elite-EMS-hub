<div class="space-y-6">
    <form wire:submit="save" class="space-y-6">

        {{-- ── Event details ── --}}
        <div class="card p-6">
            <h3 class="mb-4 text-xs font-bold uppercase tracking-wide text-navy-900">Event Details</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="field-label !mb-1 !text-[0.62rem]" for="st-name">Event name</label>
                    <input id="st-name" type="text" wire:model="name" class="input h-10 text-sm">
                    @error('name') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="field-label !mb-1 !text-[0.62rem]" for="st-desc">Description</label>
                    <textarea id="st-desc" wire:model="description" rows="2" class="input text-sm" placeholder="Scope, audience, objectives…"></textarea>
                </div>
                <div>
                    <label class="field-label !mb-1 !text-[0.62rem]" for="st-client">Client</label>
                    <select id="st-client" wire:model="client_id" class="input h-10 text-sm">
                        <option value="">— Select client —</option>
                        @foreach ($clients as $client)<option value="{{ $client->id }}">{{ $client->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label !mb-1 !text-[0.62rem]" for="st-newclient">…or new client</label>
                    <input id="st-newclient" type="text" wire:model="new_client" class="input h-10 text-sm" placeholder="Creates on save">
                </div>
                <div>
                    <label class="field-label !mb-1 !text-[0.62rem]" for="st-part">Expected participants</label>
                    <input id="st-part" type="number" min="0" wire:model="expected_participants" class="input h-10 text-sm" placeholder="e.g. 400">
                    @error('expected_participants') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="field-label !mb-1 !text-[0.62rem]" for="st-budget">Budget (USD)</label>
                    <input id="st-budget" type="number" step="0.01" min="0" wire:model="budget" class="input h-10 text-sm" placeholder="250000">
                </div>
                <div>
                    <label class="field-label !mb-1 !text-[0.62rem]" for="st-city">City</label>
                    <input id="st-city" type="text" wire:model="city" class="input h-10 text-sm" placeholder="Amman">
                    @error('city') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="field-label !mb-1 !text-[0.62rem]" for="st-country">Country</label>
                    <select id="st-country" wire:model="country" class="input h-10 text-sm">
                        @foreach (['Jordan', 'Bahrain', 'UAE', 'Qatar', 'KSA', 'Kuwait', 'Oman', 'Egypt'] as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label !mb-1 !text-[0.62rem]" for="st-start">Start date</label>
                    <input id="st-start" type="date" wire:model.live="starts_at" class="input h-10 text-sm">
                    @error('starts_at') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="field-label !mb-1 !text-[0.62rem]" for="st-end">End date</label>
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
        <div class="card p-6">
            <h3 class="mb-4 text-xs font-bold uppercase tracking-wide text-navy-900">Ownership & Lifecycle</h3>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="field-label !mb-1 !text-[0.62rem]" for="st-pm">Project manager</label>
                    <select id="st-pm" wire:model="project_manager_id" class="input h-10 text-sm">
                        <option value="">— Unassigned —</option>
                        @foreach ($managers as $manager)<option value="{{ $manager->id }}">{{ $manager->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label !mb-1 !text-[0.62rem]" for="st-venue">Venue</label>
                    <select id="st-venue" wire:model="venue_id" class="input h-10 text-sm">
                        <option value="">— Not assigned —</option>
                        @foreach ($venues as $venue)<option value="{{ $venue->id }}">{{ $venue->name }} ({{ $venue->city }})</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label !mb-1 !text-[0.62rem]" for="st-stage">Lifecycle stage</label>
                    <select id="st-stage" wire:model="stage" class="input h-10 text-sm">
                        @foreach (\App\Models\Event::STAGES as $s)<option value="{{ $s }}">{{ str($s)->replace('_', ' ')->title() }}</option>@endforeach
                    </select>
                </div>
            </div>
            <p class="mt-2 text-[0.65rem] text-muted">Rooms are managed in the <a href="{{ route('events.hub', [$event, 'tab' => 'venue']) }}" class="font-semibold text-gold-600">Venue tab</a>.</p>
        </div>

        {{-- ── Identity: avatar + theme ── --}}
        <div class="card p-6">
            <h3 class="mb-4 text-xs font-bold uppercase tracking-wide text-navy-900">Avatar & Color Theme</h3>
            <div class="grid gap-5 sm:grid-cols-2">

                {{-- Avatar — elegant combo box --}}
                <div>
                    <label class="field-label !mb-1 !text-[0.62rem]">Event Avatar</label>
                    @php $selectedAvatar = $avatars->firstWhere('id', $avatar_id); @endphp
                    <details class="group relative" wire:key="avatar-dd">
                        <summary class="flex cursor-pointer list-none items-center gap-3 rounded-2xl bg-fill px-3 py-2 transition hover:bg-navy-50 [&::-webkit-details-marker]:hidden">
                            <span class="h-9 w-14 shrink-0 overflow-hidden rounded-lg bg-white ring-1 ring-line">
                                @if ($selectedAvatar)
                                    <x-event-avatar :avatar="$selectedAvatar" :ring="false" size="sm" class="block h-full w-full [&>span]:h-full [&>span]:w-full [&>span]:rounded-none [&>span]:!bg-white" />
                                @endif
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-navy-900">{{ $selectedAvatar?->name ?? 'Choose an avatar…' }}</span>
                                <span class="block truncate text-[0.65rem] text-muted">{{ $selectedAvatar?->subtitle }}</span>
                            </span>
                            <x-icon name="chevron" class="h-4 w-4 shrink-0 text-navy-400 transition group-open:rotate-180" />
                        </summary>
                        <div class="absolute z-30 mt-2 max-h-72 w-full overflow-y-auto rounded-2xl border border-line bg-white p-1.5 shadow-[0_20px_45px_rgba(11,31,58,0.15)]">
                            @foreach ($avatars as $avatar)
                                <button type="button" wire:click="chooseAvatar({{ $avatar->id }})" onclick="this.closest('details').removeAttribute('open')"
                                        @class([
                                            'flex w-full items-center gap-3 rounded-xl px-2 py-1.5 text-left transition',
                                            'bg-gold-50 ring-1 ring-gold-200' => $avatar_id === $avatar->id,
                                            'hover:bg-navy-50' => $avatar_id !== $avatar->id,
                                        ])>
                                    <span class="h-8 w-12 shrink-0 overflow-hidden rounded-lg bg-white ring-1 ring-line">
                                        <x-event-avatar :avatar="$avatar" :ring="false" size="sm" class="block h-full w-full [&>span]:h-full [&>span]:w-full [&>span]:rounded-none [&>span]:!bg-white" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-xs font-semibold text-navy-900">{{ $avatar->name }}</span>
                                        <span class="block truncate text-[0.6rem] text-muted">{{ $avatar->subtitle }}</span>
                                    </span>
                                    @if ($avatar_id === $avatar->id)<span class="shrink-0 text-sm text-gold-600">✓</span>@endif
                                </button>
                            @endforeach
                        </div>
                    </details>
                </div>

                {{-- Color theme --}}
                <div>
                    <label class="field-label !mb-1 !text-[0.62rem]">Color Theme</label>
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
                                <span class="text-[0.62rem] font-semibold text-navy-800">{{ str($label)->before(' +') }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Modules ── --}}
        <div class="card p-6">
            <h3 class="text-xs font-bold uppercase tracking-wide text-navy-900">Enabled Modules</h3>
            <p class="mb-4 text-xs text-muted">Turn tabs on or off for this event's control room.</p>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($hubModules as $key => [$label, $category, $icon])
                    @php $on = in_array($key, $modules, true); @endphp
                    <button type="button" wire:click="toggleModule('{{ $key }}')"
                            class="flex items-center gap-3 rounded-2xl border border-line p-3 text-left transition hover:border-navy-200">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-navy-50 text-navy-500"><x-icon :name="$icon" class="h-4 w-4" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-xs font-bold text-navy-900">{{ $label }}</span>
                            <span class="block text-[0.6rem] text-muted">{{ $category }}</span>
                        </span>
                        <span @class(['relative flex h-5 w-9 shrink-0 items-center rounded-full transition', 'bg-navy-900' => $on, 'bg-navy-200' => ! $on])>
                            <span class="absolute h-4 w-4 rounded-full bg-white shadow transition-all {{ $on ? 'left-[18px]' : 'left-0.5' }}"></span>
                        </span>
                    </button>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn-gold h-10 px-6 text-sm">Save Settings</button>
        </div>
    </form>

    {{-- ── Danger zone ── --}}
    <div class="card border-risk/20 p-6">
        <h3 class="mb-4 text-xs font-bold uppercase tracking-wide text-navy-900">Manage Event</h3>
        <div class="flex flex-wrap gap-3">
            <button type="button" wire:click="duplicate" class="rounded-xl border border-line bg-white px-4 py-2.5 text-xs font-semibold text-navy-700 transition hover:border-gold-300">⧉ Duplicate event</button>
            <button type="button" wire:click="archive" wire:confirm="Archive “{{ $event->name }}”? It disappears from lists and the Operations Hub (recoverable)."
                    class="rounded-xl border border-risk/30 bg-risk/5 px-4 py-2.5 text-xs font-semibold text-risk transition hover:bg-risk/10">⌫ Archive event</button>
        </div>
    </div>
</div>
