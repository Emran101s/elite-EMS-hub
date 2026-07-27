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

            <label class="block">
                <span class="field-label">Full name <span class="text-risk">*</span></span>
                <input type="text" wire:model="name" class="input" autocomplete="name" required>
                @error('name')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
            </label>

            <label class="block">
                <span class="field-label">Email <span class="text-risk">*</span></span>
                <input type="email" wire:model="email" class="input" autocomplete="email" required>
                @error('email')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
            </label>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="field-label">Phone</span>
                    <input type="tel" wire:model="phone" class="input" autocomplete="tel">
                </label>
                @if ($types)
                    <label class="block">
                        <span class="field-label">Ticket</span>
                        <select wire:model="ticket_type" class="input">
                            @foreach ($types as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach
                        </select>
                    </label>
                @endif
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="field-label">Organisation</span>
                    <input type="text" wire:model="organization" class="input" autocomplete="organization">
                </label>
                <label class="block">
                    <span class="field-label">Job title</span>
                    <input type="text" wire:model="job_title" class="input" autocomplete="organization-title">
                </label>
            </div>

            <label class="block">
                <span class="field-label">Dietary requirements</span>
                <input type="text" wire:model="dietary" class="input" placeholder="Optional — allergies, preferences">
            </label>

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
