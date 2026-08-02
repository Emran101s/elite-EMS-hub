<div class="w-full">

    {{-- ══════════ The event ══════════ --}}
    <div class="mb-5 text-center">
        @if ($event->logo_path)
            <img src="{{ asset($event->logo_path) }}" alt="" class="mx-auto mb-3 h-12 w-auto object-contain">
        @endif
        <p class="eyebrow-gold">{{ \App\Support\Taxonomy::label('event_type', $event->type) }}</p>
        <h1 class="pf mt-1 text-2xl font-bold leading-tight text-navy-900">{{ $event->name }}</h1>
        <p class="mt-1.5 text-sm text-muted">
            {{ $event->starts_at?->format('j F Y') }}@if ($event->ends_at && ! $event->ends_at->isSameDay($event->starts_at)) – {{ $event->ends_at->format('j F Y') }}@endif
            @if ($event->city) · {{ $event->city }}@endif
        </p>
    </div>

    {{-- ══════════ The receipt ══════════ --}}
    @if ($reference)
        <div class="card p-6 text-center">
            <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-emerald-50 text-2xl text-emerald-600">✓</span>
            <h2 class="pf mt-3 text-lg font-bold text-navy-900">You're registered</h2>
            <p class="mx-auto mt-1.5 max-w-[38ch] text-sm text-muted">
                We've got your details for {{ $event->name }}. Keep this reference — it's on your badge.
            </p>

            <p class="mt-4 inline-block rounded-xl bg-navy-900 px-5 py-2.5 font-mono text-xl font-bold tracking-[0.2em] text-white">
                {{ $reference }}
            </p>

            @if ($event->registration_note)
                <p class="mt-4 whitespace-pre-line border-t border-line pt-4 text-left text-[12.5px] leading-relaxed text-navy-600">{{ $event->registration_note }}</p>
            @endif
        </div>

    {{-- ══════════ Closed ══════════ --}}
    @elseif (! $live)
        <div class="card p-6 text-center">
            <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-navy-50 text-xl text-navy-400">
                {{ $full ? '⌛' : '✕' }}
            </span>
            <h2 class="pf mt-3 text-lg font-bold text-navy-900">
                {{ $full ? 'Fully booked' : 'Registration is closed' }}
            </h2>
            <p class="mx-auto mt-1.5 max-w-[38ch] text-sm text-muted">
                {{ $full
                    ? 'Every place for this event has been taken. Get in touch if you would like to be told about a cancellation.'
                    : 'This event is not taking registrations at the moment.' }}
            </p>
        </div>

    {{-- ══════════ The form ══════════ --}}
    @else
        <form wire:submit="register" class="card space-y-4 p-6">
            @if ($event->registration_note)
                <p class="whitespace-pre-line rounded-xl bg-page/70 px-4 py-3 text-[12.5px] leading-relaxed text-navy-600">{{ $event->registration_note }}</p>
            @endif

            {{-- The form is whatever this event asks — see
                 Event::registrationForm(). Autocomplete is still named for the
                 core fields, because a browser filling in a phone number is
                 worth more than a tidy loop. --}}
            @foreach ($fields as $field)
                @php
                    $auto = ['name' => 'name', 'email' => 'email', 'phone' => 'tel',
                             'organization' => 'organization', 'job_title' => 'organization-title'][$field->maps_to] ?? 'off';
                    $err = 'form.'.$field->key;
                @endphp

                <label class="block" wire:key="fld-{{ $field->id }}">
                    <span class="field-label">
                        {{ $field->label }}
                        @if ($field->required)<span class="text-risk">*</span>@endif
                    </span>

                    @switch ($field->type)
                        @case('textarea')
                            <textarea rows="3" wire:model="form.{{ $field->key }}" class="input"
                                      placeholder="{{ $field->placeholder }}"></textarea>
                            @break

                        @case('select')
                            <select wire:model="form.{{ $field->key }}" class="input">
                                @unless ($field->required)<option value="">—</option>@endunless
                                @foreach ($field->options ?? [] as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </select>
                            @break

                        @case('multiselect')
                            <span class="mt-1 grid gap-1.5 sm:grid-cols-2">
                                @foreach ($field->options ?? [] as $option)
                                    <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-line bg-white px-3 py-2 text-[12.5px] text-navy-700">
                                        <input type="checkbox" value="{{ $option }}" wire:model="form.{{ $field->key }}"
                                               class="h-3.5 w-3.5 rounded border-navy-300">
                                        {{ $option }}
                                    </label>
                                @endforeach
                            </span>
                            @break

                        @case('checkbox')
                            <span class="mt-1 flex items-center gap-2">
                                <input type="checkbox" wire:model="form.{{ $field->key }}"
                                       class="h-4 w-4 rounded border-navy-300">
                                <span class="text-[12.5px] text-navy-600">{{ $field->placeholder ?: 'Yes' }}</span>
                            </span>
                            @break

                        @default
                            <input type="{{ ['email' => 'email', 'phone' => 'tel', 'number' => 'number', 'date' => 'date'][$field->type] ?? 'text' }}"
                                   wire:model="form.{{ $field->key }}" class="input"
                                   autocomplete="{{ $auto }}"
                                   placeholder="{{ $field->placeholder }}"
                                   @if ($field->required) required @endif>
                    @endswitch

                    @if ($field->help)
                        <span class="mt-1 block text-[11px] text-muted">{{ $field->help }}</span>
                    @endif
                    @error($err)<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                </label>
            @endforeach

            <button type="submit" class="btn-gold w-full !py-3" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="register">Register for this event</span>
                <span wire:loading wire:target="register">Registering…</span>
            </button>

            @if ($event->registration_capacity)
                <p class="text-center text-[11px] text-muted">
                    {{ max(0, $event->registration_capacity - $event->registeredCount()) }} of
                    {{ $event->registration_capacity }} places left
                </p>
            @endif
        </form>
    @endif

    <p class="mt-5 text-center text-[11px] text-muted">
        Organised by {{ \App\Models\CompanyProfile::current()->name }}
    </p>
</div>
