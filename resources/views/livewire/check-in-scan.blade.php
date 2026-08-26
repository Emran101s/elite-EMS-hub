@php
    // One state, one colour, one sentence. Whoever is holding this phone is
    // standing in front of a person with a queue behind them.
    [$tone, $glyph, $headline] = match ($state) {
        'found'     => ['bg-page text-muted', '?', 'Ready to admit'],
        'done'      => ['bg-success-soft text-success-ink', '✓', 'Admitted'],
        'already'   => ['bg-warning-soft text-amber-800', '!', 'Already checked in'],
        'cancelled' => ['bg-danger-soft text-danger-ink', '✕', 'This registration was cancelled'],
        'throttled' => ['bg-warning-soft text-amber-800', '…', 'Slow down'],
        default     => ['bg-page text-muted', '?', 'Badge not recognised'],
    };
@endphp

<div class="w-full text-center">
    @if ($state !== 'throttled')
        <p class="text-eyebrow font-bold uppercase tracking-[0.2em] text-gold-700">{{ $event->name }}</p>
    @endif

    <div class="rounded-lg border border-line bg-white shadow-raise p-5 mt-3">
        <span class="mx-auto grid h-16 w-16 place-items-center rounded-full text-3xl {{ $tone }}">{{ $glyph }}</span>

        <h1 class="mt-4 text-xl font-bold text-ink">{{ $headline }}</h1>

        @if ($attendee)
            <p class="mt-3 text-lg font-bold text-ink">{{ $attendee->name }}</p>
            @if ($attendee->organization)
                <p class="text-sm text-muted">{{ $attendee->organization }}</p>
            @endif
            <p class="mt-2 inline-block rounded-lg bg-page px-3 py-1 font-mono text-xs font-bold tracking-[0.18em] text-muted">
                {{ $attendee->reference() }}
            </p>

            @if ($attendee->ticket_type)
                <p class="mt-2"><span class="inline-flex items-center rounded-full bg-gold-50 px-2 py-0.5 text-[10px] font-bold text-gold-700 ring-1 ring-gold-200">{{ $attendee->ticket_type }}</span></p>
            @endif

            @if ($attendee->dietary)
                <p class="mt-3 rounded-xl bg-warning-soft px-3 py-2 text-[12px] font-semibold text-amber-800">
                    Dietary: {{ $attendee->dietary }}
                </p>
            @endif
        @endif

        @if ($state === 'found')
            {{-- A second tap on purpose: a camera reads a badge lying on a table
                 as readily as one round a neck, and a door that admits people by
                 accident is worse than one that asks. --}}
            <button type="button" wire:click="admit" class="rounded-full bg-gold-500 px-4 py-2 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400 mt-5 w-full !py-3 text-base">
                Admit {{ str($attendee->name)->before(' ') }}
            </button>
        @elseif ($state === 'done')
            <p class="mt-4 text-sm text-muted">Checked in at {{ $attendee->checked_in_at?->format('H:i') }}.</p>
        @elseif ($state === 'already')
            <p class="mt-4 text-sm text-muted">
                Admitted at {{ $attendee->checked_in_at?->format('H:i') }}. If that was not them, find a supervisor.
            </p>
        @elseif ($state === 'throttled')
            <p class="mx-auto mt-4 max-w-[34ch] text-sm text-muted">
                Too many scans from this phone just now. Wait a minute and try again.
            </p>
        @elseif ($state === 'unknown')
            <p class="mx-auto mt-4 max-w-[34ch] text-sm text-muted">
                This badge is not on the list for this event. Check they are at the right door, or register them at the desk.
            </p>
        @endif

        {{-- ══ the room they are standing at ══

             The QR is printed once and cannot carry a room, so the room is
             chosen here: whoever is on the door scans the badge and taps the
             session in front of them. --}}
        @php $today = $this->sessionsToday(); @endphp

        @if ($attendee && ! in_array($state, ['unknown', 'cancelled', 'throttled'], true) && $today->isNotEmpty())
            <div class="mt-5 border-t border-line pt-4 text-left">
                <p class="text-eyebrow font-bold uppercase tracking-[0.16em] text-muted">Booked today</p>

                @foreach ($today as $session)
                    @php $in = $session->pivot->checked_in_at; @endphp
                    <div wire:key="sess-{{ $session->id }}"
                         class="mt-2 flex items-center gap-3 rounded-2xl border border-line bg-white px-3 py-2.5">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[13px] font-bold text-ink">{{ $session->title }}</span>
                            <span class="block text-[11.5px] text-muted">
                                @if ($session->starts_at) {{ substr($session->starts_at, 0, 5) }} @endif
                                @if ($session->room) · {{ $session->room->name }} @endif
                            </span>
                        </span>

                        @if ($in)
                            <span class="shrink-0 text-[11.5px] font-bold text-success-ink">In at {{ \Illuminate\Support\Carbon::parse($in)->format('H:i') }}</span>
                        @else
                            <button type="button" wire:click="admitToSession({{ $session->id }})"
                                    class="shrink-0 rounded-xl bg-navy-900 px-3 py-2 text-[12px] font-bold text-white transition hover:bg-navy-950">
                                Admit here
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
