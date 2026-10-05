<?php

namespace Tests\Feature;

use App\Livewire\Hub\TasksTab;
use App\Livewire\TasksIndex;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskDigest;
use App\Notifications\TasksAssigned;
use App\Services\TaskNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class TaskNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function person(string $role = 'coordinator'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_assigning_from_the_tasks_list_tells_the_new_owner(): void
    {
        [$me, $khalid] = [$this->person(), $this->person()];
        $task = Task::factory()->for(Event::factory())->create(['assignee_id' => null]);

        Livewire::actingAs($me)->test(TasksIndex::class)->call('assign', $task->id, (string) $khalid->id);

        Notification::assertSentTo($khalid, TasksAssigned::class);
        Notification::assertNotSentTo($me, TasksAssigned::class);
    }

    public function test_assigning_from_the_event_board_tells_the_new_owner(): void
    {
        [$me, $dana] = [$this->person(), $this->person()];
        $event = Event::factory()->create();
        $task = Task::factory()->for($event)->create(['assignee_id' => null]);

        Livewire::actingAs($me)->test(TasksTab::class, ['event' => $event])
            ->call('openTask', $task->id)
            ->call('patch', 'assignee_id', (string) $dana->id);

        Notification::assertSentTo($dana, TasksAssigned::class);
    }

    public function test_nobody_hears_about_a_non_change_or_their_own_assignment(): void
    {
        [$me, $dana] = [$this->person(), $this->person()];
        $event = Event::factory()->create();
        $task = Task::factory()->for($event)->create(['assignee_id' => $dana->id]);
        $other = Task::factory()->for($event)->create(['assignee_id' => null]);

        // Picking the owner it already has.
        Livewire::actingAs($me)->test(TasksTab::class, ['event' => $event])
            ->call('openTask', $task->id)
            ->call('patch', 'assignee_id', (string) $dana->id);
        Livewire::actingAs($me)->test(TasksIndex::class)->call('assign', $task->id, (string) $dana->id);

        // Taking a task yourself.
        Livewire::actingAs($me)->test(TasksIndex::class)->call('assign', $other->id, (string) $me->id);

        Notification::assertNothingSent();
    }

    public function test_a_bulk_reassign_is_one_mail_listing_only_what_changed_hands(): void
    {
        [$me, $omar] = [$this->person(), $this->person()];
        $event = Event::factory()->create();
        $a = Task::factory()->for($event)->create(['assignee_id' => null, 'title' => 'Book the band']);
        $b = Task::factory()->for($event)->create(['assignee_id' => $me->id, 'title' => 'Order lanyards']);
        $already = Task::factory()->for($event)->create(['assignee_id' => $omar->id, 'title' => 'Already Omars']);

        Livewire::actingAs($me)->test(TasksIndex::class)
            ->call('selectMany', [$a->id, $b->id, $already->id])
            ->call('bulkAssign', (string) $omar->id);

        Notification::assertSentToTimes($omar, TasksAssigned::class, 1);
        Notification::assertSentTo($omar, TasksAssigned::class, function (TasksAssigned $n) use ($omar) {
            $lines = implode("\n", $n->toMail($omar)->introLines);

            return str_contains($lines, 'Book the band') && str_contains($lines, 'Order lanyards') && ! str_contains($lines, 'Already Omars');
        });
    }

    public function test_the_digest_mails_only_people_with_something_due_and_buckets_it(): void
    {
        $today = Carbon::parse('2026-10-01');
        [$busy, $idle] = [$this->person(), $this->person()];
        $event = Event::factory()->create();

        Task::factory()->for($event)->create(['assignee_id' => $busy->id, 'title' => 'Late', 'status' => 'todo', 'due_on' => '2026-09-28']);
        Task::factory()->for($event)->create(['assignee_id' => $busy->id, 'title' => 'Now', 'status' => 'doing', 'due_on' => '2026-10-01']);
        Task::factory()->for($event)->create(['assignee_id' => $busy->id, 'title' => 'Next', 'status' => 'todo', 'due_on' => '2026-10-02']);
        Task::factory()->for($event)->create(['assignee_id' => $busy->id, 'title' => 'Finished', 'status' => 'done', 'due_on' => '2026-09-28']);
        Task::factory()->for($event)->create(['assignee_id' => $idle->id, 'title' => 'Far off', 'status' => 'todo', 'due_on' => '2026-10-20']);

        $sent = app(TaskNotifier::class)->digest($today);

        $this->assertSame(1, $sent);
        Notification::assertNotSentTo($idle, TaskDigest::class);
        Notification::assertSentTo($busy, TaskDigest::class, function (TaskDigest $n) use ($busy) {
            $mail = $n->toMail($busy);
            $lines = implode("\n", $mail->introLines);

            return $mail->subject === 'Your tasks: 1 overdue, 1 due today'
                && str_contains($lines, 'Late') && str_contains($lines, 'Now') && str_contains($lines, 'Next')
                && ! str_contains($lines, 'Finished');
        });
    }

    public function test_the_digest_subject_mentions_tomorrow_only_tasks_by_count(): void
    {
        $today = Carbon::parse('2026-10-01');
        $user = $this->person();
        $event = Event::factory()->create();

        Task::factory()->for($event)->create(['assignee_id' => $user->id, 'title' => 'Next up', 'status' => 'todo', 'due_on' => '2026-10-02']);
        Task::factory()->for($event)->create(['assignee_id' => $user->id, 'title' => 'Also tomorrow', 'status' => 'doing', 'due_on' => '2026-10-02']);

        app(TaskNotifier::class)->digest($today);

        Notification::assertSentTo($user, TaskDigest::class, function (TaskDigest $n) use ($user) {
            return $n->toMail($user)->subject === 'Your tasks: 2 due tomorrow';
        });
    }

    public function test_the_command_dry_run_sends_nothing(): void
    {
        $user = $this->person();
        Task::factory()->for(Event::factory())->create(['assignee_id' => $user->id, 'status' => 'todo', 'due_on' => now()->subDay()]);

        $this->artisan('tasks:digest', ['--dry-run' => true])
            ->expectsOutputToContain('Would mail 1 person.')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }
}
