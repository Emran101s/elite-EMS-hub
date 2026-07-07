<div class="space-y-6">
    <form wire:submit="save" class="space-y-6">

        {{-- ── Event details ── --}}
        <div class="card p-6">
            <h3 class="mb-4 text-xs font-bold uppercase tracking-wide text-navy-900">Event Details</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-navy-800" for="st-name">Event name</label>
                    <input id="st-name" type="text" wire:model="name" class="input h-10 text-sm">
                    @error('name') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-navy-800" for="st-desc">Description</label>
                    <textarea id="st-desc" wire:model="description" rows="2" class="input text-sm" placeholder="Scope, audience, objectives…"></textarea>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-navy-800" for="st-client">Client</label>
                    <select id="st-client" wire:model="client_id" class="input h-10 text-sm">
                        <option value="">— Select client —</option>
                        @foreach ($clients as $client)<option value="{{ $client->id }}">{{ $client->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-navy-800" for="st-newclient">…or new client</label>
                    <input id="st-newclient" type="text" wire:model="new_client" class="input h-10 text-sm" placeholder="Creates on save">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-navy-800" for="st-part">Expected participants</label>
                    <input id="st-part" type="number" min="0" wire:model="expected_participants" class="input h-10 text-sm" placeholder="e.g. 400">
                    @error('expected_participants') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-navy-800" for="st-budget">Budget (USD)</label>
                    <input id="st-budget" type="number" step="0.01" min="0" wire:model="budget" class="input h-10 text-sm" placeholder="250000">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-navy-800" for="st-city">City</label>
                    <input id="st-city" type="text" wire:model="city" class="input h-10 text-sm" placeholder="Amman">
                    @error('city') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-navy-800" for="st-country">Country</label>
                    <select id="st-country" wire:model="country" class="input h-10 text-sm">
                        @foreach (['Jordan', 'Bahrain', 'UAE', 'Qatar', 'KSA', 'Kuwait', 'Oman', 'Egypt'] as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-navy-800" for="st-start">Start date</label>
                    <input id="st-start" type="date" wire:model="starts_at" class="input h-10 text-sm">
                    @error('starts_at') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-navy-800" for="st-end">End date</label>
                    <input id="st-end" type="date" wire:model="ends_at" class="input h-10 text-sm">
                    @error('ends_at') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- ── Ownership ── --}}
        <div class="card p-6">
            <h3 class="mb-4 text-xs font-bold uppercase tracking-wide text-navy-900">Ownership & Lifecycle</h3>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-navy-800" for="st-pm">Project manager</label>
                    <select id="st-pm" wire:model="project_manager_id" class="input h-10 text-sm">
                        <option value="">— Unassigned —</option>
                        @foreach ($managers as $manager)<option value="{{ $manager->id }}">{{ $manager->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-navy-800" for="st-venue">Venue</label>
                    <select id="st-venue" wire:model="venue_id" class="input h-10 text-sm">
                        <option value="">— Not assigned —</option>
                        @foreach ($venues as $venue)<option value="{{ $venue->id }}">{{ $venue->name }} ({{ $venue->city }})</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-navy-800" for="st-stage">Lifecycle stage</label>
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
            <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 xl:grid-cols-6">
                @foreach ($avatars as $avatar)
                    <button type="button" wire:click="chooseAvatar({{ $avatar->id }})"
                            @class([
                                'relative rounded-2xl border-2 p-1.5 text-left transition',
                                'border-gold-500 bg-[#FFF7E6]' => $avatar_id === $avatar->id,
                                'border-line hover:border-gold-300' => $avatar_id !== $avatar->id,
                            ])>
                        @if ($avatar_id === $avatar->id)<span class="absolute -right-2 -top-2 z-10 flex h-5 w-5 items-center justify-center rounded-full bg-gold-500 text-[0.6rem] font-bold text-navy-900">✓</span>@endif
                        <x-event-avatar :avatar="$avatar" :ring="false" size="md" class="w-full [&>span]:h-16 [&>span]:w-full [&>span]:rounded-xl" />
                        <p class="mt-1 truncate text-[0.62rem] font-bold text-navy-900">{{ $avatar->name }}</p>
                    </button>
                @endforeach
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-2.5">
                <span class="text-[0.65rem] font-bold uppercase tracking-wide text-muted">Theme</span>
                @foreach ($palettes as $key => [$label, $primary, $secondary, $accent, $text])
                    <button type="button" wire:click="usePalette('{{ $key }}')"
                            @class([
                                'flex items-center gap-1.5 rounded-xl border px-2.5 py-1.5 transition',
                                'border-gold-500 ring-1 ring-gold-300' => $primary_color === $primary && $accent_color === $accent,
                                'border-line hover:border-gold-300' => ! ($primary_color === $primary && $accent_color === $accent),
                            ])>
                        <span class="flex gap-0.5">
                            <span class="h-4 w-4 rounded ring-1 ring-line" style="background: {{ $primary }}"></span>
                            <span class="h-4 w-4 rounded ring-1 ring-line" style="background: {{ $accent }}"></span>
                        </span>
                        <span class="text-[0.65rem] font-semibold text-navy-800">{{ $label }}</span>
                    </button>
                @endforeach
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
