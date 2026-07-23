<div class="mx-auto max-w-6xl">
    @php
        $stageHex = ['lead' => '#94A3B8', 'proposal' => 'var(--color-info)', 'confirmed' => '#06B6D4'];
        $pvName = trim($name) ?: 'Untitled event';
        $pvClient = trim($new_client) ?: optional($clients->firstWhere('id', (int) $client_id))->name;
        $pvStart = $starts_at ? \Illuminate\Support\Carbon::parse($starts_at) : null;
        $pvEnd = $ends_at ? \Illuminate\Support\Carbon::parse($ends_at) : null;
        $pvDays = $pvStart ? (int) round(now()->startOfDay()->diffInDays($pvStart->copy()->startOfDay(), false)) : null;
        $pvInitials = \Illuminate\Support\Str::of($pvName)->explode(' ')->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(3)->implode('');
    @endphp

    <div class="flex flex-col gap-6 lg:flex-row">

        {{-- ═════════ THE CANVAS ═════════ --}}
        <form wire:submit="save" class="min-w-0 flex-1 space-y-4">

            {{-- 1 · Who & what --}}
            <section class="card p-5">
                <div class="mb-3 flex items-center gap-2">
                    <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-navy-900 text-eyebrow font-black text-gold-400">1</span>
                    <h2 class="pf text-base font-bold text-navy-900">Who is it for, and what is it?</h2>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <div class="mb-1.5 flex items-center justify-between">
                            <label class="field-label !mb-0" for="c-client">Client <span class="text-risk">*</span></label>
                            <button type="button" wire:click="toggleNewClient" class="text-micro font-bold text-gold-600 transition hover:text-gold-700">
                                {{ $newClientMode ? '← Pick existing' : '＋ New client' }}
                            </button>
                        </div>
                        @if ($newClientMode)
                            <input id="c-client" type="text" wire:model.live.debounce.300ms="new_client" class="field !py-2.5" placeholder="New client name">
                        @else
                            <select id="c-client" wire:model.live="client_id" class="field !py-2.5">
                                <option value="">— Select a client —</option>
                                @foreach ($clients as $client)<option value="{{ $client->id }}">{{ $client->name }}</option>@endforeach
                            </select>
                        @endif
                        @error('client_id') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="field-label !mb-1.5" for="c-name">Event title <span class="text-risk">*</span></label>
                        <input id="c-name" type="text" wire:model.live.debounce.300ms="name" class="field !py-2.5" placeholder="e.g. Arab Investment Summit 2027">
                        @error('name') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-4">
                    <label class="field-label !mb-1.5">Where is it in your pipeline?</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach (['lead' => 'Lead', 'proposal' => 'Proposal', 'confirmed' => 'Confirmed'] as $pill => $label)
                            <button type="button" wire:click="$set('statusPill', '{{ $pill }}')"
                                    @class(['flex items-center gap-2 rounded-xl border px-4 py-2 text-sm font-bold transition', 'text-white' => $statusPill === $pill, 'border-line text-navy-500 hover:border-navy-200' => $statusPill !== $pill])
                                    @style(['background: '.$stageHex[$pill].'; border-color: '.$stageHex[$pill] => $statusPill === $pill])>
                                <span class="h-2 w-2 rounded-full" style="background: {{ $statusPill === $pill ? '#fff' : $stageHex[$pill] }}"></span>{{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- 2 · When --}}
            <section class="card p-5">
                <div class="mb-3 flex items-center gap-2">
                    <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-navy-900 text-eyebrow font-black text-gold-400">2</span>
                    <h2 class="pf text-base font-bold text-navy-900">When and where?</h2>
                    @if ($previewDays > 0)
                        <span class="ml-auto rounded-full bg-gold-50 px-2.5 py-1 text-micro font-bold text-gold-700">{{ $previewDays }} agenda {{ str('day')->plural($previewDays) }} will be created</span>
                    @endif
                </div>

                <div class="grid gap-4 sm:grid-cols-4">
                    <div>
                        <label class="field-label !mb-1.5" for="c-start">Start <span class="text-risk">*</span></label>
                        <input id="c-start" type="date" wire:model.live="starts_at" class="field !py-2.5">
                        @error('starts_at') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="field-label !mb-1.5" for="c-end">End</label>
                        <input id="c-end" type="date" wire:model.live="ends_at" class="field !py-2.5">
                        @error('ends_at') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="field-label !mb-1.5" for="c-city">City</label>
                        <input id="c-city" type="text" wire:model.live.debounce.300ms="city" class="field !py-2.5" placeholder="Amman">
                    </div>
                    <div>
                        <label class="field-label !mb-1.5" for="c-tz">Timezone</label>
                        <select id="c-tz" wire:model="timezone" class="field !py-2.5">
                            @foreach ($timezones as $tz)<option value="{{ $tz }}">{{ $tz }}</option>@endforeach
                        </select>
                    </div>
                </div>
            </section>

            {{-- 3 · Type --}}
            <section class="card p-5">
                <div class="mb-3 flex items-center gap-2">
                    <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-navy-900 text-eyebrow font-black text-gold-400">3</span>
                    <h2 class="pf text-base font-bold text-navy-900">What kind of event?</h2>
                    <span class="ml-auto text-micro text-muted">picks the crest &amp; the modules it needs</span>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach ($templates as $key => [$label, $type, $icon, $mods])
                        <button type="button" wire:click="chooseTemplate('{{ $key }}')"
                                @class([
                                    'flex flex-col items-center gap-1.5 rounded-2xl border px-2 py-3 transition',
                                    'border-gold-400 bg-gold-50 shadow-[0_8px_20px_-10px_rgba(212,175,55,0.6)]' => $template === $key,
                                    'border-line bg-white hover:border-navy-200' => $template !== $key,
                                ])>
                            <x-icon :name="$icon" class="h-5 w-5 {{ $template === $key ? 'text-gold-600' : 'text-navy-400' }}" />
                            <span class="text-micro font-bold {{ $template === $key ? 'text-navy-900' : 'text-navy-600' }}">{{ $label }}</span>
                        </button>
                    @endforeach
                </div>
            </section>

            {{-- 4 · Modules --}}
            <section class="card p-5">
                <div class="mb-3 flex items-center gap-2">
                    <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-navy-900 text-eyebrow font-black text-gold-400">4</span>
                    <h2 class="pf text-base font-bold text-navy-900">Which modules does it need?</h2>
                    <span class="ml-auto rounded-full bg-navy-50 px-2.5 py-1 text-micro font-bold text-navy-600">{{ count($modules) }} on</span>
                </div>

                <div class="flex flex-wrap gap-1.5">
                    @foreach ($hubModules as $key => [$label, $group, $icon])
                        @php $on = in_array($key, $modules, true); @endphp
                        <button type="button" wire:click="toggleModule('{{ $key }}')"
                                @class([
                                    'flex items-center gap-1.5 rounded-xl border px-3 py-2 text-micro font-bold transition',
                                    'border-navy-900 bg-navy-900 text-white' => $on,
                                    'border-line bg-white text-navy-500 hover:border-navy-200' => ! $on,
                                ])>
                            <x-icon :name="$icon" class="h-3.5 w-3.5 {{ $on ? 'text-gold-400' : 'text-navy-300' }}" />{{ $label }}
                        </button>
                    @endforeach
                </div>
                <p class="mt-2.5 text-micro text-muted">Overview, AI Insights and Settings are always on. You can change any of this later in the hub.</p>
            </section>

            <section>
                <div class="mb-2 flex items-center gap-2">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-navy-900 text-eyebrow font-black text-gold-400">◈</span>
                    <h2 class="text-sm font-bold text-navy-900">Cover &amp; logo <span class="font-normal text-muted">— optional</span></h2>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="cursor-pointer rounded-2xl border border-dashed border-line bg-white px-4 py-3 transition hover:border-gold-300">
                        <span class="text-micro font-bold text-navy-700">Cover image</span>
                        <span class="mt-0.5 block text-eyebrow text-muted">Wide photo / banner · PNG or JPG</span>
                        <input type="file" wire:model="cover" accept="image/*" class="mt-2 block w-full text-eyebrow file:mr-2 file:rounded-lg file:border-0 file:bg-navy-900 file:px-2.5 file:py-1 file:text-eyebrow file:font-semibold file:text-white">
                        <span wire:loading wire:target="cover" class="text-eyebrow text-gold-600">Uploading…</span>
                        @error('cover') <span class="mt-1 block text-eyebrow text-risk">{{ $message }}</span> @enderror
                    </label>
                    <label class="cursor-pointer rounded-2xl border border-dashed border-line bg-white px-4 py-3 transition hover:border-gold-300">
                        <span class="text-micro font-bold text-navy-700">Logo</span>
                        <span class="mt-0.5 block text-eyebrow text-muted">Square mark · transparent PNG best</span>
                        <input type="file" wire:model="logo" accept="image/*" class="mt-2 block w-full text-eyebrow file:mr-2 file:rounded-lg file:border-0 file:bg-navy-900 file:px-2.5 file:py-1 file:text-eyebrow file:font-semibold file:text-white">
                        <span wire:loading wire:target="logo" class="text-eyebrow text-gold-600">Uploading…</span>
                        @error('logo') <span class="mt-1 block text-eyebrow text-risk">{{ $message }}</span> @enderror
                    </label>
                </div>
            </section>

            <div class="flex flex-wrap items-center gap-3 pb-2">
                <a href="{{ route('events.index') }}" class="rounded-2xl bg-fill px-5 py-2.5 text-sm font-bold text-navy-700 transition hover:bg-line">Cancel</a>
                <button type="submit" class="btn-gold ml-auto h-11 !rounded-2xl px-7 text-sm" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Create event →</span>
                    <span wire:loading wire:target="save">Creating…</span>
                </button>
            </div>
        </form>

        {{-- ═════════ LIVE PREVIEW ═════════ --}}
        <aside class="w-full shrink-0 lg:w-[330px]">
            <div class="lg:sticky lg:top-6">
                <p class="mb-2 text-eyebrow font-bold uppercase tracking-[0.2em] text-muted">Live preview</p>

                <div class="overflow-hidden rounded-3xl border border-line bg-white shadow-[0_14px_38px_-18px_rgba(11,31,58,0.35)]">
                    <span class="block h-1 w-full" style="background: {{ $stageHex[$statusPill] }}"></span>

                    {{-- crest --}}
                    <div class="relative h-[132px] overflow-hidden bg-gradient-to-br from-navy-800 to-[var(--color-navy-950)]">
                        <div class="pointer-events-none absolute -right-7 -top-9 h-28 w-28 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.30),transparent_70%)]"></div>
                        @if ($cover)
                            <img src="{{ $cover->temporaryUrl() }}" alt="Cover preview" class="h-full w-full object-cover">
                        @else
                            <x-event-crest :name="$pvName" :type="$previewType" class="h-full w-full" />
                        @endif
                        @if ($logo)
                            <span class="absolute bottom-2.5 right-2.5 flex h-11 w-11 items-center justify-center overflow-hidden rounded-xl bg-white/95 p-1 shadow ring-1 ring-line">
                                <img src="{{ $logo->temporaryUrl() }}" alt="Logo preview" class="h-full w-full object-contain">
                            </span>
                        @endif
                        @if ($pvDays !== null)
                            <span class="absolute left-2.5 top-3 rounded-full bg-white/95 px-2 py-1 text-eyebrow font-black uppercase tracking-wider text-navy-700 backdrop-blur">
                                {{ $pvDays > 0 ? 'in '.$pvDays.'d' : ($pvDays === 0 ? 'Today' : 'past') }}
                            </span>
                        @endif
                    </div>

                    <div class="p-4">
                        <p class="truncate text-base font-bold leading-tight text-navy-900">{{ $pvName }}</p>
                        <p class="mt-0.5 truncate text-micro text-muted">{{ $pvClient ?: 'No client yet' }}</p>

                        <div class="mt-3 space-y-1.5 text-micro">
                            <p class="flex items-center gap-1.5 text-navy-600"><x-icon name="calendar" class="h-3.5 w-3.5 text-navy-400" />
                                {{ $pvStart ? $pvStart->format('j M Y') : 'Pick a start date' }}{{ $pvEnd && $pvStart && ! $pvEnd->isSameDay($pvStart) ? ' – '.$pvEnd->format('j M Y') : '' }}
                            </p>
                            <p class="flex items-center gap-1.5 text-navy-600"><x-icon name="pin" class="h-3.5 w-3.5 text-navy-400" />{{ $city ?: 'City TBD' }}</p>
                            <p class="flex items-center gap-1.5 text-navy-600"><x-icon name="grid" class="h-3.5 w-3.5 text-navy-400" />{{ str($previewType)->replace('_', ' ')->title() }}</p>
                        </div>

                        <div class="mt-3 border-t border-line pt-3">
                            <p class="text-eyebrow font-bold uppercase tracking-wider text-muted">What you'll get</p>
                            <ul class="mt-1.5 space-y-1 text-micro text-navy-600">
                                <li class="flex items-center gap-1.5"><span class="text-emerald-500">✓</span>{{ count($modules) }} {{ str('module')->plural(count($modules)) }} enabled</li>
                                <li class="flex items-center gap-1.5"><span class="{{ $previewDays > 0 ? 'text-emerald-500' : 'text-navy-300' }}">{{ $previewDays > 0 ? '✓' : '•' }}</span>{{ $previewDays > 0 ? $previewDays.' agenda '.str('day')->plural($previewDays).' scaffolded' : 'Agenda days from your dates' }}</li>
                                <li class="flex items-center gap-1.5"><span class="text-emerald-500">✓</span>Plan Studio &amp; Tasks ready</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <p class="mt-3 px-1 text-micro leading-relaxed text-muted">
                    Everything here is editable after creation — this is just the starting shape.
                </p>
            </div>
        </aside>
    </div>
</div>
