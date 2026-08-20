{{--
    Built for somebody standing up, holding a phone, with a queue in front of
    them. Big targets, one question on screen, and the counts where they can be
    read at arm's length.
--}}
<div class="space-y-4" wire:poll.20s>

    {{-- ══ where the door is up to ══ --}}
    <div class="rounded-3xl bg-gradient-to-br from-eo-navy to-eo-navy-deep p-5 text-white shadow-eo-float">
        <div class="flex flex-wrap items-baseline gap-x-6 gap-y-2">
            <div>
                <p class="text-eyebrow font-bold uppercase tracking-[0.18em] text-white/50">Arrived</p>
                <p class="text-[34px] font-black leading-none">{{ number_format($arrived) }}</p>
            </div>
            <div>
                <p class="text-eyebrow font-bold uppercase tracking-[0.18em] text-white/50">Still to come</p>
                <p class="text-[34px] font-black leading-none text-eo-teal-lit">{{ number_format($toCome) }}</p>
            </div>
            <div>
                <p class="text-eyebrow font-bold uppercase tracking-[0.18em] text-white/50">Expected</p>
                <p class="text-[22px] font-black leading-none text-white/70">{{ number_format($expected) }}</p>
            </div>

            <a href="{{ route('events.hub', [$event, 'tab' => 'attendees']) }}" wire:navigate
               class="ms-auto text-[11.5px] font-semibold text-white/60 transition hover:text-white">The full list →</a>
        </div>

        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/15">
            <div class="h-full rounded-full bg-eo-teal-lit transition-all duration-500" style="width: {{ $pct }}%"></div>
        </div>
    </div>

    {{-- ══ find them ══ --}}
    <div class="eo-soft-card-pad">
        <input type="search" wire:model.live.debounce.250ms="q" autofocus
               placeholder="Name, email or organisation…"
               class="eo-input h-14 w-full text-lg">

        @if (mb_strlen(trim($q)) < 2)
            <p class="mt-3 text-center text-[12px] text-eo-muted">
                Type two letters to find somebody. Badges scan straight through — this is for the ones without.
            </p>
        @elseif ($matches->isEmpty())
            <div class="mt-4 rounded-2xl bg-eo-warn-soft px-4 py-3">
                <p class="text-[13px] font-bold text-amber-900">Nobody on the list matches “{{ trim($q) }}”.</p>
                <p class="mt-0.5 text-[11.5px] text-amber-900/70">
                    Check the spelling, try their organisation, or register them at
                    <a href="{{ $event->registrationUrl() }}" target="_blank" class="font-semibold underline">the public form</a>.
                </p>
            </div>
        @endif
    </div>

    {{-- ══ who matched ══ --}}
    @foreach ($matches as $person)
        @php $in = $person->checked_in_at; @endphp
        <div wire:key="p-{{ $person->id }}"
             @class(['eo-soft-card-pad flex flex-wrap items-center gap-3',
                 'ring-2 ring-eo-ok' => $justAdmitted === $person->id])>

            <span class="min-w-0 flex-1">
                <span class="block text-[16px] font-bold text-eo-text">{{ $person->name }}</span>
                <span class="block truncate text-[12px] text-eo-muted">
                    {{ collect([$person->organization, $person->job_title, $person->email])->filter()->join(' · ') }}
                </span>

                <span class="mt-1.5 flex flex-wrap items-center gap-1.5">
                    <span class="rounded-full bg-eo-bg px-2 py-0.5 font-mono text-[10.5px] font-bold tracking-[0.14em] text-eo-muted">{{ $person->reference() }}</span>
                    @if ($person->ticket_type)
                        <span class="eo-pill-premium">{{ $person->ticket_type }}</span>
                    @endif
                    @if ($person->vip)
                        <span class="rounded-full bg-eo-navy px-2 py-0.5 text-[10.5px] font-bold text-eo-gold-soft">VIP</span>
                    @endif
                    {{-- What the kitchen needs to know, where the desk will see it. --}}
                    @if ($person->dietary)
                        <span class="rounded-full bg-eo-warn-soft px-2 py-0.5 text-[10.5px] font-bold text-amber-800">{{ $person->dietary }}</span>
                    @endif
                </span>
            </span>

            <span class="shrink-0">
                @if ($person->status === 'cancelled')
                    <span class="text-[12px] font-bold text-eo-muted">Cancelled — see a supervisor</span>
                @elseif ($in)
                    <span class="flex items-center gap-2">
                        <span class="text-[12.5px] font-bold text-eo-ok-ink">In at {{ $in->format('H:i') }}</span>
                        <button type="button" wire:click="undo({{ $person->id }})"
                                class="rounded-lg px-2 py-1 text-[11px] font-semibold text-eo-muted transition hover:text-eo-risk-ink">Undo</button>
                    </span>
                @else
                    <button type="button" wire:click="admit({{ $person->id }})"
                            class="eo-btn-primary !rounded-2xl !px-6 !py-3 text-[14px]">
                        Admit
                    </button>
                @endif
            </span>
        </div>
    @endforeach

    {{-- ══ the last few through ══ --}}
    @if ($recent->isNotEmpty() && mb_strlen(trim($q)) < 2)
        <div class="eo-soft-card-pad">
            <p class="text-eyebrow font-bold uppercase tracking-[0.16em] text-eo-muted">Just arrived</p>
            @foreach ($recent as $person)
                <div wire:key="r-{{ $person->id }}" class="mt-1.5 flex items-baseline gap-2 text-[12.5px]">
                    <span class="font-semibold text-eo-text">{{ $person->name }}</span>
                    <span class="truncate text-eo-muted">{{ $person->organization }}</span>
                    <span class="ms-auto shrink-0 tabular-nums text-eo-muted">{{ $person->checked_in_at?->format('H:i') }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
