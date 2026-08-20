@php
    // What the preview shows before you have said anything. Placeholders, never
    // invented facts — an empty studio should look empty, not pre-filled.
    $pName = $name !== '' ? $name : 'Untitled event';
    $pClient = $client_id ? optional($clients->firstWhere('id', $client_id))->name : (trim($new_client) ?: null);
    $pVenue = $venue_id ? optional($venues->firstWhere('id', $venue_id))->name : null;
    $pWhere = collect([$pVenue, $city, $country])->filter()->take(2)->implode(', ');
    $pStageKey = \App\Livewire\EventCreate::ORIGIN_STAGE[$originSource] ?? 'draft';
    $pStage = \App\Support\Workflow::label('event_stage', $pStageKey);
    $pStageHex = \App\Models\Event::stageColor($pStageKey);
    [$pPriorityLabel, $pPriorityHex] = $priorities[$priority] ?? $priorities['normal'];
    [$pVipLabel, $pVipHex] = $vipLevels[$vipLevel] ?? $vipLevels['standard'];
    $cur = $currency ?: $defaultCurrency;
    $curSymbol = \App\Models\Event::CURRENCIES[$cur][0] ?? '';
    $originOptions = $originKind === 'internal' ? $originInternal : $originCommercial;

    $pDates = $starts_at
        ? \Illuminate\Support\Carbon::parse($starts_at)->format('j M')
            . ($ends_at ? ' – ' . \Illuminate\Support\Carbon::parse($ends_at)->format('j, Y') : ', ' . \Illuminate\Support\Carbon::parse($starts_at)->format('Y'))
        : 'Dates not set';
@endphp

<div class="eo-event-atmosphere space-y-5 rounded-[24px]">

    <x-eo.page-header
        eyebrow="New mission · Elite Orbit"
        title="Event Studio"
        subtitle="Define your event. The platform builds everything around it."
    >
        <x-slot:actions>
            <span class="eo-journey-chip">Mission Builder</span>
            <x-eo.button variant="ghost" size="sm" href="{{ route('events.index') }}">Start from existing</x-eo.button>
            <x-eo.button variant="secondary" size="sm" href="{{ route('events.index') }}">← Portfolio</x-eo.button>
        </x-slot:actions>
    </x-eo.page-header>

    {{-- Five rooms — Soft Command step rail --}}
    <x-eo.soft-card class="overflow-hidden p-0" :padding="false">
        <div class="scrollbar-none flex items-stretch overflow-x-auto">
            @foreach ($steps as $n => [$label, $note])
                @php $done = $n < $step; $on = $n === $step; @endphp
                <button type="button" wire:click="goTo({{ $n }})" @disabled($n > $step && ! $done)
                        @class([
                            'group relative flex min-w-[168px] flex-1 items-center gap-2.5 px-4 py-3.5 text-left transition',
                            'bg-eo-teal-soft/50 text-eo-text' => $on,
                            'text-eo-muted hover:bg-eo-workspace' => ! $on,
                            'cursor-not-allowed opacity-45' => $n > $step,
                        ])>
                    <span @class([
                        'grid h-7 w-7 shrink-0 place-items-center rounded-xl text-[11px] font-black transition',
                        'bg-gradient-to-b from-eo-teal-lit to-eo-teal-deep text-white shadow-eo-teal' => $on,
                        'bg-eo-ok text-white' => $done,
                        'bg-eo-bg text-eo-muted' => ! $on && ! $done,
                    ])>{{ $done ? '✓' : $n }}</span>

                    <span class="min-w-0">
                        <span class="block truncate text-[12.5px] font-bold">{{ $label }}</span>
                        @if ($on)
                            <span class="mt-0.5 block truncate text-[10.5px] text-eo-muted">{{ $note }}</span>
                        @endif
                    </span>

                    @if ($on)
                        <span class="absolute inset-x-3 -bottom-px h-[2.5px] rounded-full bg-eo-teal"></span>
                    @endif
                </button>
            @endforeach
        </div>
    </x-eo.soft-card>

    {{-- ══════════ ZONE 1 · WORKSPACE ── ZONE 2 · LIVE PREVIEW ══════════ --}}
    <div class="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.86fr)]">

        {{-- ─────────── ZONE 1 ─────────── --}}
        <div class="eo-domain-card min-w-0 p-5 lg:p-6">
            <div class="mb-5 flex items-center gap-2.5">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-eo-navy text-[12px] font-black text-eo-gold-soft">{{ $step }}</span>
                <div class="min-w-0">
                    <h2 class="eo-font text-[17px] font-bold text-eo-text">{{ $steps[$step][0] }}</h2>
                    <p class="text-[11.5px] text-eo-muted">{{ $steps[$step][1] }}</p>
                </div>
            </div>

            {{-- ══ 1 · IDENTITY ══ --}}
            @if ($step === 1)
                <div class="space-y-4">
                    <div>
                        <div class="mb-1.5 flex items-baseline justify-between">
                            <label class="eo-field-label !mb-0" for="s-name">Event name <span class="text-eo-risk-ink">*</span></label>
                            <span class="text-[10.5px] tabular-nums text-eo-muted">{{ mb_strlen($name) }}/120</span>
                        </div>
                        <input id="s-name" type="text" wire:model.live.debounce.250ms="name" maxlength="120"
                               class="eo-input h-12 !text-[17px] font-bold text-eo-text" placeholder="Arab Investment Summit 2027">
                        @error('name') <p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="eo-field-label">Category <span class="text-eo-risk-ink">*</span></label>
                        <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-4">
                            @foreach ($categories as $key => [$label, $type, $icon, $mods])
                                @php $on = $category === $key; @endphp
                                <button type="button" wire:click="chooseCategory('{{ $key }}')"
                                        @class([
                                            'group relative flex flex-col items-center gap-2 overflow-hidden rounded-2xl border p-3.5 text-center transition',
                                            'border-eo-teal bg-eo-teal-soft/50 shadow-eo-teal' => $on,
                                            'border-eo-line bg-white hover:-translate-y-0.5 hover:border-eo-teal/30 hover:shadow-sm' => ! $on,
                                        ])>
                                    @if ($on)
                                        <span class="absolute inset-y-0 start-0 w-[3px] bg-eo-teal" aria-hidden="true"></span>
                                        <span class="absolute end-2 top-2 grid h-5 w-5 place-items-center rounded-full bg-eo-teal text-[10px] font-black text-white">✓</span>
                                    @endif
                                    <span @class([
                                        'grid h-11 w-11 place-items-center rounded-xl transition',
                                        'bg-eo-teal text-white' => $on,
                                        'bg-eo-workspace text-eo-muted group-hover:bg-eo-teal-soft/60 group-hover:text-eo-teal-ink' => ! $on,
                                    ])>
                                        <x-icon :name="$icon" class="h-5 w-5" />
                                    </span>
                                    <span class="block text-[12.5px] font-bold text-eo-text">{{ $label }}</span>
                                    <span @class([
                                        'mt-0.5 rounded-full px-2 py-px text-[9px] font-bold tabular-nums',
                                        'bg-eo-teal-soft text-eo-teal-ink' => $on,
                                        'bg-eo-workspace text-eo-muted' => ! $on,
                                    ])>{{ count($mods) }} modules</span>
                                </button>
                            @endforeach
                        </div>
                        @error('category') <p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="eo-field-label" for="s-priority">Priority</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3 top-1/2 h-2.5 w-2.5 -translate-y-1/2 rounded-full"
                                      style="background: {{ $pPriorityHex }}"></span>
                                <select id="s-priority" wire:model.live="priority" class="eo-select h-11 !ps-8">
                                    @foreach ($priorities as $key => [$label, $hex])
                                        <option value="{{ $key }}" @selected($key === $priority)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="eo-field-label">Internal or commercial <span class="text-eo-risk-ink">*</span></label>
                            <div class="grid h-11 grid-cols-2 gap-2">
                                <button type="button" wire:click="$set('originKind', 'commercial')"
                                        @class([
                                            'rounded-xl border text-[12px] font-bold transition',
                                            'border-eo-teal bg-eo-teal-soft/50 text-eo-teal-ink' => $originKind === 'commercial',
                                            'border-eo-line bg-white text-eo-muted hover:border-eo-teal/30' => $originKind !== 'commercial',
                                        ])>Commercial</button>
                                <button type="button" wire:click="$set('originKind', 'internal')"
                                        @class([
                                            'rounded-xl border text-[12px] font-bold transition',
                                            'border-eo-teal bg-eo-teal-soft/50 text-eo-teal-ink' => $originKind === 'internal',
                                            'border-eo-line bg-white text-eo-muted hover:border-eo-teal/30' => $originKind !== 'internal',
                                        ])>Internal</button>
                            </div>
                            @error('originKind') <p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <div class="mb-1.5 flex items-baseline justify-between">
                            <label class="eo-field-label !mb-0" for="s-desc">Description</label>
                            <span class="text-[10.5px] tabular-nums text-eo-muted">{{ mb_strlen($description) }}/600</span>
                        </div>
                        <textarea id="s-desc" wire:model.live.debounce.400ms="description" rows="3" maxlength="600"
                                  class="eo-textarea text-sm" placeholder="A premier gathering of investors, leaders and innovators shaping the future of global investment."></textarea>
                        @error('description') <p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p> @enderror
                    </div>
                </div>

            {{-- ══ 2 · ORIGIN ══ --}}
            @elseif ($step === 2)
                <div class="space-y-4">
                    <p class="text-[12px] text-eo-muted">
                        Where this event is coming from decides how it starts out —
                        {{ $originKind === 'internal' ? 'an internal event begins already inside the business.' : 'a commercial event begins with the client it serves.' }}
                    </p>

                    <div>
                        <label class="eo-field-label">{{ $originKind === 'internal' ? 'Internal origin' : 'Commercial origin' }} <span class="text-eo-risk-ink">*</span></label>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                            @foreach ($originOptions as $key => $label)
                                @php $on = $originSource === $key; @endphp
                                <button type="button" wire:click="$set('originSource', '{{ $key }}')"
                                        @class([
                                            'relative flex items-center gap-2.5 overflow-hidden rounded-xl border px-3 py-2.5 text-left transition',
                                            'border-eo-teal bg-eo-teal-soft/50' => $on,
                                            'border-eo-line bg-white hover:border-eo-teal/30' => ! $on,
                                        ])>
                                    @if ($on)
                                        <span class="absolute inset-y-0 start-0 w-[3px] bg-eo-teal" aria-hidden="true"></span>
                                    @endif
                                    <span class="min-w-0 flex-1 text-[12.5px] font-bold text-eo-text">{{ $label }}</span>
                                    <span @class(['text-[13px] font-black', 'text-eo-teal-ink' => $on, 'text-eo-line' => ! $on])>{{ $on ? '✓' : '' }}</span>
                                </button>
                            @endforeach
                        </div>
                        @error('originSource') <p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p> @enderror
                    </div>

                    @if ($originKind === 'commercial')
                        <div>
                            <label class="eo-field-label" for="s-client">Client <span class="text-eo-risk-ink">*</span></label>
                            @if ($newClientMode)
                                <input id="s-client" type="text" wire:model.live.debounce.300ms="new_client" class="eo-input h-11" placeholder="New client name">
                            @else
                                <select id="s-client" wire:model.live="client_id" class="eo-select h-11">
                                    <option value="">— Select client —</option>
                                    @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                                </select>
                            @endif
                            <button type="button" wire:click="toggleNewClient" class="mt-1 text-[11px] font-semibold text-eo-teal-ink hover:underline">
                                {{ $newClientMode ? '← Pick an existing client' : '＋ Add a new client' }}
                            </button>
                            @error('client_id') <p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <p class="flex items-center gap-2 rounded-xl bg-eo-teal-soft/40 px-3.5 py-2.5 text-[11.5px] text-eo-teal-ink ring-1 ring-eo-teal/20">
                            <x-icon name="identification" class="h-4 w-4 shrink-0" />
                            Internal events run without a CRM client — the initiative, program or request above is the record of who asked for it.
                        </p>
                    @endif
                </div>

            {{-- ══ 3 · BLUEPRINT ══ --}}
            @elseif ($step === 3)
                <div class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="eo-field-label" for="s-start">Starts <span class="text-eo-risk-ink">*</span></label>
                            <input id="s-start" type="date" wire:model.live="starts_at" class="eo-input h-11">
                            @error('starts_at') <p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="eo-field-label" for="s-end">Ends</label>
                            <input id="s-end" type="date" wire:model.live="ends_at" class="eo-input h-11">
                            @error('ends_at') <p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    @if ($previewDays > 0)
                        <p class="flex items-center gap-2 rounded-xl bg-eo-gold-soft/30 px-3.5 py-2.5 text-[11.5px] text-eo-text ring-1 ring-eo-gold/20">
                            <x-icon name="calendar" class="h-4 w-4 shrink-0 text-eo-gold-ink" />
                            {{ $previewDays }} agenda {{ str('day')->plural($previewDays) }} will be scaffolded the moment this launches.
                        </p>
                    @endif

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="eo-field-label" for="s-venue">Venue</label>
                            <select id="s-venue" wire:model.live="venue_id" class="eo-select h-11">
                                <option value="">— To be confirmed —</option>
                                @foreach ($venues as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="eo-field-label" for="s-city">City</label>
                            <input id="s-city" type="text" wire:model.live.debounce.300ms="city" class="eo-input h-11" placeholder="Amman">
                        </div>
                        <div>
                            <label class="eo-field-label" for="s-country">Country</label>
                            <input id="s-country" type="text" wire:model.live.debounce.300ms="country" class="eo-input h-11" placeholder="Jordan">
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="eo-field-label" for="s-tz">Timezone</label>
                            <select id="s-tz" wire:model.live="timezone" class="eo-select h-11">
                                @foreach ($timezones as $tz)<option value="{{ $tz }}" @selected($tz === $timezone)>{{ $tz }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="eo-field-label" for="s-vip">VIP level</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3 top-1/2 h-2.5 w-2.5 -translate-y-1/2 rounded-full" style="background: {{ $pVipHex }}"></span>
                                <select id="s-vip" wire:model.live="vipLevel" class="eo-select h-11 !ps-8">
                                    @foreach ($vipLevels as $key => [$label, $hex])
                                        <option value="{{ $key }}" @selected($key === $vipLevel)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="eo-field-label" for="s-format">Format</label>
                            <select id="s-format" wire:model.live="format" class="eo-select h-11">
                                @foreach ($formats as $key => [$label, $icon])
                                    <option value="{{ $key }}" @selected($key === $format)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="eo-field-label" for="s-pax">Expected participants</label>
                            <input id="s-pax" type="number" min="0" wire:model.live.debounce.300ms="expected_participants" class="eo-input h-11" placeholder="1200">
                            @error('expected_participants') <p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="eo-field-label" for="s-budget">Budget</label>
                            <input id="s-budget" type="number" step="0.01" min="0" wire:model.live.debounce.300ms="budget" class="eo-input h-11" placeholder="480000">
                            @error('budget') <p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="eo-field-label" for="s-cur">Currency</label>
                            <select id="s-cur" wire:model.live="currency" class="eo-select h-11">
                                @foreach ($currencies as $code => [$symbol, $label])
                                    <option value="{{ $code }}" @selected($code === $currency)>{{ $code }} — {{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="sm:w-2/3">
                        <label class="eo-field-label" for="s-pm">Project manager</label>
                        <select id="s-pm" wire:model.live="project_manager_id" class="eo-select h-11">
                            <option value="">— Assign later —</option>
                            @foreach ($managers as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
                        </select>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="eo-field-label" for="s-cover">Cover image</label>
                            <input id="s-cover" type="file" wire:model="cover" accept="image/*"
                                   class="eo-input h-11 !py-0 text-xs file:mr-3 file:h-full file:rounded-lg file:border-0 file:bg-eo-navy file:px-3 file:text-xs file:font-semibold file:text-white">
                            @error('cover') <p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="eo-field-label" for="s-logo">Client logo</label>
                            <input id="s-logo" type="file" wire:model="logo" accept="image/*"
                                   class="eo-input h-11 !py-0 text-xs file:mr-3 file:h-full file:rounded-lg file:border-0 file:bg-eo-navy file:px-3 file:text-xs file:font-semibold file:text-white">
                            @error('logo') <p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

            {{-- ══ 4 · MODULES ══ --}}
            @elseif ($step === 4)
                <div class="space-y-4">
                    <p class="text-[12px] text-eo-muted">
                        Your category switched {{ count($modules) }} of these on. Every one you keep becomes a tab in the
                        Event Hub; every one you drop stays out of the way until you want it.
                    </p>

                    {{-- preserveKeys: the module key IS the identity here; without it every
                         button posts toggleModule('0') and nothing ever switches on. --}}
                    @foreach (collect($hubModules)->groupBy(fn ($m) => $m[1], true) as $group => $mods)
                        <div>
                            <p class="eo-label mb-2">{{ $group }}</p>
                            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($mods as $key => [$label, $cat, $icon])
                                    @php
                                        $on = in_array($key, $modules, true);
                                        $hex = \App\Models\Event::moduleColor($key);
                                    @endphp
                                    <button type="button" wire:click="toggleModule('{{ $key }}')"
                                            @class([
                                                'relative flex items-center gap-2.5 overflow-hidden rounded-xl border px-3 py-2.5 text-left transition',
                                                'border-eo-navy bg-eo-navy text-white' => $on,
                                                'border-eo-line bg-white text-eo-text hover:border-eo-teal/30' => ! $on,
                                            ])>
                                        @if ($on)
                                            <span class="absolute inset-y-0 start-0 w-[3px]" style="background: {{ $hex }}" aria-hidden="true"></span>
                                        @endif
                                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg"
                                              style="{{ $on ? 'color:'.$hex.';background:'.$hex.'22' : '' }}"
                                              @class(['bg-eo-workspace text-eo-muted' => ! $on])>
                                            <x-icon :name="$icon" class="h-4 w-4" />
                                        </span>
                                        <span class="min-w-0 flex-1 truncate text-[12px] font-bold">{{ $label }}</span>
                                        <span @class(['text-[13px] font-black', 'text-eo-gold-soft' => $on, 'text-eo-line' => ! $on])>{{ $on ? '✓' : '+' }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

            {{-- ══ 5 · LAUNCH ══ --}}
            @else
                <div class="space-y-4">
                    <p class="text-[12px] text-eo-muted">Here is what launching builds. Nothing has been written yet.</p>

                    <div class="divide-y divide-eo-line rounded-2xl border border-eo-line">
                        @foreach ([
                            ['Name', $pName, 1],
                            ['Category', $category !== '' ? $categories[$category][0] : 'Not chosen', 1],
                            ['Priority', $pPriorityLabel, 1],
                            ['Origin', ($originKind !== '' ? ucfirst($originKind) : 'Not chosen') . ($originSource ? ' · '.($originOptions[$originSource] ?? $originSource) : ''), 2],
                            ['Client', $pClient ?: ($originKind === 'internal' ? 'Not applicable' : 'Not chosen'), 2],
                            ['Stage on launch', $pStage, 2],
                            ['Dates', $pDates.($previewDays ? '  ·  '.$previewDays.' '.str('day')->plural($previewDays) : ''), 3],
                            ['Where', $pWhere ?: 'To be confirmed', 3],
                            ['VIP level · format', $pVipLabel.' · '.($formats[$format][0] ?? $format), 3],
                            ['Modules', count($modules).' enabled', 4],
                            ['Headcount', $expected_participants !== '' ? number_format((int) $expected_participants).' expected' : 'Not set', 3],
                            ['Budget', $budget !== '' ? $curSymbol.number_format((float) $budget) : 'Not set', 3],
                        ] as [$term, $value, $room])
                            <div class="flex items-baseline gap-3 px-4 py-2.5">
                                <span class="w-36 shrink-0 text-[11px] font-semibold text-eo-muted">{{ $term }}</span>
                                <span class="min-w-0 flex-1 truncate text-[12.5px] font-bold text-eo-text">{{ $value }}</span>
                                <button type="button" wire:click="goTo({{ $room }})" class="shrink-0 text-[11px] font-semibold text-eo-teal-ink hover:underline">Change</button>
                            </div>
                        @endforeach
                    </div>

                    {{-- Section-by-section status — the same gates the preview's
                         completion summary shows, so the two never disagree. --}}
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        @foreach ($readiness['sections'] as $key => $s)
                            <div class="rounded-xl border border-eo-line bg-eo-workspace px-3 py-2.5 text-center">
                                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-eo-muted">{{ $s['label'] }}</p>
                                <p @class(['mt-1 text-[13px] font-black', 'text-eo-ok-ink' => $s['complete'], 'text-eo-muted' => ! $s['complete']])>
                                    {{ $s['complete'] ? 'Ready' : $s['done'].'/'.$s['total'] }}
                                </p>
                            </div>
                        @endforeach
                    </div>

                    @if ($readiness['missing'])
                        <div class="rounded-2xl border border-eo-gold/30 bg-eo-gold-soft/20 px-4 py-3">
                            <p class="text-[11.5px] font-bold text-eo-text">You can launch without these, and fill them in later:</p>
                            <p class="mt-1 text-[11.5px] text-eo-muted">{{ implode(' · ', $readiness['missing']) }}</p>
                        </div>
                    @endif
                </div>
            @endif

            {{-- ── moving between rooms ── --}}
            <div class="mt-6 flex items-center gap-2 border-t border-eo-line pt-4">
                @if ($step > 1)
                    <button type="button" wire:click="back" class="flex h-10 items-center rounded-xl border border-eo-line bg-white px-4 text-[12px] font-semibold text-eo-text transition hover:border-eo-teal/30">← Back</button>
                @endif

                <p class="ms-auto text-[11px] text-eo-muted">Step {{ $step }} of {{ count($steps) }}</p>

                @if ($step < count($steps))
                    <button type="button" wire:click="next" class="flex h-10 items-center rounded-xl bg-eo-navy px-5 text-[12px] font-bold text-white transition hover:bg-eo-navy-deep">Continue →</button>
                @else
                    {{-- The last room has to end in the act it was leading up to.
                         The launch bar below carries the same button, but a review
                         screen whose only control is "Back" reads as a dead end. --}}
                    <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                            class="flex h-10 items-center gap-2 rounded-xl bg-gradient-to-r from-eo-gold-soft to-eo-gold px-5 text-[12px] font-black text-eo-navy-deep shadow-[0_12px_26px_-16px_rgba(214,174,52,0.6)] transition hover:brightness-105 disabled:opacity-60">
                        <span wire:loading.remove wire:target="save" class="flex items-center gap-1.5">
                            <x-icon name="sparkles" class="h-3.5 w-3.5" /> Launch Event
                        </span>
                        <span wire:loading wire:target="save">Building…</span>
                    </button>
                @endif
            </div>
        </div>

        {{-- ─────────── ZONE 2 · LIVE PREVIEW ───────────
             The innovation, and the reason this is a studio rather than a form:
             the event is on screen the whole time you are deciding about it.
             Every field above writes here on the keystroke. --}}
        <div class="space-y-4 xl:sticky xl:top-4">
            <div class="flex flex-wrap items-center gap-2">
                <p class="eo-label">Live preview</p>
                <span class="flex items-center gap-1.5 text-[10.5px] text-eo-muted">
                    <span class="relative flex h-1.5 w-1.5">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-eo-ok opacity-70"></span>
                        <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-eo-ok"></span>
                    </span>
                    updates as you type
                </span>

                {{-- The same card, without the numbers only your team should
                     see. Enabled once there is something worth showing. --}}
                <button type="button" wire:click="$toggle('asAttendee')" @disabled($name === '')
                        @class([
                            'ms-auto flex h-9 items-center gap-1.5 rounded-xl border px-3 text-[11.5px] font-bold transition disabled:opacity-40',
                            'border-eo-navy bg-eo-navy text-white' => $asAttendee,
                            'border-eo-line bg-white text-eo-text hover:border-eo-gold/30' => ! $asAttendee,
                        ])>
                    Preview as attendee <span aria-hidden="true">↗</span>
                </button>
            </div>

            {{-- Portrait, on the deck's proportion (width ÷ height = 0.66), because
                 this is the same card the Events deck will show once the event
                 exists — a preview that is a different shape is not a preview.
                 The cover takes the slack: everything under it is fixed height,
                 so the ratio holds whatever the column width is. --}}
            {{-- Same Command Pass language as the Events deck: light field,
                 framed emblem, barcode — so the preview is the card you will
                 actually see after launch, not a darker cousin of it. --}}
            <article class="mx-auto flex w-full max-w-[430px] flex-col overflow-hidden rounded-[24px] border border-white/60 bg-white shadow-[0_24px_60px_-38px_rgba(11,19,34,0.45)] ring-1 ring-eo-navy/5"
                     style="aspect-ratio: 66 / 100">
                <div class="relative isolate flex min-h-[150px] flex-1 flex-col overflow-hidden bg-gradient-to-br from-eo-gold-soft/20 via-white to-eo-teal-soft/30">
                    <div class="pointer-events-none absolute inset-0 flex items-center justify-center" aria-hidden="true">
                        <span class="absolute aspect-square h-[80%] rounded-full border border-dashed border-eo-gold/30"></span>
                        <span class="absolute aspect-square h-[54%] rounded-full border border-dashed border-eo-gold/25"></span>
                        <span class="absolute aspect-square h-[30%] rounded-full border border-eo-line"></span>
                    </div>

                    <div class="relative z-10 flex items-start justify-between p-3">
                        <span class="inline-flex rounded-full bg-white px-2.5 py-1 text-[9.5px] font-black uppercase tracking-[0.14em] shadow-sm ring-1 ring-eo-line"
                              style="color: {{ $pStageHex }}">{{ $pStage }}</span>
                        <label for="s-cover-quick" class="flex cursor-pointer items-center gap-1.5 rounded-xl bg-white px-3 py-1.5 text-[11px] font-semibold text-eo-text shadow-sm ring-1 ring-eo-line transition hover:ring-eo-gold/30">
                            <x-icon name="grid" class="h-3.5 w-3.5" /> Edit cover
                        </label>
                        <input id="s-cover-quick" type="file" wire:model="cover" accept="image/*" class="hidden">
                    </div>

                    <div class="relative z-10 flex flex-1 items-center justify-center py-2">
                        <div class="grid h-[104px] w-[104px] shrink-0 place-items-center overflow-hidden rounded-[24px] bg-eo-navy shadow-[0_20px_40px_-18px_rgba(11,19,34,0.35)] ring-[5px] ring-white">
                            @if ($cover)
                                <img src="{{ $cover->temporaryUrl() }}" alt="" class="h-full w-full object-cover" style="object-position: 50% 38%">
                            @elseif ($logo)
                                <img src="{{ $logo->temporaryUrl() }}" alt="" class="h-full w-full object-cover">
                            @else
                                <span class="eo-font text-[28px] font-black text-eo-gold-soft">{{ mb_strtoupper(mb_substr($pClient ?: $pName, 0, 2)) }}</span>
                            @endif
                        </div>
                    </div>

                    @php
                        $barcodeSeed = ($name !== '' ? $name : 'untitled').'|'.($category ?: 'none');
                        $barcode = collect(str_split(substr(sha1($barcodeSeed), 0, 60), 2))
                            ->map(fn ($h) => 22 + (hexdec($h) % 78));
                    @endphp
                    <div class="relative z-10 mb-3 flex h-4 items-end justify-center gap-[2px] px-6" aria-hidden="true">
                        @foreach ($barcode as $h)
                            <span class="w-[2px] rounded-full bg-eo-line" style="height: {{ $h }}%"></span>
                        @endforeach
                    </div>
                    <div class="absolute inset-x-0 bottom-0 h-[3px] bg-gradient-to-r from-transparent via-eo-gold to-transparent opacity-80"></div>
                </div>

                <div class="shrink-0 px-5 pb-4 pt-4">
                    <h3 class="eo-font line-clamp-2 text-[22px] font-black leading-tight {{ $name !== '' ? 'text-eo-text' : 'text-eo-line' }}">{{ $pName }}</h3>
                    @if ($pClient)
                        <p class="mt-1 truncate text-[11.5px] font-semibold text-eo-muted">{{ $pClient }}</p>
                    @endif

                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[11.5px] text-eo-text">
                        <span class="flex items-center gap-1.5"><x-icon name="calendar" class="h-3.5 w-3.5 text-eo-muted" />{{ $pDates }}</span>
                        <span class="flex items-center gap-1.5"><x-icon name="pin" class="h-3.5 w-3.5 text-eo-muted" />{{ $pWhere ?: 'Location to be set' }}</span>
                        @if ($expected_participants !== '')
                            <span class="flex items-center gap-1.5"><x-icon name="users" class="h-3.5 w-3.5 text-eo-muted" />{{ number_format((int) $expected_participants) }} expected</span>
                        @endif
                    </div>

                    <p class="mt-3 line-clamp-3 text-[12px] leading-relaxed {{ trim($description) !== '' ? 'text-eo-text' : 'text-eo-line' }}">
                        {{ trim($description) ?: 'A sentence about the event will appear here as you write it.' }}
                    </p>
                </div>

                {{-- the four figures the event will open with --}}
                @unless ($asAttendee)
                    @php
                        $r = 2 * M_PI * 26;
                        $rings = [
                            ['value' => $readiness['pct'].'%', 'pct' => $readiness['pct'], 'hex' => 'var(--color-eo-gold)', 'label' => 'Progress'],
                            ['value' => count($modules), 'pct' => count($modules) ? min(100, count($modules) / 18 * 100) : 0, 'hex' => 'var(--color-eo-teal)', 'label' => 'Modules'],
                            ['value' => $budget !== '' ? $curSymbol.\Illuminate\Support\Number::abbreviate((float) $budget, 0) : '—', 'pct' => $budget !== '' ? 100 : 0, 'hex' => 'var(--color-eo-ok)', 'label' => 'Budget'],
                            ['value' => $pPriorityLabel, 'pct' => 100, 'hex' => $pPriorityHex, 'label' => 'Priority'],
                        ];
                    @endphp

                    <div class="grid shrink-0 grid-cols-4 divide-x divide-eo-line border-y border-eo-line">
                        @foreach ($rings as $ring)
                            <div class="flex flex-col items-center gap-1.5 px-1.5 py-4">
                                <span class="relative grid h-[62px] w-[62px] place-items-center">
                                    <svg class="h-[62px] w-[62px] -rotate-90" viewBox="0 0 60 60" aria-hidden="true">
                                        <circle cx="30" cy="30" r="26" fill="none" stroke="var(--color-eo-bg)" stroke-width="5" />
                                        <circle cx="30" cy="30" r="26" fill="none" stroke="{{ $ring['hex'] }}" stroke-width="5" stroke-linecap="round"
                                                stroke-dasharray="{{ $r }}" stroke-dashoffset="{{ $r - ($r * $ring['pct'] / 100) }}"
                                                style="transition: stroke-dashoffset .45s cubic-bezier(.22,1,.36,1)" />
                                    </svg>
                                    <span class="eo-font absolute text-[14px] font-black leading-none"
                                          style="color: {{ $ring['label'] === 'Priority' ? $ring['hex'] : 'var(--color-eo-text)' }}">{{ $ring['value'] }}</span>
                                </span>
                                <span class="text-[10px] text-eo-muted">{{ $ring['label'] }}</span>
                            </div>
                        @endforeach
                    </div>

                    {{-- The first thing the platform will put in the diary. Agenda
                         lock is conventionally a month before doors, so the date is
                         known before the event exists. --}}
                    <div class="m-4 shrink-0 rounded-2xl bg-eo-workspace px-4 py-3">
                        <p class="eo-label">Next milestone</p>
                        @if ($milestone)
                            <div class="mt-1 flex flex-wrap items-baseline gap-x-3">
                                <span class="eo-font text-[13.5px] font-bold text-eo-text">{{ $milestone['title'] }}</span>
                                <span class="flex items-center gap-1.5 text-[11.5px] text-eo-muted"><x-icon name="calendar" class="h-3.5 w-3.5 text-eo-muted" />{{ $milestone['due'] }}</span>
                                <span class="ms-auto text-[11.5px] font-bold {{ $milestone['late'] ? 'text-eo-risk-ink' : 'text-eo-gold-ink' }}">{{ $milestone['note'] }}</span>
                            </div>
                        @else
                            <p class="mt-1 text-[12px] text-eo-line">Set a start date and the first milestone lands here.</p>
                        @endif
                    </div>
                @else
                    <div class="shrink-0 border-t border-eo-line px-5 py-4">
                        <p class="eo-label">What an attendee sees</p>
                        <p class="mt-1.5 text-[11.5px] leading-relaxed text-eo-muted">
                            The cover, the name, the dates, the place and the description — and none of
                            the budget, the modules or the priority. This is the card that goes on the
                            registration page.
                        </p>
                    </div>
                @endunless

            </article>

            {{-- ── Launch readiness — section by section, and overall ──
                 The same gates the review room's status row shows, so nothing
                 here can claim progress that room doesn't also agree on. --}}
            <div class="eo-domain-card p-4">
                <div class="mb-3 flex items-center justify-between">
                    <p class="eo-label">Launch readiness</p>
                    <span class="text-[13px] font-black tabular-nums text-eo-teal-ink">{{ $readiness['pct'] }}%</span>
                </div>
                <div class="mb-3.5 h-1.5 overflow-hidden rounded-full bg-eo-bg">
                    <div class="h-full rounded-full bg-gradient-to-r from-eo-teal-deep to-eo-teal-lit" style="width: {{ $readiness['pct'] }}%"></div>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach ($readiness['sections'] as $key => $s)
                        <button type="button" wire:click="goTo({{ ['identity' => 1, 'origin' => 2, 'blueprint' => 3, 'modules' => 4][$key] ?? 1 }})"
                                class="rounded-xl bg-eo-workspace px-2.5 py-2 text-center transition hover:-translate-y-px hover:shadow-sm">
                            <p class="truncate text-[10px] font-bold uppercase tracking-[0.06em] text-eo-muted">{{ $s['label'] }}</p>
                            @if ($s['complete'])
                                <x-eo.status-pill tone="ok" class="mt-1 !text-[9px]">Ready</x-eo.status-pill>
                            @else
                                <x-eo.status-pill tone="pending" class="mt-1 !text-[9px]">{{ $s['done'] }}/{{ $s['total'] }}</x-eo.status-pill>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════ THE LAUNCH BAR ══════════ --}}
    <div class="eo-domain-card flex flex-wrap items-center gap-x-6 gap-y-3 px-4 py-3.5">
        <div class="flex items-center gap-2.5">
            <span class="grid h-9 w-9 place-items-center rounded-full bg-eo-ok-soft text-eo-ok-ink">
                <x-icon name="clipboard" class="h-4 w-4" />
            </span>
            <span class="leading-tight">
                <span class="block text-[10px] font-bold uppercase tracking-[0.14em] text-eo-ok-ink">Draft saved</span>
                <span class="block text-[10.5px] text-eo-muted">{{ $savedAgo ?? 'Nothing typed yet' }}</span>
            </span>
        </div>

        <div class="flex items-center gap-2.5">
            <span class="grid h-9 w-9 place-items-center rounded-full bg-eo-workspace text-eo-muted">
                <x-icon name="grid" class="h-4 w-4" />
            </span>
            <span class="leading-tight">
                <span class="block text-[10px] font-bold uppercase tracking-[0.14em] text-eo-text">{{ count($modules) }} {{ str('module')->plural(count($modules)) }} selected</span>
                <span class="block text-[10.5px] text-eo-muted">{{ $category !== '' ? $categories[$category][0].' preset enabled' : 'Pick a category to preset them' }}</span>
            </span>
        </div>

        <div class="flex items-center gap-2.5">
            <span class="grid h-9 w-9 place-items-center rounded-full bg-eo-workspace text-eo-muted">
                <x-icon name="clock" class="h-4 w-4" />
            </span>
            <span class="leading-tight">
                <span class="block text-[10px] font-bold uppercase tracking-[0.14em] text-eo-text">Est. setup time</span>
                <span class="block text-[10.5px] text-eo-muted">{{ $setupMinutes }} – {{ $setupMinutes + 1 }} minutes</span>
            </span>
        </div>

        <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                class="ms-auto flex h-12 items-center gap-2.5 rounded-2xl bg-gradient-to-r from-eo-gold-soft to-eo-gold px-7 text-[13.5px] font-black text-eo-navy-deep shadow-[0_14px_30px_-16px_rgba(214,174,52,0.6)] transition hover:brightness-105 disabled:opacity-60">
            <span wire:loading.remove wire:target="save" class="flex items-center gap-2">
                Launch Event
                <x-icon name="sparkles" class="h-4 w-4" />
            </span>
            <span wire:loading wire:target="save">…</span>
        </button>
    </div>
</div>
