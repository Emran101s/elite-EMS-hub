<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['event_id', 'name', 'type', 'capacity', 'layout'])]
class EventRoom extends Model
{
    public const TYPES = ['main_hall', 'breakout', 'exhibition', 'registration', 'vip', 'catering'];

    /** Seating element presets: type => [label, seats, w, h]. */
    public const LAYOUT_PRESETS = [
        'round' => ['Round table', 8, 96, 96],
        'banquet' => ['Banquet (10)', 10, 112, 112],
        'crescent' => ['Half-moon', 6, 120, 66],
        'boardroom' => ['Boardroom', 12, 210, 78],
        'ushape' => ['U-shape', 18, 190, 150],
        'classroom' => ['Classroom', 24, 210, 132],
        'theater' => ['Theater', 30, 200, 116],
        'table' => ['Table', 0, 116, 58],
        'chair' => ['Chair', 1, 28, 28],
    ];

    protected function casts(): array
    {
        return ['layout' => 'array'];
    }

    /** Total seats across all placed elements. */
    public function seatCount(): int
    {
        return collect($this->layout ?? [])->sum(fn ($el) => (int) ($el['seats'] ?? 0));
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(EventAgendaSession::class, 'room_id');
    }
}
