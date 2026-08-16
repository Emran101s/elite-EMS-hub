@props(['event', 'header', 'ai'])

{{--
    Event Cortex — rule-based recommendation, not AI. Every figure here is
    EventHealthService's own: the headline is aiSummary()'s real sentence,
    and the action tiles are the readiness gates that are NOT met yet
    (EventCommandHeader::readiness()['gates']), each linked to the tab that
    would close it. No projected "+X%" is invented — the only percentage
    shown is the real current readiness figure.
--}}

@php
    $readiness = $header['readiness'];
    $gateTab = [
        'Agenda confirmed' => 'agenda',
        'Speakers confirmed' => 'speakers',
        'Suppliers contracted' => 'suppliers',
        'Venue assigned' => 'venue',
        'Transport ready' => 'transportation',
        'Approvals cleared' => 'approvals',
        'No open severe risk' => 'risks',
    ];

    $unmet = collect($readiness['gates'])->where('met', false)->take(3)->map(fn ($g) => [
        'title' => $g['label'],
        'note' => $g['note'],
        'href' => route('events.hub', [$event, 'tab' => $gateTab[$g['label']] ?? 'overview']),
    ]);
@endphp

<div class="hubx-cortex">
    <span class="hubx-cortex-badge">
        <x-icon name="sparkles" class="h-3 w-3" />
        Event Cortex · Rule-based
    </span>

    <p class="hubx-cortex-headline">{{ $ai['headline'] }}</p>

    @if ($readiness['total'] > 0)
        <p class="mt-1 text-[12.5px] text-eo-muted">
            {{ $readiness['met'] }} of {{ $readiness['total'] }} readiness gates met — {{ $readiness['pct'] }}% ready.
            @if ($unmet->isNotEmpty())
                Closing {{ $unmet->count() === 1 ? 'this' : 'these' }} would move it further.
            @endif
        </p>
    @endif

    @if ($unmet->isNotEmpty())
        <div class="hubx-cortex-actions">
            @foreach ($unmet as $action)
                <a href="{{ $action['href'] }}" wire:navigate class="hubx-cortex-action">
                    <p class="hubx-cortex-action-title">{{ $action['title'] }}</p>
                    <p class="hubx-cortex-action-note">{{ $action['note'] }}</p>
                </a>
            @endforeach
        </div>
    @elseif ($ai['recommendation'])
        <p class="mt-3 text-[12.5px] font-semibold text-eo-teal-ink">{{ $ai['recommendation'] }}</p>
    @endif
</div>
