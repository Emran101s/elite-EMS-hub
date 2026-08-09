<?php

namespace App\Notifications;

use App\Models\EventAttendee;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * What an attendee gets back after registering.
 *
 * Carries the two things they actually need on the day: their reference, and
 * the check-in link the door will scan. The link uses the event's check-in
 * token rather than the registration token, so rotating the public sign-up
 * URL never invalidates a confirmation somebody already has in their inbox.
 *
 * Not queued — see ApprovalRequested for why.
 */
class RegistrationConfirmed extends Notification
{
    public function __construct(private readonly EventAttendee $attendee) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(EventAttendee $notifiable): MailMessage
    {
        $event = $this->attendee->event;
        $reference = $this->attendee->reference();

        $mail = (new MailMessage)
            ->subject('You are registered for '.$event->name)
            ->greeting('Hi '.($this->attendee->name ?: 'there').',')
            ->line("You're registered for **{$event->name}**.")
            ->line('Your reference is **'.$reference.'** — keep it, the door will ask for it.');

        if ($event->starts_at) {
            $when = $event->starts_at->format('l j F Y');
            if ($event->ends_at && ! $event->ends_at->isSameDay($event->starts_at)) {
                $when .= ' – '.$event->ends_at->format('l j F Y');
            }
            $mail->line('When: '.$when);
        }

        if ($event->location) {
            $mail->line('Where: '.$event->location);
        }

        // The sessions they chose are the part people forget, so restate them
        // rather than making them dig the form out again.
        $sessions = $this->attendee->sessions()->with('day')->get();
        if ($sessions->isNotEmpty()) {
            $mail->line('Your sessions:');
            foreach ($sessions as $session) {
                $mail->line('· '.$session->title.' — '.substr((string) $session->starts_at, 0, 5));
            }
        }

        return $mail
            ->action('Open your check-in pass', route('checkin.scan', [$event->checkinToken(), $reference]))
            ->line('Bring this link with you — showing it at the desk is your check-in.');
    }
}
