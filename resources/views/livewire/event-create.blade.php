<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">

    <div class="min-w-0">
        {{-- Step indicator --}}
        <div class="mb-5 flex items-center gap-2">
            @foreach (['Basics', 'Avatar', 'Color Theme', 'Review'] as $i => $label)
                <div class="flex items-center gap-2">
                    <span @class([
                            'flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold',
                            'bg-gold-500 text-navy-900' => $step === $i + 1,
                            'bg-navy-900 text-gold-400' => $step > $i + 1,
                            'bg-white text-muted ring-1 ring-line' => $step < $i + 1,
                        ])>{{ $step > $i + 1 ? '✓' : $i + 1 }}</span>
                    <span class="{{ $step === $i + 1 ? 'font-bold text-navy-900' : 'text-muted' }} hidden text-xs sm:block">{{ $label }}</span>
                    @unless ($loop->last)<span class="h-px w-6 bg-line"></span>@endunless
                </div>
            @endforeach
        </div>

        <div class="card p-6">

            {{-- ══ Step 1: Basics ══ --}}
            @if ($step === 1)
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="name" class="mb-1.5 block text-sm font-medium text-navy-800">Event name</label>
                        <input id="name" type="text" wire:model="name" class="input" placeholder="e.g. ICFT 2027">
                        @error('name') <p class="mt-1 text-sm text-risk">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="type" class="mb-1.5 block text-sm font-medium text-navy-800">Event type</label>
                        <select id="type" wire:model.live="type" class="input">
                            @foreach ($types as $eventType)
                                <option value="{{ $eventType }}">{{ str($eventType)->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="expected_participants" class="mb-1.5 block text-sm font-medium text-navy-800">Expected participants</label>
                        <input id="expected_participants" type="number" min="1" wire:model="expected_participants" class="input" placeholder="500">
                        @error('expected_participants') <p class="mt-1 text-sm text-risk">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="client_id" class="mb-1.5 block text-sm font-medium text-navy-800">Client</label>
                        <select id="client_id" wire:model="client_id" class="input">
                            <option value="">— Select client —</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}">{{ $client->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="new_client" class="mb-1.5 block text-sm font-medium text-navy-800">…or new client name</label>
                        <input id="new_client" type="text" wire:model="new_client" class="input" placeholder="Creates the client on save">
                    </div>

                    <div class="sm:col-span-2">
                        <label for="description" class="mb-1.5 block text-sm font-medium text-navy-800">Description <span class="text-muted">(optional)</span></label>
                        <textarea id="description" wire:model="description" rows="2" class="input" placeholder="Scope, audience, objectives…"></textarea>
                    </div>

                    <div>
                        <label for="city" class="mb-1.5 block text-sm font-medium text-navy-800">City</label>
                        <input id="city" type="text" wire:model="city" class="input" placeholder="Amman">
                        @error('city') <p class="mt-1 text-sm text-risk">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="country" class="mb-1.5 block text-sm font-medium text-navy-800">Country</label>
                        <select id="country" wire:model="country" class="input">
                            @foreach (['Jordan', 'Bahrain', 'UAE', 'Qatar', 'KSA', 'Kuwait', 'Oman', 'Egypt'] as $countryOption)
                                <option value="{{ $countryOption }}">{{ $countryOption }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="starts_at" class="mb-1.5 block text-sm font-medium text-navy-800">Starts</label>
                        <input id="starts_at" type="date" wire:model="starts_at" class="input">
                        @error('starts_at') <p class="mt-1 text-sm text-risk">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="ends_at" class="mb-1.5 block text-sm font-medium text-navy-800">Ends</label>
                        <input id="ends_at" type="date" wire:model="ends_at" class="input">
                        @error('ends_at') <p class="mt-1 text-sm text-risk">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="budget" class="mb-1.5 block text-sm font-medium text-navy-800">Budget (USD)</label>
                        <input id="budget" type="number" step="0.01" min="0" wire:model="budget" class="input" placeholder="250000">
                        @error('budget') <p class="mt-1 text-sm text-risk">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="project_manager_id" class="mb-1.5 block text-sm font-medium text-navy-800">Project manager</label>
                        <select id="project_manager_id" wire:model="project_manager_id" class="input">
                            <option value="">—</option>
                            @foreach ($managers as $manager)
                                <option value="{{ $manager->id }}">{{ $manager->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="venue_id" class="mb-1.5 block text-sm font-medium text-navy-800">Venue <span class="text-muted">(optional)</span></label>
                        <select id="venue_id" wire:model="venue_id" class="input">
                            <option value="">—</option>
                            @foreach ($venues as $venue)
                                <option value="{{ $venue->id }}">{{ $venue->name }} ({{ $venue->city }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="project_id" class="mb-1.5 block text-sm font-medium text-navy-800">Project <span class="text-muted">(optional)</span></label>
                        <select id="project_id" wire:model="project_id" class="input">
                            <option value="">—</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}">{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @endif

            {{-- ══ Step 2: Avatar ══ --}}
            @if ($step === 2)
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-navy-900">Choose the Event Avatar</p>
                        <p class="text-xs text-muted">Auto-suggested from the event type — pick any to override.</p>
                    </div>
                    <a href="{{ route('events.avatars') }}" target="_blank" class="text-xs font-semibold text-gold-600 hover:text-gold-700">Browse library</a>
                </div>
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
                    @foreach ($avatars as $avatar)
                        <button type="button" wire:click="chooseAvatar({{ $avatar->id }})"
                                @class([
                                    'relative rounded-2xl border p-2 text-left transition',
                                    'border-gold-500 ring-2 ring-gold-500/40' => $avatar_id === $avatar->id,
                                    'border-line hover:border-gold-300' => $avatar_id !== $avatar->id,
                                ])>
                            @if ($avatar->id === $recommendedId)
                                <span class="absolute -top-2 left-3 z-10 rounded-full bg-gold-500 px-2 py-0.5 text-[0.6rem] font-bold text-navy-900">Recommended</span>
                            @endif
                            <x-event-avatar :avatar="$avatar" :ring="false" size="md" class="w-full [&>span]:h-20 [&>span]:w-full" />
                            <p class="mt-2 truncate text-xs font-bold text-navy-900">{{ $avatar->name }}</p>
                            <p class="truncate text-[0.65rem] text-muted">{{ $avatar->subtitle }}</p>
                        </button>
                    @endforeach
                </div>
                @error('avatar_id') <p class="mt-2 text-sm text-risk">{{ $message }}</p> @enderror
            @endif

            {{-- ══ Step 3: Color theme ══ --}}
            @if ($step === 3)
                <p class="text-sm font-bold text-navy-900">Event Color Theme</p>
                <p class="mb-4 text-xs text-muted">Used on the hub cover, event cards, rings, agenda blocks and report covers.</p>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach ($palettes as $key => [$label, $primary, $secondary, $accent, $text])
                        <button type="button" wire:click="$set('palette', '{{ $key }}')"
                                @class([
                                    'rounded-2xl border p-3 text-left transition',
                                    'border-gold-500 ring-2 ring-gold-500/40' => $palette === $key,
                                    'border-line hover:border-gold-300' => $palette !== $key,
                                ])>
                            <span class="flex gap-1.5">
                                <span class="h-6 w-6 rounded-lg ring-1 ring-line" style="background: {{ $primary }}"></span>
                                <span class="h-6 w-6 rounded-lg ring-1 ring-line" style="background: {{ $accent }}"></span>
                                <span class="h-6 w-6 rounded-lg ring-1 ring-line" style="background: {{ $secondary }}"></span>
                            </span>
                            <span class="mt-2 block text-xs font-semibold text-navy-900">{{ $label }}</span>
                        </button>
                    @endforeach

                    <div @class([
                            'rounded-2xl border p-3',
                            'border-gold-500 ring-2 ring-gold-500/40' => $palette === 'custom',
                            'border-line' => $palette !== 'custom',
                        ])>
                        <button type="button" wire:click="$set('palette', 'custom')" class="text-xs font-semibold text-navy-900">Custom brand colors</button>
                        <div class="mt-2 grid grid-cols-4 gap-1.5">
                            @foreach ([['primary_color', 'Primary'], ['secondary_color', 'Surface'], ['accent_color', 'Accent'], ['text_color', 'Text']] as [$field, $label])
                                <label class="block">
                                    <input type="color" wire:model.live="{{ $field }}" class="h-8 w-full cursor-pointer rounded-lg border border-line" title="{{ $label }}">
                                    <span class="mt-0.5 block text-center text-[0.55rem] text-muted">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Live theme preview --}}
                <div class="mt-5 overflow-hidden rounded-2xl ring-1 ring-line">
                    <div class="flex items-center gap-4 px-5 py-4" style="background: linear-gradient(100deg, {{ $primary_color }} 0%, {{ $primary_color }}E6 70%, {{ $accent_color }}33 100%)">
                        @php $selectedAvatar = $avatars->firstWhere('id', $avatar_id); @endphp
                        <x-event-avatar :avatar="$selectedAvatar" :ring="false" size="sm" />
                        <div>
                            <p class="text-sm font-bold text-white">{{ $name !== '' ? $name : 'Your event' }} — Event Hub</p>
                            <p class="text-[0.65rem]" style="color: {{ $accent_color }}">{{ str($type)->replace('_', ' ')->title() }} · {{ $city !== '' ? $city : 'City' }}, {{ $country }}</p>
                        </div>
                    </div>
                    <div class="px-5 py-3 text-xs" style="background: {{ $secondary_color }}; color: {{ $text_color }}">
                        Cover band, accents and rings will use this palette across the hub and reports.
                    </div>
                </div>
            @endif

            {{-- ══ Step 4: Review ══ --}}
            @if ($step === 4)
                @php $selectedAvatar = $avatars->firstWhere('id', $avatar_id); @endphp
                <p class="text-sm font-bold text-navy-900">Review & Create</p>
                <p class="mb-4 text-xs text-muted">Venue rooms, agenda, team, detailed budget, suppliers and sponsors are managed inside the hub after creation.</p>

                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    @foreach ([
                        'Event' => $name ?: '—',
                        'Type' => str($type)->replace('_', ' ')->title(),
                        'Client' => $clients->firstWhere('id', $client_id)?->name ?? ($new_client ?: '—'),
                        'Avatar' => $selectedAvatar?->name ?? '—',
                        'Location' => trim("{$city}, {$country}", ' ,'),
                        'Dates' => trim("{$starts_at} → {$ends_at}", ' →'),
                        'Budget' => $budget !== '' ? '$'.number_format((float) $budget) : '—',
                        'Project manager' => $managers->firstWhere('id', $project_manager_id)?->name ?? '—',
                        'Participants' => $expected_participants !== '' ? $expected_participants : '—',
                        'Venue' => $venues->firstWhere('id', $venue_id)?->name ?? 'Set inside the hub',
                    ] as $label => $value)
                        <div class="flex justify-between gap-4 border-b border-line pb-2">
                            <dt class="text-muted">{{ $label }}</dt>
                            <dd class="text-right font-semibold text-navy-900">{{ $value }}</dd>
                        </div>
                    @endforeach
                    <div class="flex items-center justify-between gap-4 border-b border-line pb-2">
                        <dt class="text-muted">Color theme</dt>
                        <dd class="flex gap-1.5">
                            @foreach ([$primary_color, $accent_color, $secondary_color, $text_color] as $color)
                                <span class="h-4 w-4 rounded-full ring-1 ring-line" style="background: {{ $color }}"></span>
                            @endforeach
                        </dd>
                    </div>
                </dl>
            @endif

            {{-- ══ Navigation ══ --}}
            <div class="mt-6 flex items-center justify-between border-t border-line pt-5">
                <div>
                    @if ($step > 1)
                        <button type="button" wire:click="back" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-navy-600 hover:text-navy-900">← Back</button>
                    @else
                        <a href="{{ route('events.index') }}" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-navy-600 hover:text-navy-900">Cancel</a>
                    @endif
                </div>
                <div>
                    @if ($step < 4)
                        <button type="button" wire:click="next" class="btn-navy">Continue →</button>
                    @else
                        <button type="button" wire:click="save" class="btn-gold">
                            <span wire:loading.remove wire:target="save">✦ Create Event Hub</span>
                            <span wire:loading wire:target="save">Creating…</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ── Island preview rail ── --}}
    <div class="space-y-4">
        <div class="card p-5">
            <h3 class="mb-4 text-xs font-bold uppercase tracking-wide text-navy-900">Island Preview</h3>
            @php $selected = $avatars->firstWhere('id', $avatar_id); @endphp
            <div class="flex flex-col items-center rounded-2xl py-6"
                 style="background: radial-gradient(ellipse at center, {{ $accent_color }}14, transparent 70%)">
                <span class="rounded-2xl p-1" style="box-shadow: 0 0 0 3px {{ $accent_color }}55">
                    <x-event-avatar :avatar="$selected" :ring="false" size="xl" />
                </span>
                <p class="mt-4 text-sm font-bold text-navy-900">{{ $name !== '' ? $name : 'Your event' }}</p>
                <p class="text-xs text-muted">
                    {{ str($type)->replace('_', ' ')->title() }}
                    @if ($city !== '') · {{ $city }}, {{ $country }} @endif
                </p>
                @if ($selected)
                    <p class="mt-2 text-[0.65rem] text-muted">{{ $selected->name }} — {{ $selected->subtitle }}</p>
                @endif
            </div>
            <p class="mt-3 text-[0.65rem] text-muted">
                Live preview of the Operations Hub island — avatar, theme ring and (once live) the health score.
            </p>
        </div>
    </div>
</div>
