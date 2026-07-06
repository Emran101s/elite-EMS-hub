<?php

namespace Tests\Feature;

use App\Livewire\Hub\ApprovalsTab;
use App\Livewire\Hub\BudgetTab;
use App\Livewire\Hub\RisksTab;
use App\Livewire\Hub\TasksTab;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventHubActionsTest extends TestCase
{
    use RefreshDatabase;

    private function setup2(): array
    {
        $this->seed(DemoDataSeeder::class);

        return [
            Event::where('name', 'ICFT 2026')->firstOrFail(),
            User::where('email', 'emran.itan@elitebhub.com')->firstOrFail(),
        ];
    }

    public function test_task_can_be_added_and_completed(): void
    {
        [$event, $user] = $this->setup2();

        Livewire::actingAs($user)->test(TasksTab::class, ['event' => $event])
            ->set('title', 'Print VIP badges')
            ->set('priority', 'high')
            ->set('due_on', '2026-10-01')
            ->call('save')
            ->assertHasNoErrors();

        $task = $event->tasks()->where('title', 'Print VIP badges')->firstOrFail();
        $this->assertSame('high', $task->priority);

        Livewire::actingAs($user)->test(TasksTab::class, ['event' => $event])
            ->call('setStatus', $task->id, 'completed');

        $this->assertSame('completed', $task->fresh()->status);
    }

    public function test_budget_line_can_be_added_and_marked_paid(): void
    {
        [$event, $user] = $this->setup2();

        Livewire::actingAs($user)->test(BudgetTab::class, ['event' => $event])
            ->set('category', 'security')
            ->set('description', 'Night security detail')
            ->set('estimated', '12000')
            ->set('actual', '11500')
            ->call('save')
            ->assertHasNoErrors();

        $item = $event->budgetItems()->where('description', 'Night security detail')->firstOrFail();
        $this->assertSame(1200000, $item->estimated_cents);
        $this->assertSame(1150000, $item->actual_cents);

        Livewire::actingAs($user)->test(BudgetTab::class, ['event' => $event])
            ->call('setPayment', $item->id, 'paid');

        $this->assertSame('paid', $item->fresh()->payment_status);
    }

    public function test_risk_can_be_registered_and_escalated(): void
    {
        [$event, $user] = $this->setup2();

        Livewire::actingAs($user)->test(RisksTab::class, ['event' => $event])
            ->set('title', 'Speaker travel strike')
            ->set('category', 'logistics')
            ->set('probability', 4)
            ->set('impact', 3)
            ->call('save')
            ->assertHasNoErrors();

        $risk = $event->risks()->where('title', 'Speaker travel strike')->firstOrFail();
        $this->assertSame(12, $risk->severity());
        $this->assertSame('open', $risk->status);

        Livewire::actingAs($user)->test(RisksTab::class, ['event' => $event])
            ->call('setStatus', $risk->id, 'escalated');

        $this->assertSame('escalated', $risk->fresh()->status);
    }

    public function test_approval_flow_request_and_decide(): void
    {
        [$event, $user] = $this->setup2();

        Livewire::actingAs($user)->test(ApprovalsTab::class, ['event' => $event])
            ->set('title', 'Extra hostess staffing')
            ->set('type', 'supplier')
            ->call('save')
            ->assertHasNoErrors();

        $approval = $event->approvals()->where('title', 'Extra hostess staffing')->firstOrFail();
        $this->assertSame('pending', $approval->status);
        $this->assertSame($user->id, $approval->requested_by);

        Livewire::actingAs($user)->test(ApprovalsTab::class, ['event' => $event])
            ->call('decide', $approval->id, 'approved');

        $approval->refresh();
        $this->assertSame('approved', $approval->status);
        $this->assertSame($user->id, $approval->decided_by);
        $this->assertNotNull($approval->decided_at);
    }

    public function test_actions_cannot_touch_another_events_records(): void
    {
        [$event, $user] = $this->setup2();
        $otherTask = Event::where('name', 'Tech Expo 2026')->firstOrFail()->tasks()->firstOrFail();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::actingAs($user)->test(TasksTab::class, ['event' => $event])
            ->call('setStatus', $otherTask->id, 'completed');
    }
}
