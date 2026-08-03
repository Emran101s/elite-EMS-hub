@props(['event', 'header'])

{{--
    The event hub's header.

    It used to be four stacked blocks — a 383px hero, a 112px meter strip, a
    41px rail and a 66px live ribbon — 602px of chrome before a single figure of
    the module you actually opened. On a 940px laptop that is the whole screen.

    It is one card now, about 170px, and it says the same things in the same
    order on every event:

        who      crest, name, where and when
        state    the live pill: what is waiting right now
        figures  health, readiness, and how big
        next     the one thing that needs a person, and the way to it

    Every number comes from EventCommandHeader, counted off the event's own
    records, so the header cannot disagree with the tab you click through to.
    The skeleton never changes shape — an event with nothing waiting draws the
    same card as one on fire, or the header reads as a bug rather than as a
    quiet week.
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

    // One tone vocabulary for both meters, so 59% means the same thing in
    // either column rather than each picking its own palette.
    $tone = fn (string $group) => match ($group) {
        'risk' => ['text-red-700', 'bg-risk'],
        'warn' => ['text-amber-700', 'bg-warn'],
        default => ['text-emerald-700', 'bg-track'],
    };

    [$healthText, $healthBar] = $tone($health['score'] === null ? 'warn' : $health['group']);
    [$readyText, $readyBar] = $tone(match (true) {
        $readiness['pct'] >= 70 => 'ok', $readiness['pct'] >= 40 => 'warn', default => 'risk',
    });

    $where = collect([$event->city, $event->country])->filter()->implode(', ');
    $when = $event->starts_at
        ? $event->starts_at->format('j M').' – '.($event->ends_at?->format('j M Y') ?? $event->starts_at->format('Y'))
        : null;
@endphp

<div class="relative isolate overflow-hidden rounded-[20px] border border-line bg-white shadow-[0_14px_40px_-30px_rgba(11,31,58,0.5)]">

    {{-- The cover, as a wash rather than a band. A photograph that costs no
         height still says which event this is the moment the page paints. --}}
    @if ($event->coverUrl())
        <div class="pointer-events-none absolute inset-y-0 end-0 -z-10 w-[46%]" aria-hidden="true">
            <img src="{{ $event->coverUrl() }}" alt="" class="h-full w-full object-cover" style="object-position: 50% 40%">
            <div class="absolute inset-0" style="background: linear-gradient(to right,
                #fff 0%, rgba(255,255,255,0.94) 22%, rgba(255,255,255,0.72) 48%, rgba(255,255,255,0.45) 100%)"></div>
        </div>
    @endif

    {{-- ══ who, and what is true right now ══ --}}
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 pb-3 pt-3.5 lg:px-5">
        <span class="grid h-11 w-11 shrink-0 place-items-center overflow-hidden rounded-xl bg-navy-950 ring-1 ring-gold-400/50">
            @if ($event->logoUrl())
                <img src="{{ $event->logoUrl() }}" alt="" class="h-full w-full object-cover">
            @else
                <x-event-crest :event="$event" class="h-full w-full" />
            @endif
        </span>

        <div class="min-w-0 flex-1">
            <h1 class="pf truncate text-[19px] font-black leading-tight text-navy-950 lg:text-[22px]">
                {{ $title['lead'] }}@if ($title['tail'])<span class="font-bold text-navy-500"> · {{ $title['tail'] }}</span>@endif
            </h1>

            {{-- Where, when, in what building — divided by rules rather than run
                 together, so three facts do not read as one sentence. --}}
            <div class="mt-0.5 flex flex-wrap items-center gap-x-2.5 gap-y-0.5 text-[12px] font-medium text-navy-600">
                @foreach (array_filter([
                    $where ? ['pin', $where] : null,
                    $when ? ['calendar', $when] : null,
                    $event->venue ? ['building', $event->venue->name] : null,
                ]) as [$icon, $text])
                    @if (! $loop->first)<span class="h-3 w-px bg-line" aria-hidden="true"></span>@endif
                    <span class="flex min-w-0 items-center gap-1.5">
                        <x-icon :name="$icon" class="h-3.5 w-3.5 shrink-0 text-navy-300" />
                        <span class="truncate">{{ $text }}</span>
                    </span>
                @endforeach
            </div>
        </div>

        {{-- The live ribbon used to be a 66px bar of its own. It is this pill:
             the same sentence, the same tone, none of the height. --}}
        <span class="flex shrink-0 items-center gap-2 rounded-full bg-navy-950 px-3.5 py-1.5">
            <span class="relative flex h-2 w-2 shrink-0">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full {{ $live['tone'] }} opacity-60"></span>
                <span class="relative inline-flex h-2 w-2 rounded-full {{ $live['tone'] }}"></span>
            </span>
            <span class="text-[11.5px] font-bold text-white">{{ $live['label'] }}</span>
        </span>
    </div>

    {{-- ══ the figures ══
         Health and readiness carry a hairline bar because a percentage with no
         scale beside it is a number you have to remember the meaning of. The
         scale figures are counts and need none. ══ --}}
    <div class="scrollbar-none flex items-stretch gap-x-5 gap-y-2 overflow-x-auto border-t border-line px-4 py-2.5 lg:px-5">
        @foreach ([
            ['Health', $health['score'] === null ? '—' : $health['score'].'%', $healthWord, $health['score'] ?? 0, $healthText, $healthBar],
            ['Readiness', $readiness['pct'].'%', $readyWord, $readiness['pct'], $readyText, $readyBar],
        ] as [$label, $value, $word, $pct, $text, $bar])
            <div class="min-w-[104px] shrink-0">
                <p class="text-eyebrow font-bold uppercase tracking-[0.16em] text-navy-400">{{ $label }}</p>
                <p class="mt-0.5 flex items-baseline gap-1.5">
                    <span class="pf text-[17px] font-black leading-none text-navy-950">{{ $value }}</span>
                    <span class="text-[11px] font-bold {{ $text }}">{{ $word }}</span>
                </p>
                <span class="mt-1.5 block h-1 overflow-hidden rounded-full bg-navy-50">
                    <span class="block h-full rounded-full {{ $bar }}" style="width: {{ max($pct, $pct > 0 ? 4 : 0) }}%"></span>
                </span>
            </div>
        @endforeach

        <span class="w-px shrink-0 self-stretch bg-line" aria-hidden="true"></span>

        @foreach ($header['scale'] as $stat)
            <div class="flex shrink-0 items-center gap-2">
                <x-icon :name="$stat['icon']" class="h-4 w-4 shrink-0 text-navy-300" />
                <span class="leading-tight">
                    <span class="pf block text-[16px] font-black {{ $stat['tone'] ?? 'text-navy-950' }}">{{ $stat['value'] }}</span>
                    <span class="block text-[10.5px] text-muted">{{ $stat['label'] }}</span>
                </span>
            </div>
        @endforeach
    </div>

    {{-- ══ the one thing that needs a person ══
         Always drawn, always in the same place, whether or not there is
         anything to report — "what needs me" should be a glance, not a tour of
         seven tabs. ══ --}}
    @php
        $clear = ! $critical;
        $level = $critical['level'] ?? 'Clear';
    @endphp
    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 border-t border-line bg-page/60 px-4 py-2 lg:px-5">
        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-navy-950 text-gold-400">
            <x-icon :name="$clear ? 'check' : 'flag'" class="h-3.5 w-3.5" />
        </span>

        <span class="shrink-0 text-eyebrow font-bold uppercase tracking-[0.16em] text-navy-400">Next</span>

        <span class="min-w-0 flex-1 truncate text-[13px] font-bold text-navy-950">
            {{ $critical['title'] ?? 'Nothing is waiting on you' }}
            <span class="font-medium text-muted">— {{ $critical['where'] ?? 'Risks, approvals and tasks all clear' }}</span>
        </span>

        {{-- Due and Owner are drawn even when they are em-dashes. Hiding them
             on a quiet event makes the strip a different shape from event to
             event, which is the exact complaint this header was rebuilt to
             answer: one skeleton, always, only the words change. --}}
        <span class="flex shrink-0 items-center gap-2.5 text-[11.5px]">
            <span class="text-muted">Due <span class="font-semibold text-navy-900">{{ $critical['due'] ?? '—' }}</span></span>
            <span class="text-muted">Owner <span class="font-semibold text-navy-900">{{ $critical['owner'] ?? '—' }}</span></span>
        </span>

        <span title="Risk level" @class([
            'shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold',
            'bg-risk/10 text-red-700' => $level === 'Critical',
            'bg-warn/15 text-amber-700' => $level === 'High',
            'bg-gold-50 text-gold-800' => $level === 'Medium',
            'bg-navy-50 text-navy-500' => $level === 'Low',
            'bg-emerald-50 text-emerald-700' => $level === 'Clear',
        ])>{{ $level }}</span>

        <a href="{{ route('events.hub', [$event, 'tab' => $critical['tab'] ?? 'tasks']) }}"
           class="shrink-0 rounded-lg bg-navy-950 px-3 py-1.5 text-[11.5px] font-bold text-white transition hover:bg-navy-800">
            Open →
        </a>
    </div>
</div>
