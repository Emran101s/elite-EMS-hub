<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">

    {{-- ── Form ── --}}
    <form wire:submit="save" class="card space-y-6 p-6">
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
                @error('type') <p class="mt-1 text-sm text-risk">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="budget" class="mb-1.5 block text-sm font-medium text-navy-800">Budget (USD)</label>
                <input id="budget" type="number" step="0.01" min="0" wire:model="budget" class="input" placeholder="250000">
                @error('budget') <p class="mt-1 text-sm text-risk">{{ $message }}</p> @enderror
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
                @error('country') <p class="mt-1 text-sm text-risk">{{ $message }}</p> @enderror
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

        {{-- ── Avatar picker ── --}}
        <div>
            <div class="mb-3 flex items-center justify-between">
                <div>
                    <p class="text-sm font-bold text-navy-900">Event Avatar</p>
                    <p class="text-xs text-muted">The visual identity for this event across the whole platform.</p>
                </div>
                <a href="{{ route('events.avatars') }}" class="text-xs font-semibold text-gold-600 hover:text-gold-700">Browse library</a>
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
            @error('avatar_id') <p class="mt-1 text-sm text-risk">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-line pt-5">
            <a href="{{ route('events.index') }}" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-navy-600 hover:text-navy-900">Cancel</a>
            <button type="submit" class="btn-gold">
                <span wire:loading.remove wire:target="save">Create Event</span>
                <span wire:loading wire:target="save">Creating…</span>
            </button>
        </div>
    </form>

    {{-- ── Island preview ── --}}
    <div class="space-y-4">
        <div class="card p-5">
            <h3 class="mb-4 text-xs font-bold uppercase tracking-wide text-navy-900">Island Preview</h3>
            @php $selected = $avatars->firstWhere('id', $avatar_id); @endphp
            <div class="flex flex-col items-center rounded-2xl bg-[radial-gradient(ellipse_at_center,rgba(212,175,55,0.08),transparent_70%)] py-6">
                <x-event-avatar :avatar="$selected" :ring="false" size="xl" />
                <p class="mt-4 text-sm font-bold text-navy-900">{{ $name !== '' ? $name : 'Your event' }}</p>
                <p class="text-xs text-muted">
                    {{ str($type)->replace('_', ' ')->title() }}
                    @if ($city !== '') · {{ $city }}, {{ $country }} @endif
                </p>
                @if ($selected)
                    <p class="mt-2 text-[0.65rem] text-muted">{{ $selected->name }} — {{ $selected->subtitle }}</p>
                    <div class="mt-2 flex gap-1.5">
                        @foreach ($selected->colors as $color)
                            <span class="h-3 w-3 rounded-full ring-1 ring-line" style="background: {{ $color }}"></span>
                        @endforeach
                    </div>
                @endif
            </div>
            <p class="mt-3 text-[0.65rem] text-muted">
                This is how the event island will appear in the Operations Hub — the health ring
                attaches automatically once the event is live.
            </p>
        </div>
    </div>
</div>
