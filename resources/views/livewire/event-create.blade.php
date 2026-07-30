@php
    // What the preview shows before you have said anything. Placeholders, never
    // invented facts — an empty studio should look empty, not pre-filled.
    $pName = $name !== '' ? $name : 'Untitled event';
    $pClient = $client_id ? optional($clients->firstWhere('id', $client_id))->name : (trim($new_client) ?: null);
    $pVenue = $venue_id ? optional($venues->firstWhere('id', $venue_id))->name : null;
    $pWhere = collect([$pVenue, $city, $country])->filter()->take(2)->implode(', ');
    $pStage = \App\Support\Workflow::label('event_stage', \App\Livewire\EventCreate::STATUS_PILLS[$statusPill] ?? 'draft');
    $pStageHex = \App\Models\Event::stageColor(\App\Livewire\EventCreate::STATUS_PILLS[$statusPill] ?? 'draft');
    [$pPriorityLabel, $pPriorityHex] = $priorities[$priority] ?? $priorities['normal'];
    $cur = $currency ?: $defaultCurrency;
    $curSymbol = \App\Models\Event::CURRENCIES[$cur][0] ?? '';

    $pDates = $starts_at
        ? \Illuminate\Support\Carbon::parse($starts_at)->format('M j')
            . ($ends_at ? ' – ' . \Illuminate\Support\Carbon::parse($ends_at)->format('j, Y') : ', ' . \Illuminate\Support\Carbon::parse($starts_at)->format('Y'))
        : 'Dates not set';
@endphp

<div class="space-y-4">

    {{-- ══════════ THE STUDIO'S OWN HEADER ══════════ --}}
    <div class="flex flex-wrap items-end gap-x-6 gap-y-3">
        <div class="min-w-0">
            <h1 class="pf flex items-center gap-2 text-[28px] font-black leading-none text-navy-950">
                Event Studio <x-icon name="sparkles" class="h-5 w-5 text-gold-500" />
            </h1>
            <p class="mt-1.5 text-[12.5px] text-muted">Define your event. The platform builds everything around it.</p>
        </div>

        <a href="{{ route('events.index') }}" class="ms-auto flex h-10 items-center gap-1.5 rounded-2xl border border-line bg-white px-3.5 text-[12px] font-semibold text-navy-700 shadow-sm transition hover:border-gold-300">
            ← Back to events
        </a>
    </div>

    {{-- ══════════ THE FIVE ROOMS ══════════ --}}
    <div class="card overflow-hidden">
        <div class="scrollbar-none flex items-stretch overflow-x-auto">
            @foreach ($steps as $n => [$label, $note])
                @php $done = $n < $step; $on = $n === $step; @endphp
                <button type="button" wire:click="goTo({{ $n }})" @disabled($n > $step && ! $done)
                        @class([
                            'group relative flex min-w-[168px] flex-1 items-center gap-2.5 px-4 py-3.5 text-left transition',
                            'text-navy-950' => $on,
                            'text-navy-500 hover:bg-page/60' => ! $on,
                            'cursor-not-allowed opacity-45' => $n > $step,
                        ])>
                    <span @class([
                        'grid h-7 w-7 shrink-0 place-items-center rounded-full text-[11px] font-black transition',
                        'bg-gold-500 text-navy-950' => $on,
                        'bg-emerald-500 text-white' => $done,
                        'bg-navy-50 text-navy-400' => ! $on && ! $done,
                    ])>{{ $done ? '✓' : $n }}</span>

                    <span class="min-w-0">
                        <span class="block truncate text-[12.5px] font-bold">{{ $label }}</span>
                    </span>

                    @if ($on)
                        <span class="absolute inset-x-3 -bottom-px h-[2.5px] rounded-full bg-gold-500"></span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    {{-- ══════════ ZONE 1 · WORKSPACE ── ZONE 2 · LIVE PREVIEW ══════════ --}}
    <div class="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.86fr)]">

        {{-- ─────────── ZONE 1 ─────────── --}}
        <div class="card min-w-0 p-5 lg:p-6">
            <div class="mb-5 flex items-center gap-2.5">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-navy-950 text-[12px] font-black text-gold-400">{{ $step }}</span>
                <div class="min-w-0">
                    <h2 class="pf text-[17px] font-bold text-navy-950">{{ $steps[$step][0] }}</h2>
                    <p class="text-[11.5px] text-muted">{{ $steps[$step][1] }}</p>
                </div>
            </div>

            {{-- ══ 1 · IDENTITY ══ --}}
            @if ($step === 1)
                <div class="space-y-4">
                    <div>
                        <div class="mb-1.5 flex items-baseline justify-between">
                            <label class="field-label !mb-0" for="s-name">Event name <span class="text-risk">*</span></label>
                            <span class="text-[10.5px] tabular-nums text-muted">{{ mb_strlen($name) }}/120</span>
                        </div>
                        <input id="s-name" type="text" wire:model.live.debounce.250ms="name" maxlength="120"
                               class="input h-12 !text-[17px] font-bold text-navy-950" placeholder="Arab Investment Summit 2027">
                        @error('name') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="field-label" for="s-client">Client <span class="text-risk">*</span></label>
                            @if ($newClientMode)
                                <input id="s-client" type="text" wire:model.live.debounce.300ms="new_client" class="input h-11" placeholder="New client name">
                            @else
                                <select id="s-client" wire:model.live="client_id" class="input h-11">
                                    <option value="">— Select client —</option>
                                    @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                                </select>
                            @endif
                            <button type="button" wire:click="toggleNewClient" class="mt-1 text-[11px] font-semibold text-gold-700 hover:underline">
                                {{ $newClientMode ? '← Pick an existing client' : '＋ Add a new client' }}
                            </button>
                            @error('client_id') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="field-label" for="s-stage">Event stage</label>
                            <select id="s-stage" wire:model.live="statusPill" class="input h-11">
                                @foreach (\App\Livewire\EventCreate::STATUS_PILLS as $pill => $stage)
                                    <option value="{{ $pill }}" @selected($pill === $statusPill)>{{ \App\Support\Workflow::label('event_stage', $stage) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="field-label" for="s-priority">Priority</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3 top-1/2 h-2.5 w-2.5 -translate-y-1/2 rounded-full"
                                      style="background: {{ $pPriorityHex }}"></span>
                                <select id="s-priority" wire:model.live="priority" class="input h-11 !ps-8">
                                    @foreach ($priorities as $key => [$label, $hex])
                                        <option value="{{ $key }}" @selected($key === $priority)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="field-label">Event type <span class="text-risk">*</span></label>
                        <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-4">
                            @foreach ($templates as $key => [$label, $type, $icon, $mods, $blurb])
                                @php $on = $template === $key; @endphp
                                <button type="button" wire:click="chooseTemplate('{{ $key }}')"
                                        @class([
                                            'group relative flex flex-col items-center gap-2 rounded-2xl border p-3.5 text-center transition',
                                            'border-gold-400 bg-gold-50/60 shadow-[0_10px_26px_-18px_rgba(212,175,55,0.9)]' => $on,
                                            'border-line bg-white hover:-translate-y-0.5 hover:border-gold-200 hover:shadow-sm' => ! $on,
                                        ])>
                                    @if ($on)
                                        <span class="absolute end-2 top-2 grid h-5 w-5 place-items-center rounded-full bg-gold-500 text-[10px] font-black text-navy-950">✓</span>
                                    @endif
                                    <span @class([
                                        'grid h-11 w-11 place-items-center rounded-xl transition',
                                        'bg-gold-500 text-navy-950' => $on,
                                        'bg-page text-navy-500 group-hover:bg-navy-50' => ! $on,
                                    ])>
                                        <x-icon :name="$icon" class="h-5 w-5" />
                                    </span>
                                    <span class="block text-[12.5px] font-bold text-navy-950">{{ $label }}</span>
                                    <span class="block max-w-[15ch] text-[10.5px] leading-tight text-muted">{{ $blurb }}</span>
                                </button>
                            @endforeach
                        </div>
                        @error('template') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                    </div>
                </div>

            {{-- ══ 2 · WHEN & WHERE ══ --}}
            @elseif ($step === 2)
                <div class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="field-label" for="s-start">Starts <span class="text-risk">*</span></label>
                            <input id="s-start" type="date" wire:model.live="starts_at" class="input h-11">
                            @error('starts_at') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="field-label" for="s-end">Ends</label>
                            <input id="s-end" type="date" wire:model.live="ends_at" class="input h-11">
                            @error('ends_at') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    @if ($previewDays > 0)
                        <p class="flex items-center gap-2 rounded-xl bg-gold-50/70 px-3.5 py-2.5 text-[11.5px] text-navy-700 ring-1 ring-gold-200/70">
                            <x-icon name="calendar" class="h-4 w-4 shrink-0 text-gold-600" />
                            {{ $previewDays }} agenda {{ str('day')->plural($previewDays) }} will be scaffolded the moment this launches.
                        </p>
                    @endif

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="field-label" for="s-venue">Venue</label>
                            <select id="s-venue" wire:model.live="venue_id" class="input h-11">
                                <option value="">— To be confirmed —</option>
                                @foreach ($venues as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="field-label" for="s-city">City</label>
                            <input id="s-city" type="text" wire:model.live.debounce.300ms="city" class="input h-11" placeholder="Amman">
                        </div>
                        <div>
                            <label class="field-label" for="s-country">Country</label>
                            <input id="s-country" type="text" wire:model.live.debounce.300ms="country" class="input h-11" placeholder="Jordan">
                        </div>
                    </div>

                    <div class="sm:w-1/2">
                        <label class="field-label" for="s-tz">Timezone</label>
                        <select id="s-tz" wire:model.live="timezone" class="input h-11">
                            @foreach ($timezones as $tz)<option value="{{ $tz }}" @selected($tz === $timezone)>{{ $tz }}</option>@endforeach
                        </select>
                    </div>
                </div>

            {{-- ══ 3 · MODULES ══ --}}
            @elseif ($step === 3)
                <div class="space-y-4">
                    <p class="text-[12px] text-muted">
                        Your event type switched {{ count($modules) }} of these on. Every one you keep becomes a tab in the
                        Event Hub; every one you drop stays out of the way until you want it.
                    </p>

                    {{-- preserveKeys: the module key IS the identity here; without it every
                         button posts toggleModule('0') and nothing ever switches on. --}}
                    @foreach (collect($hubModules)->groupBy(fn ($m) => $m[1], true) as $group => $mods)
                        <div>
                            <p class="eyebrow mb-2">{{ $group }}</p>
                            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($mods as $key => [$label, $cat, $icon])
                                    @php $on = in_array($key, $modules, true); @endphp
                                    <button type="button" wire:click="toggleModule('{{ $key }}')"
                                            @class([
                                                'flex items-center gap-2.5 rounded-xl border px-3 py-2.5 text-left transition',
                                                'border-navy-950 bg-navy-950 text-white' => $on,
                                                'border-line bg-white text-navy-600 hover:border-navy-200' => ! $on,
                                            ])>
                                        <span @class([
                                            'grid h-8 w-8 shrink-0 place-items-center rounded-lg',
                                            'bg-white/10 text-gold-400' => $on,
                                            'bg-page text-navy-400' => ! $on,
                                        ])>
                                            <x-icon :name="$icon" class="h-4 w-4" />
                                        </span>
                                        <span class="min-w-0 flex-1 truncate text-[12px] font-bold">{{ $label }}</span>
                                        <span @class(['text-[13px] font-black', 'text-gold-400' => $on, 'text-navy-200' => ! $on])>{{ $on ? '✓' : '+' }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

            {{-- ══ 4 · DETAILS ══ --}}
            @elseif ($step === 4)
                <div class="space-y-4">
                    <div>
                        <div class="mb-1.5 flex items-baseline justify-between">
                            <label class="field-label !mb-0" for="s-desc">Description</label>
                            <span class="text-[10.5px] tabular-nums text-muted">{{ mb_strlen($description) }}/600</span>
                        </div>
                        <textarea id="s-desc" wire:model.live.debounce.400ms="description" rows="3" maxlength="600"
                                  class="input text-sm" placeholder="A premier gathering of investors, leaders and innovators shaping the future of global investment."></textarea>
                        @error('description') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="field-label" for="s-pax">Expected participants</label>
                            <input id="s-pax" type="number" min="0" wire:model.live.debounce.300ms="expected_participants" class="input h-11" placeholder="1200">
                            @error('expected_participants') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="field-label" for="s-budget">Budget</label>
                            <input id="s-budget" type="number" step="0.01" min="0" wire:model.live.debounce.300ms="budget" class="input h-11" placeholder="480000">
                            @error('budget') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="field-label" for="s-cur">Currency</label>
                            <select id="s-cur" wire:model.live="currency" class="input h-11">
                                @foreach ($currencies as $code => [$symbol, $label])
                                    <option value="{{ $code }}" @selected($code === $currency)>{{ $code }} — {{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="sm:w-2/3">
                        <label class="field-label" for="s-pm">Project manager</label>
                        <select id="s-pm" wire:model.live="project_manager_id" class="input h-11">
                            <option value="">— Assign later —</option>
                            @foreach ($managers as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
                        </select>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="field-label" for="s-cover">Cover image</label>
                            <input id="s-cover" type="file" wire:model="cover" accept="image/*"
                                   class="input h-11 !py-0 text-xs file:mr-3 file:h-full file:rounded-lg file:border-0 file:bg-navy-950 file:px-3 file:text-xs file:font-semibold file:text-white">
                            @error('cover') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="field-label" for="s-logo">Client logo</label>
                            <input id="s-logo" type="file" wire:model="logo" accept="image/*"
                                   class="input h-11 !py-0 text-xs file:mr-3 file:h-full file:rounded-lg file:border-0 file:bg-navy-950 file:px-3 file:text-xs file:font-semibold file:text-white">
                            @error('logo') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

            {{-- ══ 5 · REVIEW ══ --}}
            @else
                <div class="space-y-4">
                    <p class="text-[12px] text-muted">Here is what launching builds. Nothing has been written yet.</p>

                    <div class="divide-y divide-line rounded-2xl border border-line">
                        @foreach ([
                            ['Name', $pName, 1],
                            ['Client', $pClient ?: 'Not chosen', 1],
                            ['Kind', $template ? $templates[$template][0] : 'Not chosen', 1],
                            ['Stage · priority', $pStage.' · '.$pPriorityLabel, 1],
                            ['Dates', $pDates.($previewDays ? '  ·  '.$previewDays.' '.str('day')->plural($previewDays) : ''), 2],
                            ['Where', $pWhere ?: 'To be confirmed', 2],
                            ['Modules', count($modules).' enabled', 3],
                            ['Headcount', $expected_participants !== '' ? number_format((int) $expected_participants).' expected' : 'Not set', 4],
                            ['Budget', $budget !== '' ? $curSymbol.number_format((float) $budget) : 'Not set', 4],
                        ] as [$term, $value, $room])
                            <div class="flex items-baseline gap-3 px-4 py-2.5">
                                <span class="w-32 shrink-0 text-[11px] font-semibold text-muted">{{ $term }}</span>
                                <span class="min-w-0 flex-1 truncate text-[12.5px] font-bold text-navy-950">{{ $value }}</span>
                                <button type="button" wire:click="goTo({{ $room }})" class="shrink-0 text-[11px] font-semibold text-gold-700 hover:underline">Change</button>
                            </div>
                        @endforeach
                    </div>

                    @if ($readiness['missing'])
                        <div class="rounded-2xl border border-gold-200 bg-gold-50/60 px-4 py-3">
                            <p class="text-[11.5px] font-bold text-gold-800">You can launch without these, and fill them in later:</p>
                            <p class="mt-1 text-[11.5px] text-navy-700">{{ implode(' · ', $readiness['missing']) }}</p>
                        </div>
                    @endif
                </div>
            @endif

            {{-- ── moving between rooms ── --}}
            <div class="mt-6 flex items-center gap-2 border-t border-line pt-4">
                @if ($step > 1)
                    <button type="button" wire:click="back" class="flex h-10 items-center rounded-xl border border-line bg-white px-4 text-[12px] font-semibold text-navy-700 transition hover:border-navy-200">← Back</button>
                @endif

                <p class="ms-auto text-[11px] text-muted">Step {{ $step }} of {{ count($steps) }}</p>

                @if ($step < count($steps))
                    <button type="button" wire:click="next" class="flex h-10 items-center rounded-xl bg-navy-950 px-5 text-[12px] font-bold text-white transition hover:bg-navy-800">Continue →</button>
                @endif
            </div>
        </div>

        {{-- ─────────── ZONE 2 · LIVE PREVIEW ───────────
             The innovation, and the reason this is a studio rather than a form:
             the event is on screen the whole time you are deciding about it.
             Every field above writes here on the keystroke. --}}
        <div class="space-y-4 xl:sticky xl:top-4">
            <div class="flex flex-wrap items-center gap-2">
                <p class="eyebrow">Live preview</p>
                <span class="flex items-center gap-1.5 text-[10.5px] text-muted">
                    <span class="relative flex h-1.5 w-1.5">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-70"></span>
                        <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                    </span>
                    updates as you type
                </span>

                {{-- The same card, without the numbers only your team should
                     see. Enabled once there is something worth showing. --}}
                <button type="button" wire:click="$toggle('asAttendee')" @disabled($name === '')
                        @class([
                            'ms-auto flex h-9 items-center gap-1.5 rounded-xl border px-3 text-[11.5px] font-bold transition disabled:opacity-40',
                            'border-navy-950 bg-navy-950 text-white' => $asAttendee,
                            'border-line bg-white text-navy-700 hover:border-gold-300' => ! $asAttendee,
                        ])>
                    Preview as attendee <span aria-hidden="true">↗</span>
                </button>
            </div>

            {{-- Portrait, on the deck's proportion (width ÷ height = 0.66), because
                 this is the same card the Events deck will show once the event
                 exists — a preview that is a different shape is not a preview.
                 The cover takes the slack: everything under it is fixed height,
                 so the ratio holds whatever the column width is. --}}
            <article class="mx-auto flex w-full max-w-[430px] flex-col overflow-hidden rounded-[24px] border border-line bg-white shadow-[0_24px_60px_-38px_rgba(11,31,58,0.55)]"
                     style="aspect-ratio: 66 / 100">
                {{-- the cover --}}
                <div class="relative isolate min-h-[150px] flex-1 overflow-hidden bg-navy-950">
                    @if ($cover)
                        <img src="{{ $cover->temporaryUrl() }}" alt="" class="absolute inset-0 -z-10 h-full w-full object-cover">
                    @else
                        <div class="absolute inset-0 -z-10" style="background:
                            radial-gradient(120% 120% at 80% 0%, rgba(212,175,55,.42) 0%, transparent 60%),
                            linear-gradient(160deg, #0b1f3a, #060f1f)"></div>
                    @endif
                    <div class="absolute inset-0 -z-10 bg-gradient-to-t from-navy-950/60 to-transparent"></div>

                    <div class="flex justify-end p-3">
                        <label for="s-cover-quick" class="flex cursor-pointer items-center gap-1.5 rounded-xl bg-navy-950/70 px-3 py-1.5 text-[11px] font-semibold text-white backdrop-blur transition hover:bg-navy-950">
                            <x-icon name="grid" class="h-3.5 w-3.5" /> Edit cover
                        </label>
                        <input id="s-cover-quick" type="file" wire:model="cover" accept="image/*" class="hidden">
                    </div>
                </div>

                {{-- the client's mark, straddling the fold --}}
                <div class="relative px-5">
                    <span class="absolute -top-9 grid h-[70px] w-[70px] place-items-center overflow-hidden rounded-full border-[3px] border-white bg-white shadow-lg">
                        @if ($logo)
                            <img src="{{ $logo->temporaryUrl() }}" alt="" class="h-full w-full object-cover">
                        @else
                            <span class="pf text-[19px] font-black text-navy-300">{{ mb_strtoupper(mb_substr($pClient ?: $pName, 0, 2)) }}</span>
                        @endif
                    </span>
                </div>

                <div class="shrink-0 px-5 pb-4 pt-12">
                    <span class="inline-block rounded-full px-2.5 py-1 text-[9.5px] font-black uppercase tracking-[0.14em]"
                          style="background: {{ $pStageHex }}1a; color: {{ $pStageHex }}">{{ $pStage }}</span>

                    <h3 class="pf mt-2.5 line-clamp-2 text-[24px] font-black leading-tight {{ $name !== '' ? 'text-navy-950' : 'text-navy-200' }}">{{ $pName }}</h3>

                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[11.5px] text-navy-600">
                        <span class="flex items-center gap-1.5"><x-icon name="calendar" class="h-3.5 w-3.5 text-navy-300" />{{ $pDates }}</span>
                        <span class="flex items-center gap-1.5"><x-icon name="pin" class="h-3.5 w-3.5 text-navy-300" />{{ $pWhere ?: 'Location to be set' }}</span>
                        @if ($expected_participants !== '')
                            <span class="flex items-center gap-1.5"><x-icon name="users" class="h-3.5 w-3.5 text-navy-300" />{{ number_format((int) $expected_participants) }} expected</span>
                        @endif
                    </div>

                    <p class="mt-3 line-clamp-3 text-[12px] leading-relaxed {{ trim($description) !== '' ? 'text-navy-700' : 'text-navy-300' }}">
                        {{ trim($description) ?: 'A sentence about the event will appear here as you write it.' }}
                    </p>
                </div>

                {{-- the four figures the event will open with --}}
                @unless ($asAttendee)
                    @php
                        $r = 2 * M_PI * 26;
                        $rings = [
                            ['value' => $readiness['pct'].'%', 'pct' => $readiness['pct'], 'hex' => 'var(--color-gold-500)', 'label' => 'Progress'],
                            ['value' => count($modules), 'pct' => count($modules) ? min(100, count($modules) / 18 * 100) : 0, 'hex' => '#3B82F6', 'label' => 'Modules'],
                            ['value' => $budget !== '' ? $curSymbol.\Illuminate\Support\Number::abbreviate((float) $budget, 0) : '—', 'pct' => $budget !== '' ? 100 : 0, 'hex' => '#10B981', 'label' => 'Budget'],
                            ['value' => $pPriorityLabel, 'pct' => 100, 'hex' => $pPriorityHex, 'label' => 'Priority'],
                        ];
                    @endphp

                    <div class="grid shrink-0 grid-cols-4 divide-x divide-line border-y border-line">
                        @foreach ($rings as $ring)
                            <div class="flex flex-col items-center gap-1.5 px-1.5 py-4">
                                <span class="relative grid h-[62px] w-[62px] place-items-center">
                                    <svg class="h-[62px] w-[62px] -rotate-90" viewBox="0 0 60 60" aria-hidden="true">
                                        <circle cx="30" cy="30" r="26" fill="none" stroke="var(--color-navy-50)" stroke-width="5" />
                                        <circle cx="30" cy="30" r="26" fill="none" stroke="{{ $ring['hex'] }}" stroke-width="5" stroke-linecap="round"
                                                stroke-dasharray="{{ $r }}" stroke-dashoffset="{{ $r - ($r * $ring['pct'] / 100) }}"
                                                style="transition: stroke-dashoffset .45s cubic-bezier(.22,1,.36,1)" />
                                    </svg>
                                    <span class="pf absolute text-[14px] font-black leading-none"
                                          style="color: {{ $ring['label'] === 'Priority' ? $ring['hex'] : 'var(--color-navy-950)' }}">{{ $ring['value'] }}</span>
                                </span>
                                <span class="text-[10px] text-muted">{{ $ring['label'] }}</span>
                            </div>
                        @endforeach
                    </div>

                    {{-- The first thing the platform will put in the diary. Agenda
                         lock is conventionally a month before doors, so the date is
                         known before the event exists. --}}
                    <div class="m-4 shrink-0 rounded-2xl bg-page/70 px-4 py-3">
                        <p class="eyebrow">Next milestone</p>
                        @if ($milestone)
                            <div class="mt-1 flex flex-wrap items-baseline gap-x-3">
                                <span class="pf text-[13.5px] font-bold text-navy-950">{{ $milestone['title'] }}</span>
                                <span class="flex items-center gap-1.5 text-[11.5px] text-muted"><x-icon name="calendar" class="h-3.5 w-3.5 text-navy-300" />{{ $milestone['due'] }}</span>
                                <span class="ms-auto text-[11.5px] font-bold {{ $milestone['late'] ? 'text-risk' : 'text-gold-700' }}">{{ $milestone['note'] }}</span>
                            </div>
                        @else
                            <p class="mt-1 text-[12px] text-navy-300">Set a start date and the first milestone lands here.</p>
                        @endif
                    </div>
                @else
                    <div class="shrink-0 border-t border-line px-5 py-4">
                        <p class="eyebrow">What an attendee sees</p>
                        <p class="mt-1.5 text-[11.5px] leading-relaxed text-muted">
                            The cover, the name, the dates, the place and the description — and none of
                            the budget, the modules or the priority. This is the card that goes on the
                            registration page.
                        </p>
                    </div>
                @endunless

            </article>
        </div>
    </div>

    {{-- ══════════ THE LAUNCH BAR ══════════ --}}
    <div class="card flex flex-wrap items-center gap-x-6 gap-y-3 px-4 py-3.5">
        <div class="flex items-center gap-2.5">
            <span class="grid h-9 w-9 place-items-center rounded-full bg-emerald-50 text-emerald-600">
                <x-icon name="clipboard" class="h-4 w-4" />
            </span>
            <span class="leading-tight">
                <span class="block text-eyebrow font-bold uppercase tracking-[0.14em] text-emerald-700">Draft saved</span>
                <span class="block text-[10.5px] text-muted">{{ $savedAgo ?? 'Nothing typed yet' }}</span>
            </span>
        </div>

        <div class="flex items-center gap-2.5">
            <span class="grid h-9 w-9 place-items-center rounded-full bg-navy-50 text-navy-500">
                <x-icon name="grid" class="h-4 w-4" />
            </span>
            <span class="leading-tight">
                <span class="block text-eyebrow font-bold uppercase tracking-[0.14em] text-navy-700">{{ count($modules) }} {{ str('module')->plural(count($modules)) }} selected</span>
                <span class="block text-[10.5px] text-muted">{{ $template ? $templates[$template][0].' preset enabled' : 'Pick a type to preset them' }}</span>
            </span>
        </div>

        <div class="flex items-center gap-2.5">
            <span class="grid h-9 w-9 place-items-center rounded-full bg-navy-50 text-navy-500">
                <x-icon name="clock" class="h-4 w-4" />
            </span>
            <span class="leading-tight">
                <span class="block text-eyebrow font-bold uppercase tracking-[0.14em] text-navy-700">Est. setup time</span>
                <span class="block text-[10.5px] text-muted">{{ $setupMinutes }} – {{ $setupMinutes + 1 }} minutes</span>
            </span>
        </div>

        <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                class="ms-auto flex h-12 items-center gap-2.5 rounded-2xl bg-gradient-to-r from-gold-400 to-gold-500 px-7 text-[13.5px] font-black text-navy-950 shadow-[0_14px_30px_-16px_rgba(212,175,55,0.95)] transition hover:brightness-105 disabled:opacity-60">
            <span wire:loading.remove wire:target="save">🚀</span>
            <span wire:loading wire:target="save">…</span>
            Launch Event
            <x-icon name="sparkles" class="h-4 w-4" />
        </button>
    </div>
</div>
