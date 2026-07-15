<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'type', 'route', 'provider', 'depart_at', 'capacity', 'passengers', 'cost_cents', 'status', 'notes'])]
class EventTransport extends Model
{
    protected $table = 'event_transport';

    public const TYPES = ['shuttle', 'coach', 'sedan', 'van', 'vip', 'flight'];

    public const STATUSES = ['planned', 'booked', 'confirmed', 'completed'];

    protected function casts(): array
    {
        return [
            'depart_at' => 'datetime',
            'capacity' => 'integer',
            'passengers' => 'integer',
            'cost_cents' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
