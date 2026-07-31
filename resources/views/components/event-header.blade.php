@props(['event', 'header'])

{{--
    The event hub's header.

    What it replaces was one white bar: crest, name, three meters, dates. It
    confirmed the event existed. What you actually open an event to find out is
    how big it is, how close it is, whether it is ready, and what the one thing
    is that needs you today — so that is what it says now, in four blocks:

        the hero      identity, scale, and the next critical action
        the strip     module by module, with the count behind each percentage
        the tabs      where to go
        the live bar  what is true right now

    Every figure comes from EventCommandHeader, counted off the event's own
    records, so the header cannot disagree with the tab you click through to.
--}}

@php
    $title = $header['title'];
    $health = $header['health'];
    $critical = $header['critical'];
    $readiness = $header['readiness'];
    $live = $header['live'];

    $healthWord = $health['score'] === null ? 'Not scored'
        : match ($health['group']) { 'risk' => 'Behind', 'warn' => 'At watch', default => 'Healthy' };

    $readyWord = match (true) {
        $readiness['pct'] >= 90 => 'Excellent',
        $readiness['pct'] >= 70 => 'On track',
        $readiness['pct'] >= 40 => 'Gaps remain',
        default => 'Not ready',
    };
    $readyGroup = match (true) {
        $readiness['pct'] >= 70 => 'ok', $readiness['pct'] >= 40 => 'warn', default => 'risk',
    };
@endphp

{{-- ══════════ HERO ══════════
     No card. The reference runs the photograph to the edge of the work area
     and sets the identity straight on the page, so the negative margins here
     cancel main's padding rather than drawing a box inside it. --}}
<div class="relative isolate -mx-4 -mt-1 overflow-hidden bg-white lg:-mx-6">

    {{-- The field. An uploaded cover if there is one; otherwise the event's own
         generated crest, blown up and dimmed — every event has a mark, so no
         event gets a grey rectangle. It fades to white before it reaches the
         title, which is the only reason text over a photo is ever readable. --}}
    <div class="pointer-events-none absolute inset-y-0 right-0 -z-10 w-full sm:w-[64%]" aria-hidden="true">
        @if ($event->coverUrl())
            <img src="{{ $event->coverUrl() }}" alt="" class="h-full w-full object-cover" style="object-position: 50% 38%">
            {{-- The veil has to be finished before the photo starts, not spread
                 across it: a wash over the whole frame does not make the title
                 readable, it only makes the photograph look broken. Solid white
                 under the words, gone by two-thirds, the picture untouched. --}}
            <div class="absolute inset-0" style="background: linear-gradient(to right,
                #fff 0%, rgba(255,255,255,0.97) 18%, rgba(255,255,255,0.62) 38%,
                rgba(255,255,255,0.16) 60%, rgba(255,255,255,0) 74%)"></div>
        @else
            {{-- No photo: a wash in the event's own accent rather than a grey
                 rectangle. The crest is not repeated here — at this size it is
                 one letterform four hundred pixels tall, and it is already the
                 medallion three inches to the left. --}}
            <div class="h-full w-full" style="background:
                radial-gradient(120% 90% at 82% 8%, {{ $event->accent_color ?: 'rgba(212,175,55,0.30)' }} 0%, transparent 62%),
                radial-gradient(90% 80% at 100% 100%, rgba(11,31,58,0.16) 0%, transparent 60%)"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-white via-white/55 to-transparent"></div>
        @endif
    </div>

    {{-- The band is as tall as the identity column, and the identity column is
         as tall as the event's name — so a one-word event got a squashed header
         and a three-line one got a deep one, and opening two events in a row
         looked like two different products. The floating action card sets the
         floor: at xl, where it is pinned, the band is never shorter than the
         card it holds. Longer names still grow it. --}}
    <div class="flex flex-wrap items-start gap-x-6 gap-y-5 px-4 py-6 lg:px-6 lg:py-8 xl:min-h-[336px] xl:pe-[340px]">

        {{-- crest --}}
        <span class="relative grid h-[92px] w-[92px] shrink-0 place-items-center overflow-hidden rounded-full bg-navy-950 ring-2 ring-gold-400/70 ring-offset-4 ring-offset-white lg:h-[104px] lg:w-[104px]">
            @if ($event->logoUrl())
                <img src="{{ $event->logoUrl() }}" alt="{{ $event->name }}" class="h-full w-full object-cover">
            @else
                <x-event-crest :event="$event" class="h-full w-full" />
            @endif
        </span>

        {{-- identity --}}
        <div class="min-w-0 flex-1">
            <p class="flex items-center gap-1.5 text-eyebrow font-bold uppercase tracking-[0.24em] text-gold-600">
                <span class="h-px w-4 bg-gold-400"></span>Event Hub
            </p>

            <h1 class="pf mt-1.5 max-w-[16ch] text-[30px] font-black leading-[1.05] text-navy-950 lg:text-[40px]">{{ $title['lead'] }}</h1>

            @if ($title['tail'])
                {{-- The edition, set under a rule rather than run on: it is how
                     the name is actually written down. --}}
                <div class="mt-2 flex items-center gap-3">
                    <span class="h-px w-10 bg-gold-400/70"></span>
                    <span class="pf text-[19px] font-bold tracking-wide text-gold-700 lg:text-[22px]">{{ $title['tail'] }}</span>
                    <span class="h-px w-10 bg-gold-400/70"></span>
                </div>
            @endif

            {{-- Where, when, and in what building — divided by rules rather
                 than run together, as drawn. The client, the PM and the stage
                 are not lost: they are on the Overview beneath this. --}}
            <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[13px] font-medium text-navy-700">
                @foreach (array_filter([
                    ($event->city || $event->country) ? ['pin', collect([$event->city, $event->country])->filter()->implode(', ')] : null,
                    $event->starts_at ? ['calendar', $event->starts_at->format('M j').' – '.($event->ends_at?->format('M j, Y') ?? $event->starts_at->format('Y'))] : null,
                    $event->venue ? ['building', $event->venue->name] : null,
                ]) as [$icon, $text])
                    @if (! $loop->first)<span class="h-4 w-px bg-line" aria-hidden="true"></span>@endif
                    <span class="flex items-center gap-1.5"><x-icon :name="$icon" class="h-4 w-4 text-navy-300" />{{ $text }}</span>
                @endforeach
            </div>

            {{-- how big --}}
            <div class="mt-5 flex flex-wrap items-center gap-x-4 gap-y-3">
                @foreach ($header['scale'] as $stat)
                    @if (! $loop->first)
                        <span class="h-9 w-px bg-line" aria-hidden="true"></span>
                    @endif
                    <div class="flex items-center gap-2">
                        <x-icon :name="$stat['icon']" class="h-4 w-4 shrink-0 text-navy-300" />
                        <span class="leading-tight">
                            <span class="pf block text-[21px] font-black {{ $stat['tone'] ?? 'text-navy-950' }}">{{ $stat['value'] }}</span>
                            <span class="block text-[11px] font-semibold text-muted">{{ $stat['label'] }}</span>
                        </span>
                    </div>
                @endforeach
            </div>

        </div>

        {{-- ── next critical action ──
             One card, always in the same place, so "what needs me" is a glance
             rather than a tour of seven tabs. --}}
        <div class="w-full shrink-0 rounded-2xl border border-line bg-white/95 p-4 shadow-[0_14px_36px_-24px_rgba(11,31,58,0.55)] backdrop-blur sm:w-[298px] xl:absolute xl:end-6 xl:top-8">
            <p class="flex items-center gap-1.5 text-eyebrow font-bold uppercase tracking-[0.18em] text-navy-400">
                Next critical action
                <x-icon name="flag" class="ms-auto h-3.5 w-3.5 text-gold-600" />
            </p>

            {{-- One skeleton, whether or not there is anything to report: mark,
                 headline, source, then Due / Owner / Risk level, then the way
                 in. An empty state built out of different parts makes the
                 header change shape from event to event, which reads as a bug
                 in the design rather than as a quiet week. --}}
            @php
                $clear = ! $critical;
                $level = $critical['level'] ?? 'Clear';
            @endphp

            <div class="mt-3 flex items-start gap-2.5">
                {{-- Same mark on every event, in the same navy and gold. Only
                     the glyph changes — a card that swaps its whole palette
                     when the news is good reads as a different component, not
                     as the same one saying something different. --}}
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-navy-950 text-gold-400">
                    <x-icon :name="$clear ? 'check' : 'calendar'" class="h-4 w-4" />
                </span>
                <div class="min-w-0">
                    <p class="pf line-clamp-2 text-[14px] font-bold leading-snug text-navy-950">
                        {{ $critical['title'] ?? 'Nothing is waiting on you' }}
                    </p>
                    <p class="mt-0.5 truncate text-[11.5px] text-muted">
                        {{ $critical['where'] ?? 'Risks, approvals and tasks all clear' }}
                    </p>
                </div>
            </div>

            <dl class="mt-3 space-y-1.5 border-t border-line pt-3 text-[11.5px]">
                @foreach ([['Due', $critical['due'] ?? '—'], ['Owner', $critical['owner'] ?? '—']] as [$term, $value])
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-muted">{{ $term }}</dt>
                        <dd class="truncate font-semibold text-navy-900">{{ $value }}</dd>
                    </div>
                @endforeach
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-muted">Risk level</dt>
                    <dd>
                        <span @class([
                            'rounded-full px-2 py-0.5 text-[10px] font-bold',
                            'bg-risk/10 text-red-700' => $level === 'Critical',
                            'bg-warn/15 text-amber-700' => $level === 'High',
                            'bg-gold-50 text-gold-800' => $level === 'Medium',
                            'bg-navy-50 text-navy-500' => $level === 'Low',
                            'bg-emerald-50 text-emerald-700' => $level === 'Clear',
                        ])>{{ $level }}</span>
                    </dd>
                </div>
            </dl>

            <a href="{{ route('events.hub', [$event, 'tab' => $critical['tab'] ?? 'tasks']) }}"
               class="mt-3.5 flex h-10 items-center justify-center gap-2 rounded-xl bg-navy-950 text-[12.5px] font-bold text-white transition hover:bg-navy-800">
                {{ $critical['cta'] ?? 'Open the task board' }} →
            </a>
        </div>
    </div>

</div>

{{-- ══════════ HEALTH STRIP ══════════
     A ring at each end and the modules between them. The two rings are not the
     same number twice: health is how the work is going, readiness is whether
     the gates to go live are met — an event can be healthy and not ready. --}}
<div class="mt-4 overflow-hidden rounded-[22px] border border-line bg-white shadow-[0_10px_30px_-24px_rgba(11,31,58,0.45)]">
    <div class="flex items-stretch divide-x divide-line overflow-x-auto scrollbar-none">

        <div class="flex shrink-0 items-center gap-2.5 px-4 py-3.5">
            <span class="max-w-[64px] text-eyebrow font-bold uppercase leading-tight tracking-[0.14em] text-navy-400">Event Health Score</span>
            <span class="text-center">
                <x-health-ring :percent="$health['score']" :group="$health['group']" size="h-14 w-14" textSize="text-[13px]" />
                <span class="mt-1 block text-[10.5px] font-bold text-navy-600">{{ $healthWord }}</span>
            </span>
        </div>

        @foreach ($header['meters'] as $meter)
            <div class="min-w-[118px] flex-1 shrink-0 px-3.5 py-3">
                <p class="flex items-center gap-1.5 text-eyebrow font-bold uppercase tracking-[0.14em] text-navy-400">
                    <x-icon :name="$meter['icon']" class="h-3.5 w-3.5 text-navy-300" />{{ $meter['label'] }}
                </p>
                <p class="pf mt-1 text-[22px] font-black leading-none text-navy-950">
                    {{ $meter['pct'] === null ? '—' : $meter['pct'].'%' }}
                </p>
                <div class="mt-2 h-[3px] overflow-hidden rounded-full bg-navy-50">
                    <div @class([
                        'h-full rounded-full',
                        'bg-track' => $meter['pct'] !== null && $meter['pct'] >= 81,
                        'bg-warn' => $meter['pct'] !== null && $meter['pct'] >= 61 && $meter['pct'] < 81,
                        'bg-risk' => $meter['pct'] !== null && $meter['pct'] < 61,
                        'bg-navy-100' => $meter['pct'] === null,
                    ]) style="width: {{ $meter['pct'] ?? 100 }}%"></div>
                </div>
                <p class="mt-1.5 truncate text-[10.5px] text-muted" title="{{ $meter['detail'] }}">{{ $meter['detail'] }}</p>
            </div>
        @endforeach

        {{-- The gates are named in the tooltip: a readiness number you cannot
             take apart is a number you have to believe. --}}
        <div class="flex shrink-0 items-center gap-2.5 px-4 py-3.5"
             title="{{ collect($readiness['gates'])->map(fn ($g) => ($g['met'] ? '✓ ' : '✗ ').$g['label'].' — '.$g['note'])->implode("\n") }}">
            <span class="max-w-[64px] text-right text-eyebrow font-bold uppercase leading-tight tracking-[0.14em] text-navy-400">Readiness to Go Live</span>
            <span class="text-center">
                <x-health-ring :percent="$readiness['pct']" :group="$readyGroup" size="h-14 w-14" textSize="text-[13px]" />
                <span class="mt-1 block text-[10.5px] font-bold text-navy-600">{{ $readyWord }}</span>
            </span>
        </div>
    </div>
</div>
