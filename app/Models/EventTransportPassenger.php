<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['transport_id', 'name', 'airline', 'phone', 'email', 'flight_no', 'arrival_on',
    'arrival_time', 'pickup_point', 'notes', 'position'])]
class EventTransportPassenger extends Model
{
    protected function casts(): array
    {
        return ['position' => 'integer', 'arrival_on' => 'date'];
    }

    /** "RJ 512 · 17 Oct 13:50" — the flight line as a driver reads it. */
    public function flightLine(): string
    {
        return collect([
            $this->airline,
            $this->flight_no,
            $this->arrival_on?->format('j M'),
            $this->arrival_time,
        ])->filter()->implode(' · ');
    }

    public function transport(): BelongsTo
    {
        return $this->belongsTo(EventTransport::class, 'transport_id');
    }
}
