<?php

namespace App\Notifications;

use App\Models\ApprovalStep;
use App\Models\EventApproval;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a manager a decision is waiting on them.
 *
 * Not queued, deliberately. There is no queue worker running against this
 * install, and a queued notification with nobody to run it does not arrive
 * late — it never arrives at all, silently, which is worse than the ~300ms
 * this adds to the request. Add ShouldQueue the day a worker exists.
 */
class ApprovalRequested extends Notification
{
    public function __construct(
        private readonly EventApproval $approval,
        private readonly ApprovalStep $step,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $event = $this->approval->event;
        $requester = $this->approval->requester?->name ?? 'Someone';

        $mail = (new MailMessage)
            ->subject('Approval needed: '.$this->approval->title)
            ->greeting("Hi {$notifiable->name},")
            ->line("{$requester} has asked for your decision on **{$this->approval->title}**.")
            ->line('Event: '.$event->name)
            ->line('Type: '.ucfirst($this->approval->type));

        // Money is the reason most of these get escalated — say the number
        // rather than making somebody open the page to find it.
        if ($this->approval->amount_cents !== null) {
            $mail->line('Amount: '.number_format($this->approval->amount_cents / 100, 2).' '.($event->currency ?? ''));
        }

        if ($this->approval->notes) {
            $mail->line('Note: '.$this->approval->notes);
        }

        // A chained approval should say where in the chain this is, so the
        // approver knows whether they are the last word or one of several.
        $total = $this->approval->steps()->count();
        if ($total > 1) {
            $mail->line("This is step {$this->step->position} of {$total}.");
        }

        return $mail
            ->action('Review the request', route('events.hub', [$event, 'tab' => 'approvals']))
            ->line('You are seeing this because the step is assigned to you, or to your role.');
    }
}
