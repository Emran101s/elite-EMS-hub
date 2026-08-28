<?php

namespace App\Livewire;

use Livewire\Component;

/**
 * "Something needs you" used to mean opening the right screen and noticing.
 * This is the platform's first personal inbox — see docs/21 §C15.
 *
 * The existing "Alerts" bell in app-tools.blade.php stays exactly as it was:
 * a portfolio count (pending approvals + open risks) linking to the
 * dashboard's #live-alerts section. This is a different thing — things that
 * happened TO YOU — and sits beside it rather than replacing it.
 */
class NotificationBell extends Component
{
    public bool $open = false;

    /** Snapshot of what was unread the moment the panel opened, so a row
     *  does not lose its "new" styling out from under you while you are
     *  still looking at it — only opening it again re-takes the snapshot. */
    public array $unreadIdsAtOpen = [];

    public function toggle(): void
    {
        $this->open = ! $this->open;

        if ($this->open) {
            $unread = auth()->user()->unreadNotifications;
            $this->unreadIdsAtOpen = $unread->pluck('id')->all();
            $unread->markAsRead();
        }
    }

    public function render()
    {
        return view('livewire.notification-bell', [
            'notifications' => auth()->user()->notifications()->latest()->limit(15)->get(),
            'unreadCount' => auth()->user()->unreadNotifications()->count(),
        ]);
    }
}
