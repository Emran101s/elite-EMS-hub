{{--
    Built for somebody standing up, holding a phone, with a queue in front of
    them. Big targets, one question on screen, and the counts where they can be
    read at arm's length.
--}}
<div class="space-y-4" wire:poll.20s>

    {{-- ══ where the door is up to ══ --}}
    <div class="rounded-3xl bg-navy-950 p-5 text-white">
        <div class="flex flex-wrap items-baseline gap-x-6 gap-y-2">
            <div>
                <p class="text-eyebrow font-bold uppercase tracking-[0.18em] text-white/50">Arrived</p>
                <p class="pf text-[34px] font-black leading-none">{{ number_format($arrived) }}</p>
            </div>
            <div>
                <p class="text-eyebrow font-bold uppercase tracking-[0.18em] text-white/50">Still to come</p>
                <p class="pf text-[34px] font-black leading-none text-gold-400">{{ number_format($toCome) }}</p>
            </div>
            <div>
                <p class="text-eyebrow font-bold uppercase tracking-[0.18em] text-white/50">Expected</p>
                <p class="pf text-[22px] font-black leading-none text-white/70">{{ number_format($expected) }}</p>
            </div>

            <a href="{{ route('events.hub', [$event, 'tab' => 'attendees']) }}" wire:navigate
               class="ms-auto text-[11.5px] font-semibold text-white/60 transition hover:text-white">The full list →</a>
        </div>

        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/15">
            <div class="h-full rounded-full bg-gold-400 transition-all duration-500" style="width: {{ $pct }}%"></div>
        </div>
    </div>

    {{-- ══ find them ══ --}}
    <div class="card p-4">
        <input type="search" wire:model.live.debounce.250ms="q" autofocus
               placeholder="Name, email or organisation…"
               class="input h-14 w-full text-lg">

        @if (mb_strlen(trim($q)) < 2)
            <p class="mt-3 text-center text-[12px] text-muted">
                Type two letters to find somebody. Badges scan straight through — this is for the ones without.
            </p>
        @elseif ($matches->isEmpty())
            <div class="mt-4 rounded-2xl bg-amber-50 px-4 py-3">
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
             @class(['card flex flex-wrap items-center gap-3 p-4',
                 'ring-2 ring-emerald-400' => $justAdmitted === $person->id])>

            <span class="min-w-0 flex-1">
                <span class="block text-[16px] font-bold text-navy-950">{{ $person->name }}</span>
                <span class="block truncate text-[12px] text-muted">
                    {{ collect([$person->organization, $person->job_title, $person->email])->filter()->join(' · ') }}
                </span>

                <span class="mt-1.5 flex flex-wrap items-center gap-1.5">
                    <span class="rounded-full bg-navy-50 px-2 py-0.5 font-mono text-[10.5px] font-bold tracking-[0.14em] text-navy-500">{{ $person->reference() }}</span>
                    @if ($person->ticket_type)
                        <span class="rounded-full bg-gold-100 px-2 py-0.5 text-[10.5px] font-bold text-gold-800">{{ $person->ticket_type }}</span>
                    @endif
                    @if ($person->vip)
                        <span class="rounded-full bg-navy-950 px-2 py-0.5 text-[10.5px] font-bold text-gold-400">VIP</span>
                    @endif
                    {{-- What the kitchen needs to know, where the desk will see it. --}}
                    @if ($person->dietary)
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10.5px] font-bold text-amber-800">{{ $person->dietary }}</span>
                    @endif
                </span>
            </span>

            <span class="shrink-0">
                @if ($person->status === 'cancelled')
                    <span class="text-[12px] font-bold text-navy-400">Cancelled — see a supervisor</span>
                @elseif ($in)
                    <span class="flex items-center gap-2">
                        <span class="text-[12.5px] font-bold text-emerald-700">In at {{ $in->format('H:i') }}</span>
                        <button type="button" wire:click="undo({{ $person->id }})"
                                class="rounded-lg px-2 py-1 text-[11px] font-semibold text-navy-400 transition hover:text-red-600">Undo</button>
                    </span>
                @else
                    <button type="button" wire:click="admit({{ $person->id }})"
                            class="rounded-2xl bg-navy-950 px-6 py-3 text-[14px] font-bold text-white transition hover:bg-navy-800">
                        Admit
                    </button>
                @endif
            </span>
        </div>
    @endforeach

    {{-- ══ the last few through ══ --}}
    @if ($recent->isNotEmpty() && mb_strlen(trim($q)) < 2)
        <div class="card p-4">
            <p class="text-eyebrow font-bold uppercase tracking-[0.16em] text-navy-400">Just arrived</p>
            @foreach ($recent as $person)
                <div wire:key="r-{{ $person->id }}" class="mt-1.5 flex items-baseline gap-2 text-[12.5px]">
                    <span class="font-semibold text-navy-800">{{ $person->name }}</span>
                    <span class="truncate text-muted">{{ $person->organization }}</span>
                    <span class="ms-auto shrink-0 tabular-nums text-navy-400">{{ $person->checked_in_at?->format('H:i') }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
