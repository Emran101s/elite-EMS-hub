<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Tells someone work was just put on their plate.
 *
 * One mail per hand-over, however many tasks it carries: a bulk reassign of
 * twelve rows is one message listing twelve tasks, not twelve messages.
 *
 * Not queued, for the same reason as ApprovalRequested: there is no queue
 * worker on this install, and a queued mail with nobody to send it never
 * arrives at all.
 */
class TasksAssigned extends Notification
{
    /** @param Collection<int,Task> $tasks */
    public function __construct(
        private readonly Collection $tasks,
        private readonly ?string $assignedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $count = $this->tasks->count();
        $who = $this->assignedBy ?? 'Someone';

        $mail = (new MailMessage)
            ->subject($count === 1
                ? 'Assigned to you: '.$this->tasks->first()->title
                : "{$count} tasks assigned to you")
            ->greeting("Hi {$notifiable->name},")
            ->line($count === 1
                ? "{$who} assigned you a task."
                : "{$who} assigned you {$count} tasks.");

        foreach ($this->tasks->take(15) as $task) {
            $mail->line('• '.self::describe($task));
        }

        if ($count > 15) {
            $mail->line('…and '.($count - 15).' more.');
        }

        return $mail->action('Open My work', route('tasks.mine'));
    }

    /** "Title — Event · due 12 Oct 2026" */
    public static function describe(Task $task): string
    {
        return trim(implode(' — ', array_filter([
            $task->title,
            trim(($task->event?->name ?? '').($task->due_on ? ' · due '.$task->due_on->format('j M Y') : ''), ' ·'),
        ])));
    }
}
