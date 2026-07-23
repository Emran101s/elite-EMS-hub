<?php

namespace App\Support;

use App\Models\EventTransport;
use App\Models\EventTransportPassenger;

/**
 * WhatsApp deep links — no API, no integration, no cost. A wa.me URL with a
 * pre-filled message opens the user's own WhatsApp with the text ready to send.
 *
 * This is how transport actually gets coordinated in this industry, and it works
 * on day one with nothing to onboard.
 */
class WhatsApp
{
    /** Digits only, no leading + or zeros — what wa.me expects. */
    public static function normalise(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        return $digits === '' ? null : ltrim($digits, '0');
    }

    public static function link(?string $phone, string $message): ?string
    {
        $number = self::normalise($phone);

        return $number ? 'https://wa.me/'.$number.'?text='.rawurlencode($message) : null;
    }

    /** What the driver needs: when, where, who, and how many. */
    public static function toDriver(EventTransport $movement): ?string
    {
        $when = $movement->effectiveDeparture();
        $pax = $movement->paxCount();

        $lines = array_filter([
            $movement->event?->name,
            'Car '.$movement->refLabel().' — '.$movement->legLabel(),
            $when ? 'Pickup: '.$when->format('D j M · H:i') : 'Pickup time to be confirmed',
            'From: '.($movement->pickup_from ?: '—'),
            'To: '.($movement->drop_to ?: '—'),
            $pax ? 'Passengers: '.$pax : null,
            $movement->flight_no ? 'Flight: '.$movement->flight_no : null,
            $movement->notes ?: null,
        ]);

        return self::link($movement->contactNumber(), implode("\n", $lines));
    }

    /** What the guest needs: which car to look for, and who is driving it. */
    public static function toGuest(EventTransportPassenger $guest): ?string
    {
        $movement = $guest->transport;

        $lines = array_filter([
            'Hello '.$guest->name.',',
            'Your transfer is confirmed.',
            $movement?->effectiveDeparture()
                ? 'Pickup: '.$movement->effectiveDeparture()->format('D j M · H:i')
                : ($guest->pickup_time ? 'Pickup: '.substr($guest->pickup_time, 0, 5) : null),
            $guest->pickup_point ? 'From: '.$guest->pickup_point : null,
            $movement?->vehicle?->label() ? 'Vehicle: '.$movement->vehicle->label() : null,
            $movement?->driver ? 'Driver: '.$movement->driver->name
                .($movement->driver->phone ? ' · '.$movement->driver->phone : '') : null,
        ]);

        return self::link($guest->whatsappNumber(), implode("\n", $lines));
    }
}
