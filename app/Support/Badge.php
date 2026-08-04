<?php

namespace App\Support;

use App\Models\Event;
use App\Models\EventAttendee;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * What goes on a badge, and how big it is.
 *
 * The template lives on the event as JSON rather than in its own table: it is
 * one small settings object per event that only the badge reads, and a table
 * would buy nothing but a join. Every key has a default, so an event nobody
 * has designed a badge for still prints one.
 */
class Badge
{
    /** Stock sizes, in millimetres. Landscape is the lanyard default. */
    public const SIZES = [
        'a6_landscape' => ['A6 landscape', 148, 105],
        'a6_portrait' => ['A6 portrait', 105, 148],
        'a7_landscape' => ['A7 landscape', 105, 74],
        'square' => ['Square 100mm', 100, 100],
        'name_badge' => ['Name badge 90 × 54', 90, 54],
    ];

    /** What a badge shows unless the event says otherwise. */
    public const DEFAULTS = [
        'size' => 'a6_landscape',
        'accent' => null,           // null = the event's own accent colour
        'show_logo' => true,
        'show_organisation' => true,
        'show_job_title' => false,
        'show_ticket' => true,
        'show_qr' => true,
        'show_reference' => true,
        'footer' => '',

        // Extra lines, named by registration-field key. A badge that can print
        // the workshop track is a badge somebody can be pointed to a room by;
        // before this it could print a job title and nothing else the event
        // actually asked.
        'lines' => [],
    ];

    /** The event's template, with every missing key filled in. */
    public static function template(Event $event): array
    {
        return array_merge(self::DEFAULTS, (array) ($event->badge_template ?? []));
    }

    /**
     * The extra lines this badge prints, resolved for one attendee.
     *
     * Named by field key so a renamed question keeps printing — the key is
     * what answers are filed under. A line with nothing in it is dropped
     * rather than printed as an empty row, because a badge is 90mm wide and a
     * blank line costs a real one.
     *
     * @return list<array{label:string,value:string}>
     */
    public static function lines(Event $event, EventAttendee $attendee): array
    {
        $keys = (array) (self::template($event)['lines'] ?? []);

        if ($keys === []) {
            return [];
        }

        $fields = $event->registrationFields()->whereIn('key', $keys)->get()->keyBy('key');
        $out = [];

        foreach ($keys as $key) {
            $field = $fields->get($key);

            $value = $field?->maps_to
                ? trim((string) $attendee->{$field->maps_to})
                : $attendee->answer($key);

            // Sessions are seats, not an answer — read them from the booking.
            if ($field?->isSessions()) {
                $value = $attendee->sessions->pluck('title')->join(' · ');
            }

            if ($value !== '') {
                $out[] = ['label' => $field?->label ?? $key, 'value' => $value];
            }
        }

        return $out;
    }

    /** [width, height] in millimetres. */
    public static function dimensions(Event $event): array
    {
        return self::sizeDimensions(self::template($event)['size']);
    }

    /**
     * [width, height] in millimetres, from a size key directly rather than
     * re-reading the event's saved template — the one the live editor
     * preview needs, since it already has an in-progress, unsaved size
     * choice in its own $template array.
     */
    public static function sizeDimensions(string $size): array
    {
        return array_slice(self::SIZES[$size] ?? self::SIZES['a6_landscape'], 1);
    }

    /**
     * The QR, inline as a data URI.
     *
     * SVG rather than PNG so it stays sharp at any print size and needs no
     * image extension; a data URI rather than a file so the badge sheet is one
     * self-contained document Chrome can render without fetching anything.
     */
    public static function qr(string $payload, int $sizePx = 220): string
    {
        $svg = (new SvgWriter)->write(
            new QrCode(
                data: $payload,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::Medium,
                size: $sizePx,
                margin: 0,
            )
        )->getString();

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * What a badge's QR carries: the check-in URL for that person.
     *
     * A URL rather than a bare code, so any phone camera opens the right page
     * without a special scanner app — which on show day is the difference
     * between a queue and a door that moves.
     */
    public static function checkInUrl(EventAttendee $attendee): string
    {
        return route('checkin.scan', [
            'token' => $attendee->event->registrationToken(),
            'reference' => $attendee->reference(),
        ]);
    }
}
