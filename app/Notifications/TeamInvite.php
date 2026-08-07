<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A new team member's first link into the hub.
 *
 * TeamRoster creates the account with a password nobody will ever type — this
 * is the only way that account becomes usable. It rides the same broker/token
 * table as a forgot-password link (`password.reset`), so one reset screen
 * serves both journeys; only the invitation copy differs.
 */
class TeamInvite extends Notification
{
    public function __construct(private readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('You’ve been added to Elite Business Hub')
            ->greeting("Hi {$notifiable->name},")
            ->line('An account has been created for you on Elite Business Hub. Set a password to sign in.')
            ->action('Set your password', $url)
            ->line('This link expires in '.round(config('auth.passwords.users.expire') / 60).' hours.');
    }
}
