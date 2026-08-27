@php
    // Concourse hub home. The Event Command Header, Module Nav, Pulse Strip and
    // Inspector (rendered around this by events/hub.blade.php) answer "what is
    // this event / what stage / what's blocked". This page answers the one
    // thing they don't lay out visually: the event AS A HUB — which of its
    // modules are ready, and which need work — the same honeycomb language the
    // Event Portfolio uses for each hub card, now for this single event.
    use App\Support\Workflow;

    $badge = fn (?string $key = null) => \App\Models\Event::moduleColor($key);

    // Real readiness gates — Agenda, Speakers, Suppliers, Venue, Transport,
    // Approvals, Risk — each a module cell in this hub's honeycomb.
    $readiness = app(\App\Services\EventCommandHeader::class)->readiness($event);
    $gateMeta = [
        'Agenda confirmed' => ['calendar', 'Agenda'],
        'Speakers confirmed' => ['users', 'Speakers'],
        'Suppliers contracted' => ['archive', 'Suppliers'],
        'Venue assigned' => ['building', 'Venue'],
        'Transport ready' => ['truck', 'Transport'],
        'Approvals cleared' => ['check', 'Approvals'],
        'No open severe risk' => ['bell', 'Risk'],
    ];

    $docChip = function (?string $status): array {
        if (! $status) {
            return ['Not started', 'pend'];
        }

        return [Workflow::label('contract_status', $status), $status === 'signed' ? 'done' : 'live'];
    };

    $contract = $event->contract;
    $brief = $event->brief;
    [$contractLabel, $contractTone] = $docChip($contract?->status);
    $briefTone = $brief ? 'done' : 'pend';
    $briefLabel = $brief ? 'Ready' : 'Not started';

    $speakerCount = $event->speakers->count();
    $speakersConfirmed = $event->speakers->where('status', 'confirmed')->count();
    $registeredCount = $event->attendees->count();
    $registrationLabel = $event->registration_open ? 'Open' : ($registeredCount > 0 ? 'In progress' : 'Not started');
    $registrationTone = $event->registration_open ? 'done' : ($registeredCount > 0 ? 'live' : 'pend');

    $doors = [
        ['key' => 'brief', 'icon' => 'clipboard', 'label' => 'Brief', 'chip' => $briefLabel, 'tone' => $briefTone, 'sub' => 'The plan'],
        ['key' => 'contract', 'icon' => 'identification', 'label' => 'Contract', 'chip' => $contractLabel, 'tone' => $contractTone, 'sub' => $contract?->reference ?? 'No document yet'],
        ['key' => 'speakers', 'icon' => 'sparkles', 'label' => 'Speakers', 'chip' => $speakerCount ? $speakersConfirmed.'/'.$speakerCount.' confirmed' : 'None yet', 'tone' => $speakerCount && $speakersConfirmed === $speakerCount ? 'done' : ($speakerCount ? 'live' : 'pend'), 'sub' => 'Roster & schedule'],
        ['key' => 'attendees', 'icon' => 'users', 'label' => 'Registration', 'chip' => $registrationLabel, 'tone' => $registrationTone, 'sub' => $registeredCount.' registered'],
    ];
    $doors = array_values(array_filter($doors, fn ($d) => $event->moduleEnabled($d['key'])));

    $toneClass = fn ($t) => match ($t) { 'done' => 'done', 'risk' => 'risk', default => 'pend' };
@endphp

<div class="cx-canvas cx-hubhome">
    {{-- ══ This hub's modules — the readiness honeycomb ══ --}}
    <div class="cx-card cx-reveal cx-d1">
        <div class="cx-hh-head">
            <div>
                <span class="cx-eyebrow">This event, as a hub</span>
                <p class="cx-hh-title">Modules &amp; readiness</p>
            </div>
            <div class="cx-hh-score">
                <span class="cx-hh-pct">{{ $readiness['pct'] }}<s>%</s></span>
                <span class="cx-hh-lbl">{{ $readiness['met'] }} of {{ $readiness['total'] }} ready</span>
            </div>
        </div>

        @if (! empty($readiness['gates']))
            <div class="cx-comb" style="margin-top:6px">
                @foreach ($readiness['gates'] as $gate)
                    @php
                        [$gi, $gl] = $gateMeta[$gate['label']] ?? ['grid', (string) str($gate['label'])->words(1)];
                        $cls = $gate['met'] ? 'done' : (str_contains(strtolower($gate['label']), 'risk') ? 'risk' : 'pend');
                    @endphp
                    <span class="cx-cell {{ $cls }}" title="{{ $gate['label'] }} — {{ $gate['note'] }}">
                        <x-icon :name="$gi" class="h-3.5 w-3.5" />
                        <span class="cx-clbl">{{ $gl }}</span>
                    </span>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ══ Key module doors — Brief, Contract, Speakers, Registration ══ --}}
    @if (! empty($doors))
        <div class="cx-doorgrid cx-reveal cx-d2">
            @foreach ($doors as $door)
                <a href="{{ route('events.hub', [$event, 'tab' => $door['key']]) }}" wire:navigate class="cx-door">
                    <span class="cx-door-ic" style="color: {{ $badge($door['key']) }}; background: {{ $badge($door['key']) }}18">
                        <x-icon :name="$door['icon']" class="h-4 w-4" />
                    </span>
                    <span class="cx-door-body">
                        <span class="cx-door-lbl">{{ $door['label'] }}</span>
                        <span class="cx-door-sub">{{ $door['sub'] }}</span>
                    </span>
                    <span class="cx-door-chip cx-dt-{{ $toneClass($door['tone']) }}">{{ $door['chip'] }}</span>
                </a>
            @endforeach
        </div>
    @endif
</div>

{{-- The mission feed keeps its own workspace element below. --}}
<div class="mt-3">
    <x-eo.hubx-mission-feed :event="$event" />
</div>
