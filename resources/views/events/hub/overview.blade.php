@php
    // Mission Control pass: Overview answers five questions only — what is
    // this event, what stage is it in, what's blocked, what needs action,
    // what should I open next. The Event Command Header, Module Navigation
    // Bar, Event Pulse Strip and Inspector (all rendered above/around this
    // partial by events/hub.blade.php) already answer those. Everything this page
    // used to stack underneath them — Task/Budget/Supplier summaries, Team
    // Workload, deadline and alert feeds, Recent activity, a 7-card door
    // grid — was detail belonging to a module, a duplicate of the shell
    // above, or both. Task/Budget/Supplier summaries moved into their own
    // module's workspace; Team Workload and Recent Activity moved into the
    // Event Utilities drawer; the rest was cut outright as pure duplication
    // (see docs/event-hub-ia-audit for the reasoning behind each one).
    //
    // What's left: Mission Feed (the one workspace element Overview still
    // owns), and a slim "more doors" row for the four modules the compact
    // Module Rail doesn't surface directly — Brief, Speakers, Contract,
    // Registration. Agenda/Budget/Transport/Approvals/Venue/Suppliers/Files
    // are already one click away on the rail; repeating them here again
    // would be the exact duplication this pass removed everywhere else.
    use App\Support\Workflow;

    $badge = fn (?string $key = null) => \App\Models\Event::moduleColor($key);

    $docChip = function (?string $status): array {
        if (! $status) {
            return ['Not started', 'color: #94A3B8; background: #94A3B815; box-shadow: inset 0 0 0 1px #94A3B833'];
        }

        $hex = Workflow::color('contract_status', $status);

        return [
            Workflow::label('contract_status', $status),
            'color: '.$hex.'; background: '.$hex.'15; box-shadow: inset 0 0 0 1px '.$hex.'33',
        ];
    };

    $contract = $event->contract;
    $brief = $event->brief;
    [$contractLabel, $contractStyle] = $docChip($contract?->status);
    [$briefLabel, $briefStyle] = $brief ? ['Ready', $docChip('signed')[1]] : $docChip(null);

    $speakerCount = $event->speakers->count();
    $speakersConfirmed = $event->speakers->where('status', 'confirmed')->count();

    $readiness = function (?int $score): array {
        if ($score === null) {
            return ['Not started', 'color: #94A3B8; background: #94A3B815; box-shadow: inset 0 0 0 1px #94A3B833'];
        }
        if ($score >= 100) {
            return ['Ready', 'color: #22C55E; background: #22C55E15; box-shadow: inset 0 0 0 1px #22C55E33'];
        }

        return ['In progress', 'color: #F59E0B; background: #F59E0B15; box-shadow: inset 0 0 0 1px #F59E0B33'];
    };

    $registeredCount = $event->attendees->count();
    $registrationScore = $event->registration_open ? 100 : ($registeredCount > 0 ? 50 : null);
    [$registrationLabel, $registrationStyle] = $readiness($registrationScore);

    $sym = $event->currencySymbol();
    $gap = strlen($sym) > 1 ? ' ' : '';
@endphp

<x-eo.hubx-mission-feed :event="$event" />

@php
    $moreDoors = [
        ['key' => 'speakers', 'icon' => 'sparkles', 'label' => 'Speakers', 'chip' => null, 'chipStyle' => null, 'sub' => $speakersConfirmed.'/'.$speakerCount.' confirmed'],
        ['key' => 'brief', 'icon' => 'clipboard', 'label' => 'Brief', 'chip' => $briefLabel, 'chipStyle' => $briefStyle, 'sub' => null],
        ['key' => 'contract', 'icon' => 'identification', 'label' => 'Contract', 'chip' => $contractLabel, 'chipStyle' => $contractStyle, 'sub' => $contract ? $contract->reference : null],
        ['key' => 'attendees', 'icon' => 'users', 'label' => 'Registration', 'chip' => $registrationLabel, 'chipStyle' => $registrationStyle, 'sub' => $registeredCount.' registered'],
    ];
    $moreDoors = array_values(array_filter($moreDoors, fn ($d) => $event->moduleEnabled($d['key'])));
@endphp

@if (! empty($moreDoors))
    <div class="mt-3 flex flex-wrap items-center gap-2">
        <span class="mr-0.5 text-[9.5px] font-bold uppercase tracking-[0.05em] text-muted">More</span>
        @foreach ($moreDoors as $door)
            <a href="{{ route('events.hub', [$event, 'tab' => $door['key']]) }}" wire:navigate class="ehc-doors-chip">
                <span class="ehc-doors-chip-ic" style="color: {{ $badge($door['key']) }}; background: {{ $badge($door['key']) }}15">
                    <x-icon :name="$door['icon']" class="h-3.5 w-3.5" />
                </span>
                <span class="flex min-w-0 flex-col gap-0.5">
                    <span class="whitespace-nowrap text-[11.5px] font-bold text-ink">{{ $door['label'] }}</span>
                    <span class="whitespace-nowrap text-[9.5px] text-muted">
                        @if ($door['chip'])
                            <span style="{{ $door['chipStyle'] }}" class="inline-block rounded-full px-1.5 py-0.5 text-[9px] font-bold">{{ $door['chip'] }}</span>
                        @else
                            {{ $door['sub'] }}
                        @endif
                    </span>
                </span>
            </a>
        @endforeach
    </div>
@endif
