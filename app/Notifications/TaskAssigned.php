<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * A task landed on someone who was not in the room when it did.
 *
 * Database-only: an assignment is not urgent enough to interrupt an inbox,
 * but it must survive being missed on the day it happened — the bell, not
 * email, is where "what's mine" lives. See docs/21 §C15: this was the
 * platform's only assignment path with nothing behind it.
 */
class TaskAssigned extends Notification
{
    public function __construct(
        private readonly Task $task,
        private readonly User $assignedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(User $notifiable): array
    {
        return [
            'title' => 'Assigned to you: '.$this->task->title,
            'body' => $this->assignedBy->name.' assigned you a task'.
                ($this->task->event ? ' on '.$this->task->event->name : '').'.',
            'url' => $this->task->event
                ? route('events.hub', [$this->task->event, 'tab' => 'tasks'])
                : route('tasks.index'),
        ];
    }
}
