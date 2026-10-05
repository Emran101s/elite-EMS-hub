<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * The morning note: what of yours is overdue, due today, and due tomorrow.
 *
 * One mail a day, sent only when there is something in it — a digest that
 * arrives empty teaches people to ignore the ones that are not.
 */
class TaskDigest extends Notification
{
    /** @param array{overdue:Collection<int,Task>,today:Collection<int,Task>,tomorrow:Collection<int,Task>} $buckets */
    public function __construct(private readonly array $buckets) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $overdue = $this->buckets['overdue']->count();
        $today = $this->buckets['today']->count();
        $tomorrow = $this->buckets['tomorrow']->count();

        $subject = collect([
            $overdue ? "{$overdue} overdue" : null,
            $today ? "{$today} due today" : null,
        ])->filter()->join(', ');

        if ($subject === '' && $tomorrow > 0) {
            $subject = "{$tomorrow} due tomorrow";
        }

        $subject = $subject ?: 'Tasks due tomorrow';

        $mail = (new MailMessage)
            ->subject('Your tasks: '.$subject)
            ->greeting("Good morning, {$notifiable->name}.");

        foreach (['overdue' => 'Overdue', 'today' => 'Due today', 'tomorrow' => 'Due tomorrow'] as $key => $heading) {
            $tasks = $this->buckets[$key];
            if ($tasks->isEmpty()) {
                continue;
            }

            $mail->line("**{$heading}**");
            foreach ($tasks->take(10) as $task) {
                $mail->line('• '.TasksAssigned::describe($task));
            }
            if ($tasks->count() > 10) {
                $mail->line('…and '.($tasks->count() - 10).' more.');
            }
        }

        return $mail->action('Open My work', route('tasks.mine'));
    }
}
