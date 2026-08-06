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
    /**
     * May this person open the event's hub, or pull one of its ~35 export
     * routes (contract, budget, invoice, attendee PDFs)?
     *
     * This ability did not exist until now. Before it, "authenticated" was
     * the entire check on every one of those routes — any signed-in viewer
     * could fetch any tenant's event's contract by changing a number in the
     * URL. Managers and above keep the tenant-wide visibility every other
     * gate in this app already gives them (manage-budget, manage-contract,
     * manage-team are all manager+ with no per-resource check). Below
     * manager, access narrows to events this person is actually on the team
     * for — event_team_members already existed and already had real,
     * UI-editable data in it; nothing was reading it.
     *
     * Deliberately NOT applied to EventsIndex's listing query. Browsing the
     * roster of what events exist is the transparency the tool is built
     * around — a coordinator not on Event #40's team still sees it in the
     * list, the same as before. What changes is that opening it, or pulling
     * its documents, now requires an actual reason to be there.
     */
    public function view(User $user, Event $event): bool
    {
        if (! $user->isAtLeast('coordinator')) {
            return false;
        }

        return $user->isAtLeast('manager') || $event->teamMembers()->whereKey($user->id)->exists();
    }

    /** Creating a new event is routine and additive. */
    public function create(User $user): bool
    {
        return $user->isAtLeast('coordinator');
    }

    /** Same rule as view() — editing requires the same reason to be here as reading does. */
    public function update(User $user, Event $event): bool
    {
        return $this->view($user, $event);
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
