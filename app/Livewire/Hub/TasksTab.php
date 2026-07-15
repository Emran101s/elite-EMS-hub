<?php

namespace App\Livewire\Hub;

use App\Livewire\Concerns\BulkSelectable;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use Livewire\Component;

class TasksTab extends Component
{
    use BulkSelectable;

    public Event $event;

    public function deleteSelected(): void
    {
        $this->event->tasks()->whereIn('id', $this->selectedIds())->delete();
        $this->clearSelection();
    }

    public bool $showForm = false;

    public string $title = '';

    public string $priority = 'normal';

    public string $status = 'pending';

    public ?int $assignee_id = null;

    public string $due_on = '';

    public function mount(): void
    {
        $this->showForm = request('action') === 'add';
    }

    public function save()
    {
        $this->validate([
            'title' => ['required', 'string', 'max:160'],
            'priority' => ['required', 'in:'.implode(',', Task::PRIORITIES)],
            'status' => ['required', 'in:'.implode(',', Task::STATUSES)],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'due_on' => ['nullable', 'date'],
        ]);

        $this->event->tasks()->create([
            'title' => $this->title,
            'priority' => $this->priority,
            'status' => $this->status,
            'assignee_id' => $this->assignee_id,
            'due_on' => $this->due_on ?: null,
        ]);

        session()->flash('status', "Task “{$this->title}” added.");

        return $this->redirectRoute('events.hub', [$this->event, 'tab' => 'tasks']);
    }

    public function setStatus(int $taskId, string $status)
    {
        abort_unless(in_array($status, Task::STATUSES, true), 422);

        $this->event->tasks()->whereKey($taskId)->firstOrFail()->update(['status' => $status]);

        return $this->redirectRoute('events.hub', [$this->event, 'tab' => 'tasks']);
    }

    public function render()
    {
        return view('livewire.hub.tasks-tab', [
            'groups' => [
                'pending' => $this->event->tasks()->with('assignee')->where('status', 'pending')->orderBy('due_on')->get(),
                'in_progress' => $this->event->tasks()->with('assignee')->where('status', 'in_progress')->orderBy('due_on')->get(),
                'completed' => $this->event->tasks()->with('assignee')->where('status', 'completed')->orderBy('due_on')->get(),
            ],
            'users' => User::orderBy('name')->get(),
        ]);
    }
}
