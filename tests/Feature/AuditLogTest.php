<?php

namespace Tests\Feature;

use App\Livewire\Hub\ApprovalsTab;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(): array
    {
        $this->seed(DemoDataSeeder::class);

        return [
            Event::where('name', 'ICFT 2026')->firstOrFail(),
            User::where('email', 'emran.itan@elitebhub.com')->firstOrFail(),
        ];
    }

    public function test_seeding_writes_no_audit_rows(): void
    {
        // Console runs have no author — an audit row without one is noise.
        $this->seed(DemoDataSeeder::class);

        $this->assertSame(0, AuditLog::count());
    }

    public function test_an_approval_decision_is_logged_with_its_author(): void
    {
        [$event, $user] = $this->ctx();
        $requester = User::where('email', '!=', $user->email)->firstOrFail();
        $approval = $event->approvals()->create([
            'title' => 'Stage design sign-off', 'type' => 'design',
            'status' => 'pending', 'requested_by' => $requester->id,
        ]);
        AuditLog::query()->delete();   // only the decision matters here

        Livewire::actingAs($user)->test(ApprovalsTab::class, ['event' => $event])
            ->call('decide', $approval->id, 'approved');

        $log = AuditLog::where('auditable_type', 'EventApproval')->where('action', 'updated')->firstOrFail();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($event->id, $log->event_id);
        $this->assertSame('Stage design sign-off', $log->label);
        $this->assertSame(['pending', 'approved'], $log->changes['status']);
    }

    public function test_event_stage_changes_are_logged_but_quiet_fields_are_not(): void
    {
        [$event, $user] = $this->ctx();
        $this->actingAs($user);

        $event->update(['stage' => 'production']);
        $log = AuditLog::where('auditable_type', 'Event')->latest('id')->firstOrFail();
        $this->assertArrayHasKey('stage', $log->changes);

        AuditLog::query()->delete();
        $event->update(['progress' => 55]);   // not an AUDIT_FIELDS member
        $this->assertSame(0, AuditLog::count(), 'progress ticks are noise, not decisions');
    }

    public function test_selling_a_booth_is_logged(): void
    {
        [$event, $user] = $this->ctx();
        $this->actingAs($user);
        $hall = $event->ensureExhibitionHall();
        $ex = $event->exhibitors()->create(['company' => 'Acme', 'status' => 'confirmed']);
        $booth = $event->booths()->create(['hall_id' => $hall->id, 'number' => 'B01', 'price_cents' => 0, 'x' => 5, 'y' => 5, 'w_m' => 3, 'h_m' => 3]);
        AuditLog::query()->delete();

        $booth->update(['exhibitor_id' => $ex->id]);

        $log = AuditLog::where('auditable_type', 'EventBooth')->firstOrFail();
        $this->assertSame('Booth B01', $log->label);
        $this->assertArrayHasKey('exhibitor_id', $log->changes);
    }

    public function test_recent_activity_shows_on_the_overview(): void
    {
        [$event, $user] = $this->ctx();
        $this->actingAs($user);
        $event->update(['stage' => 'production']);

        $this->actingAs($user)->get(route('events.hub', $event))
            ->assertOk()
            ->assertSee('Recent Activity')
            ->assertSee('Audit trail');
    }
}
