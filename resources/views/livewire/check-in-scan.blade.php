@php
    // One state, one colour, one sentence. Whoever is holding this phone is
    // standing in front of a person with a queue behind them.
    [$tone, $glyph, $headline] = match ($state) {
        'found'     => ['bg-navy-50 text-navy-500', '?', 'Ready to admit'],
        'done'      => ['bg-emerald-50 text-emerald-600', '✓', 'Admitted'],
        'already'   => ['bg-amber-50 text-amber-600', '!', 'Already checked in'],
        'cancelled' => ['bg-red-50 text-risk', '✕', 'This registration was cancelled'],
        default     => ['bg-navy-50 text-navy-400', '?', 'Badge not recognised'],
    };
@endphp

<div class="w-full text-center">
    <p class="eyebrow-gold">{{ $event->name }}</p>

    <div class="card mt-3 p-6">
        <span class="mx-auto grid h-16 w-16 place-items-center rounded-full text-3xl {{ $tone }}">{{ $glyph }}</span>

        <h1 class="pf mt-4 text-xl font-bold text-navy-900">{{ $headline }}</h1>

        @if ($attendee)
            <p class="mt-3 text-lg font-bold text-navy-900">{{ $attendee->name }}</p>
            @if ($attendee->organization)
                <p class="text-sm text-muted">{{ $attendee->organization }}</p>
            @endif
            <p class="mt-2 inline-block rounded-lg bg-page px-3 py-1 font-mono text-xs font-bold tracking-[0.18em] text-navy-500">
                {{ $attendee->reference() }}
            </p>

            @if ($attendee->ticket_type)
                <p class="mt-2"><span class="chip-gold">{{ $attendee->ticket_type }}</span></p>
            @endif

            @if ($attendee->dietary)
                <p class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-[12px] font-semibold text-amber-800">
                    Dietary: {{ $attendee->dietary }}
                </p>
            @endif
        @endif

        @if ($state === 'found')
            {{-- A second tap on purpose: a camera reads a badge lying on a table
                 as readily as one round a neck, and a door that admits people by
                 accident is worse than one that asks. --}}
            <button type="button" wire:click="admit" class="btn-gold mt-5 w-full !py-3 text-base">
                Admit {{ str($attendee->name)->before(' ') }}
            </button>
        @elseif ($state === 'done')
            <p class="mt-4 text-sm text-muted">Checked in at {{ $attendee->checked_in_at?->format('H:i') }}.</p>
        @elseif ($state === 'already')
            <p class="mt-4 text-sm text-muted">
                Admitted at {{ $attendee->checked_in_at?->format('H:i') }}. If that was not them, find a supervisor.
            </p>
        @elseif ($state === 'unknown')
            <p class="mx-auto mt-4 max-w-[34ch] text-sm text-muted">
                This badge is not on the list for this event. Check they are at the right door, or register them at the desk.
            </p>
        @endif
    </div>
</div>
