<?php

namespace App\Providers;

use App\Models\EventAccommodation;
use App\Models\EventCateringItem;
use App\Models\EventRoom;
use App\Models\EventRoomBlock;
use App\Models\EventSpeaker;
use App\Models\EventTransport;
use App\Models\User;
use App\Observers\BudgetSourceObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->defineGates();
        $this->watchBudgetSources();
        $this->guardPilotMail();
    }

    /**
     * Phase 2's pilot-week safeguard: every outbound message, whatever its
     * real recipient, is redirected to one internal inbox — so a mistake in
     * an approval/registration/invite flow during the first days of real use
     * cannot reach an actual attendee or client contact.
     *
     * Dormant by default. Both conditions must be true to activate:
     * production environment AND MAIL_PILOT_REDIRECT set in .env. Neither is
     * true today, so this has no effect until the pilot host is deliberately
     * configured for it — see docs/38 §3.2 step 5.
     */
    private function guardPilotMail(): void
    {
        if (app()->environment('production') && config('mail.pilot_redirect')) {
            Mail::alwaysTo(config('mail.pilot_redirect'));
        }
    }

    /**
     * Modules that hold money keep the budget current as they are worked on,
     * rather than only when somebody opens the Budget tab. See
     * App\Observers\BudgetSourceObserver.
     */
    private function watchBudgetSources(): void
    {
        foreach ([EventRoomBlock::class, EventAccommodation::class, EventTransport::class,
            EventSpeaker::class, EventRoom::class, EventCateringItem::class] as $model) {
            $model::observe(BudgetSourceObserver::class);
        }
    }

    /**
     * Workspace abilities. Roles rank viewer < coordinator < manager < admin
     * < super_admin; each gate names the least-senior role allowed.
     *
     *  write            — any change at all; viewers are read-only.
     *  decide-approvals — sign off approvals (never one's own request).
     *  manage-budget    — approve or lock a budget baseline.
     *  manage-contract  — edit, reset or advance the client contract.
     *  manage-events    — archive, delete or duplicate whole events.
     *  manage-team      — invite, edit or remove workspace members.
     */
    private function defineGates(): void
    {
        Gate::define('write', fn (User $u) => $u->isAtLeast('coordinator'));
        Gate::define('decide-approvals', fn (User $u) => $u->isAtLeast('manager'));
        Gate::define('manage-budget', fn (User $u) => $u->isAtLeast('manager'));
        Gate::define('manage-contract', fn (User $u) => $u->isAtLeast('manager'));
        Gate::define('manage-events', fn (User $u) => $u->isAtLeast('manager'));
        Gate::define('manage-team', fn (User $u) => $u->isAtLeast('admin'));
    }
}
