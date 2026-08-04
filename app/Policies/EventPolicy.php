<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

/**
 * Instance-aware authorization for Event, alongside the flat named gates
 * (App\Providers\AppServiceProvider) that still cover every other model.
 * Only the abilities Event actually exposes today — no speculative view/
 * restore/forceDelete methods for actions nothing calls.
 */
class EventPolicy
{
    /** Creating a new event is routine and additive. */
    public function create(User $user): bool
    {
        return $user->isAtLeast('coordinator');
    }

    /** Editing an existing event's own fields is routine too. */
    public function update(User $user, Event $event): bool
    {
        return $user->isAtLeast('coordinator');
    }

    /**
     * These three are pure role checks, called before the target event is
     * even fetched (same order the flat gate had) — so $event stays nullable
     * rather than forcing a fetch-then-authorize reorder for no real gain.
     */
    public function archive(User $user, ?Event $event = null): bool
    {
        return $user->isAtLeast('manager');
    }

    public function duplicate(User $user, ?Event $event = null): bool
    {
        return $user->isAtLeast('manager');
    }

    /** Permanent, unrecoverable — see DeleteEventTest. */
    public function delete(User $user, ?Event $event = null): bool
    {
        return $user->isAtLeast('manager');
    }
}
