<?php

namespace Tests\Feature;

use App\Livewire\Hub\SettingsTab;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class EventTeamTest extends TestCase
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

    public function test_assign_and_remove_event_team_members(): void
    {
        [$event, $user] = $this->ctx();
        DB::table('event_team_members')->where('event_id', $event->id)->delete();
        $event->update(['project_manager_id' => null]);
        $ops = User::where('id', '!=', $user->id)->firstOrFail();

        $c = Livewire::actingAs($user)->test(SettingsTab::class, ['event' => $event]);

        // assign operations lead twice — the second is a no-op (unique keeps it single)
        $c->set('teamUserId', $ops->id)->set('teamRole', 'operations_lead')->call('addTeamMember');
        $c->set('teamUserId', $ops->id)->set('teamRole', 'operations_lead')->call('addTeamMember');
        $this->assertSame(1, DB::table('event_team_members')->where('event_id', $event->id)->where('user_id', $ops->id)->where('role', 'operations_lead')->count());
        $this->assertDatabaseHas('event_team_members', ['event_id' => $event->id, 'user_id' => $ops->id, 'role' => 'operations_lead']);

        // same user, different role is allowed
        $c->set('teamUserId', $ops->id)->set('teamRole', 'finance_owner')->call('addTeamMember');
        $this->assertDatabaseHas('event_team_members', ['user_id' => $ops->id, 'role' => 'finance_owner']);

        // assigning a project manager also sets the event's project_manager_id
        $c->set('teamUserId', $user->id)->set('teamRole', 'project_manager')->call('addTeamMember');
        $this->assertSame($user->id, $event->fresh()->project_manager_id);

        // remove a role
        $c->call('removeTeamMember', $ops->id, 'operations_lead');
        $this->assertDatabaseMissing('event_team_members', ['user_id' => $ops->id, 'role' => 'operations_lead']);
        $this->assertDatabaseHas('event_team_members', ['user_id' => $ops->id, 'role' => 'finance_owner']); // other role stays
    }
}
