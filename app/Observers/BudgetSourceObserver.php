<?php

namespace App\Observers;

use App\Models\Event;
use App\Services\BudgetSync;
use Illuminate\Database\Eloquent\Model;

/**
 * The budget follows the modules, without anybody going to look.
 *
 * Costs were mirrored into the budget only when somebody opened the Budget
 * tab. Book a block of rooms on Monday and the event's cost — on the hub, on
 * the dashboard, in the portfolio's margin — stayed at zero until a person
 * happened to open one screen. That is not a stale number, it is a wrong one,
 * and nothing on the page admitted it.
 *
 * So a module record that carries money re-syncs its event's linked lines the
 * moment it is written. The full sync is cheap and idempotent, which is why it
 * is used whole rather than reimplemented per model: one place decides what a
 * linked line looks like, and it cannot drift from the reconciler.
 */
class BudgetSourceObserver
{
    /**
     * The fields that move money. A note typed on a booking is not one of them.
     *
     * @var array<class-string,list<string>>
     */
    private const WATCHED = [
        \App\Models\EventRoomBlock::class => ['rooms_count', 'rate_cents', 'check_in', 'check_out',
            'status', 'hotel', 'room_type', 'occupancy', 'supplier_id'],
        \App\Models\EventAccommodation::class => ['cost_cents', 'hotel', 'guest', 'rooms', 'block_id'],
        \App\Models\EventTransport::class => ['cost_cents', 'route', 'provider'],
        \App\Models\EventSpeaker::class => ['fee_cents', 'name'],
        \App\Models\EventRoom::class => ['cost_cents', 'name', 'requirements', 'days', 'setup_days'],
    ];

    /** Guards against a sync setting off another one. */
    private static bool $running = false;

    public static bool $enabled = true;

    public function saved(Model $model): void
    {
        if (! $model->wasRecentlyCreated && ! $model->wasChanged($this->watched($model))) {
            return;
        }

        $this->resync($model);
    }

    public function deleted(Model $model): void
    {
        $this->resync($model);
    }

    /** @return list<string> */
    private function watched(Model $model): array
    {
        return self::WATCHED[$model::class] ?? [];
    }

    private function resync(Model $model): void
    {
        if (! self::$enabled || self::$running) {
            return;
        }

        $event = $model->event ?? Event::find($model->event_id);

        if (! $event) {
            return;
        }

        self::$running = true;

        try {
            app(BudgetSync::class)->sync($event->fresh());
        } finally {
            self::$running = false;
        }
    }

    /** Run something without re-syncing after every row — imports, seeders. */
    public static function quietly(callable $work): mixed
    {
        $was = self::$enabled;
        self::$enabled = false;

        try {
            return $work();
        } finally {
            self::$enabled = $was;
        }
    }
}
