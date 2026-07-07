<div class="mx-auto max-w-4xl">

    {{-- ══ Step bar ══ --}}
    <div class="mb-6 flex items-center">
        @foreach (['Basics', 'Type', 'Modules'] as $i => $label)
            @php $n = $i + 1; @endphp
            <div class="flex items-center gap-3">
                <span @class([
                        'flex h-9 w-9 items-center justify-center rounded-full text-sm font-bold transition',
                        'bg-navy-900 text-white' => $step >= $n,
                        'bg-white text-navy-300 ring-1 ring-line' => $step < $n,
                    ])>{{ $step > $n ? '✓' : $n }}</span>
                <span class="{{ $step === $n ? 'font-bold text-navy-900' : 'font-semibold text-navy-300' }} text-lg">{{ $label }}</span>
            </div>
            @unless ($loop->last)
                <div class="mx-4 h-0.5 flex-1 rounded {{ $step > $n ? 'bg-navy-900' : 'bg-line' }}"></div>
            @endunless
        @endforeach
    </div>

    <div class="rounded-[28px] bg-white p-8 shadow-[0_20px_50px_rgba(11,31,58,0.08)] sm:p-10">

        {{-- ══ Step 1: Basics ══ --}}
        @if ($step === 1)
            <div class="space-y-6">
                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <span class="field-label !mb-0">Client / Account <span class="text-risk">*</span></span>
                        <button type="button" wire:click="toggleNewClient" class="text-sm font-bold text-navy-900 hover:text-gold-600">
                            {{ $newClientMode ? '← Pick existing' : '+ New client' }}
                        </button>
                    </div>
                    @if ($newClientMode)
                        <input type="text" wire:model="new_client" class="field" placeholder="New client / account name">
                    @else
                        <select wire:model="client_id" class="field">
                            <option value="">Select a client…</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}">{{ $client->name }}@if ($client->organization) — {{ $client->organization }} @endif</option>
                            @endforeach
                        </select>
                    @endif
                    @error('client_id') <p class="mt-1.5 text-sm text-risk">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="field-label" for="e-title">Event Title <span class="text-risk">*</span></label>
                    <input id="e-title" type="text" wire:model="name" class="field" placeholder="e.g. Annual Investors Summit 2026">
                    @error('name') <p class="mt-1.5 text-sm text-risk">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-5 sm:grid-cols-3">
                    <div>
                        <label class="field-label" for="e-start">Start Date <span class="text-risk">*</span></label>
                        <input id="e-start" type="date" wire:model.live="starts_at" class="field">
                        @error('starts_at') <p class="mt-1.5 text-sm text-risk">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="field-label" for="e-end">End Date</label>
                        <input id="e-end" type="date" wire:model.live="ends_at" class="field">
                        @error('ends_at') <p class="mt-1.5 text-sm text-risk">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="field-label" for="e-tz">Timezone</label>
                        <select id="e-tz" wire:model="timezone" class="field">
                            @foreach ($timezones as $tz)<option value="{{ $tz }}">{{ $tz }}</option>@endforeach
                        </select>
                    </div>
                </div>

                @php
                    $dayCount = 0;
                    if ($starts_at) {
                        try {
                            $s = \Carbon\Carbon::parse($starts_at);
                            $e = $ends_at ? \Carbon\Carbon::parse($ends_at) : $s;
                            $dayCount = $e->gte($s) ? (int) $s->diffInDays($e) + 1 : 0;
                        } catch (\Throwable) {}
                    }
                @endphp
                @if ($dayCount > 0)
                    <div class="flex items-center gap-2 rounded-2xl bg-navy-50 px-4 py-2.5 text-sm font-semibold text-navy-800">
                        <x-icon name="calendar" class="h-4 w-4 text-gold-600" />
                        This event spans <span class="text-navy-900">{{ $dayCount }} {{ str('day')->plural($dayCount) }}</span> — {{ $dayCount }} agenda {{ str('day')->plural($dayCount) }} will be created (Day 1–{{ $dayCount }}).
                    </div>
                @endif

                <div>
                    <span class="field-label">Status</span>
                    <div class="flex flex-wrap gap-3">
                        @foreach (['lead' => 'Lead', 'proposal' => 'Proposal', 'confirmed' => 'Confirmed'] as $value => $label)
                            <button type="button" wire:click="$set('statusPill', '{{ $value }}')"
                                    @class([
                                        'seg-pill',
                                        'border-navy-900 text-navy-900' => $statusPill === $value,
                                        'border-line text-navy-300 hover:text-navy-600' => $statusPill !== $value,
                                    ])>{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- ══ Step 2: Type ══ --}}
        @if ($step === 2)
            <p class="mb-6 text-[0.95rem] text-muted">Choose a template — it pre-enables the modules this kind of event usually needs (you can fine-tune next). Optional.</p>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($templates as $key => [$label, $type, $icon, $mods])
                    <button type="button" wire:click="chooseTemplate('{{ $key }}')"
                            @class([
                                'flex items-center gap-4 rounded-2xl border-2 p-5 text-left transition',
                                'border-navy-900 bg-navy-50/40' => $template === $key,
                                'border-line hover:border-navy-200' => $template !== $key,
                            ])>
                        <span @class([
                                'flex h-12 w-12 shrink-0 items-center justify-center rounded-xl',
                                'bg-navy-900 text-gold-400' => $template === $key,
                                'bg-fill text-navy-500' => $template !== $key,
                            ])><x-icon :name="$icon" class="h-6 w-6" /></span>
                        <span class="text-lg font-bold text-navy-900">{{ $label }}</span>
                    </button>
                @endforeach
            </div>
        @endif

        {{-- ══ Step 3: Modules ══ --}}
        @if ($step === 3)
            <p class="mb-6 text-[0.95rem] text-muted">Turn on the modules this event needs. They appear in the Event Control Center — you can change these anytime.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($hubModules as $key => [$label, $category, $icon])
                    @php $on = in_array($key, $modules, true); @endphp
                    <button type="button" wire:click="toggleModule('{{ $key }}')"
                            class="flex items-center gap-4 rounded-2xl border border-line p-4 text-left transition hover:border-navy-200">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-fill text-navy-500">
                            <x-icon :name="$icon" class="h-5 w-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[0.95rem] font-bold text-navy-900">{{ $label }}</span>
                            <span class="block text-xs text-muted">{{ $category }}</span>
                        </span>
                        <span @class([
                                'relative flex h-6 w-11 shrink-0 items-center rounded-full transition',
                                'bg-navy-900' => $on,
                                'bg-navy-200' => ! $on,
                            ])>
                            <span class="absolute h-5 w-5 rounded-full bg-white shadow transition-all {{ $on ? 'left-[22px]' : 'left-0.5' }}"></span>
                        </span>
                    </button>
                @endforeach
            </div>
        @endif

        {{-- ══ Footer actions ══ --}}
        <div class="mt-8 flex items-center justify-between border-t border-line pt-6">
            <div>
                @if ($step > 1)
                    <button type="button" wire:click="back" class="rounded-2xl border border-line px-6 py-3 text-sm font-bold text-navy-600 transition hover:border-navy-200 hover:text-navy-900">← Back</button>
                @else
                    <a href="{{ route('events.index') }}" class="rounded-2xl px-6 py-3 text-sm font-bold text-navy-400 transition hover:text-navy-700">Cancel</a>
                @endif
            </div>
            <div class="flex items-center gap-3">
                <button type="button" wire:click="save" class="rounded-2xl bg-fill px-6 py-3 text-sm font-bold text-navy-900 transition hover:bg-line"
                        wire:loading.attr="disabled" wire:target="save">Create &amp; open</button>
                @if ($step < 3)
                    <button type="button" wire:click="next" class="rounded-2xl bg-navy-900 px-7 py-3 text-sm font-bold text-white shadow-[0_10px_25px_rgba(11,31,58,0.25)] transition hover:bg-navy-800">Continue →</button>
                @else
                    <button type="button" wire:click="save" class="rounded-2xl bg-navy-900 px-7 py-3 text-sm font-bold text-white shadow-[0_10px_25px_rgba(11,31,58,0.25)] transition hover:bg-navy-800"
                            wire:loading.attr="disabled" wire:target="save">Create event</button>
                @endif
            </div>
        </div>
    </div>
</div>
