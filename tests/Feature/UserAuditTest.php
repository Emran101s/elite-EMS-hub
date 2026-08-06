<?php

namespace Tests\Feature;

use App\Livewire\TeamRoster;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Who can do what is the most security-relevant fact in the platform, and until
 * User carried the Auditable trait, changing it left no record anywhere — an
 * admin promoting an account to super_admin was invisible after the fact.
 */
class UserAuditTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(DemoDataSeeder::class);

        return User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
    }

    public function test_a_role_change_is_recorded_with_its_author(): void
    {
        $admin = $this->admin();
        $target = User::where('id', '!=', $admin->id)->where('role', '!=', 'admin')->firstOrFail();
        $was = $target->role;
        AuditLog::query()->delete();

        $this->actingAs($admin);
        $target->update(['role' => 'admin']);

        $log = AuditLog::where('auditable_type', 'User')->where('action', 'updated')->firstOrFail();
        $this->assertSame($admin->id, $log->user_id, 'the trail must name who made the change');
        $this->assertSame($target->id, $log->auditable_id);
        $this->assertSame([$was, 'admin'], $log->changes['role']);
    }

    public function test_the_trail_names_the_person_not_their_job_title(): void
    {
        // Auditable's default label reaches for `title` first, which on User is
        // the job title — two coordinators would produce identical rows.
        $admin = $this->admin();
        $target = User::where('id', '!=', $admin->id)->where('role', '!=', 'viewer')->firstOrFail();
        $target->update(['title' => 'Operations Manager']);
        AuditLog::query()->delete();

        $this->actingAs($admin);
        $target->update(['role' => 'viewer']);

        $log = AuditLog::where('auditable_type', 'User')->firstOrFail();
        $this->assertStringContainsString($target->name, $log->label);
        $this->assertStringContainsString($target->email, $log->label);
        $this->assertNotSame('Operations Manager', $log->label);
    }

    public function test_an_email_change_is_recorded(): void
    {
        $admin = $this->admin();
        $target = User::where('id', '!=', $admin->id)->firstOrFail();
        AuditLog::query()->delete();

        $this->actingAs($admin);
        $target->update(['email' => 'new.address@elitebhub.com']);

        $log = AuditLog::where('auditable_type', 'User')->firstOrFail();
        $this->assertSame('new.address@elitebhub.com', $log->changes['email'][1]);
    }

    public function test_a_password_hash_never_reaches_the_trail(): void
    {
        // A manager can read the audit list. Storing hashes there would be a
        // worse problem than the one auditing solves.
        $admin = $this->admin();
        $target = User::where('id', '!=', $admin->id)->firstOrFail();
        AuditLog::query()->delete();

        $this->actingAs($admin);
        $target->update(['password' => 'a-brand-new-secret']);

        $this->assertSame(0, AuditLog::where('auditable_type', 'User')->count());
    }

    public function test_cosmetic_edits_do_not_fill_the_trail(): void
    {
        $admin = $this->admin();
        $target = User::where('id', '!=', $admin->id)->firstOrFail();
        AuditLog::query()->delete();

        $this->actingAs($admin);
        $target->update(['title' => 'Senior Coordinator']);

        $this->assertSame(0, AuditLog::where('auditable_type', 'User')->count());
    }

    public function test_a_role_change_through_the_team_screen_is_recorded(): void
    {
        // The path a human actually takes, not just a direct model write.
        $admin = $this->admin();
        $target = User::where('id', '!=', $admin->id)->where('role', '!=', 'manager')->firstOrFail();
        AuditLog::query()->delete();

        Livewire::actingAs($admin)->test(TeamRoster::class)
            ->call('edit', $target->id)
            ->set('role', 'manager')
            ->call('save');

        $this->assertSame('manager', $target->fresh()->role);
        $log = AuditLog::where('auditable_type', 'User')->where('action', 'updated')->firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
    }
}
