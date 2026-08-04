<?php

namespace App\Events;

use App\Models\Event;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever an event's stage actually changes — the hook point for
 * anything that should react to lifecycle movement (the approval engine,
 * future notifications) without that logic living inside the model or
 * whichever screen happened to trigger the change.
 */
class EventStageChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Event $event,
        public string $from,
        public string $to,
    ) {}
}
