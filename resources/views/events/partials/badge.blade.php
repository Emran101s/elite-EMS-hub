@props(['event', 'attendee', 'template', 'qr' => null])

@php
    use App\Support\Badge;
    use App\Support\Taxonomy;

    [$w, $h] = Badge::dimensions($event);
    $theme = $event->theme();
    $accent = $template['accent'] ?: ($theme['accent'] ?? '#D4AF37');
    $ink = $theme['primary'] ?? '#0B1F3A';

    // The name is the only thing anyone reads across a room, so it gets the
    // room: it steps down a size at a time rather than wrapping to three lines.
    $len = mb_strlen($attendee->name);
    $nameSize = match (true) {
        $len > 34 => '5.2mm',
        $len > 26 => '6.4mm',
        $len > 18 => '7.6mm',
        default => '9mm',
    };
@endphp

{{--
    One badge. The same partial renders the live preview and every card on the
    print sheet, so what is designed on screen is what comes out of the printer
    — the rule the brief and contract documents already follow.

    Sized in millimetres throughout, because this ends up on card stock and a
    pixel means nothing to a printer.
--}}
<div class="badge" style="width: {{ $w }}mm; height: {{ $h }}mm; --accent: {{ $accent }}; --ink: {{ $ink }};">
    <div class="badge-bar"></div>

    <div class="badge-body">
        <div class="badge-head">
            @if ($template['show_logo'] && $event->logo_path)
                <img src="{{ $logoSrc ?? asset($event->logo_path) }}" alt="" class="badge-logo">
            @else
                <span class="badge-event">{{ $event->name }}</span>
            @endif

            @if ($template['show_ticket'] && $attendee->ticket_type)
                <span class="badge-ticket">{{ $attendee->ticket_type }}</span>
            @endif
        </div>

        <div class="badge-name-wrap">
            <p class="badge-name" style="font-size: {{ $nameSize }}">{{ $attendee->name }}</p>

            @if ($template['show_job_title'] && $attendee->job_title)
                <p class="badge-role">{{ $attendee->job_title }}</p>
            @endif
            @if ($template['show_organisation'] && $attendee->organization)
                <p class="badge-org">{{ $attendee->organization }}</p>
            @endif
        </div>

        <div class="badge-foot">
            <div class="badge-foot-text">
                @if ($template['show_reference'])
                    <span class="badge-ref">{{ $attendee->reference() }}</span>
                @endif
                @if ($template['footer'])
                    <span class="badge-footer-note">{{ $template['footer'] }}</span>
                @endif
            </div>

            @if ($template['show_qr'] && $qr)
                <img src="{{ $qr }}" alt="" class="badge-qr">
            @endif
        </div>
    </div>
</div>
