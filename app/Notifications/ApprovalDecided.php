<?php

namespace App\Notifications;

use App\Models\EventApproval;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Closes the loop with whoever raised the request.
 *
 * Sent only when the approval itself resolves, not on every step: a five-step
 * chain that emailed the requester five times would be noise, and the only
 * moment they can act on is the last one.
 *
 * Not queued — see ApprovalRequested for why.
 */
class ApprovalDecided extends Notification
{
    public function __construct(
        private readonly EventApproval $approval,
        private readonly string $decision,
        private readonly ?string $decidedBy = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $event = $this->approval->event;
        $verdict = str($this->decision)->replace('_', ' ')->title()->toString();

        $mail = (new MailMessage)
            ->subject("{$verdict}: {$this->approval->title}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your request **{$this->approval->title}** was {$this->decision}"
                .($this->decidedBy ? " by {$this->decidedBy}" : '').'.')
            ->line('Event: '.$event->name);

        // Rejection and needs-revision are the two that require the requester
        // to do something next, so they get the stronger visual treatment.
        if (in_array($this->decision, ['rejected', 'needs_revision'], true)) {
            $mail->error();
        }

        if ($this->decision === 'needs_revision') {
            $mail->line('Revise the request and raise it again when it is ready.');
        }

        return $mail->action('Open the approvals board', route('events.hub', [$event, 'tab' => 'approvals']));
    }
}
