<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
