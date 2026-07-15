<?php

namespace Tests\Feature;

use App\Livewire\TeamRoster;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TeamRosterTest extends TestCase
{
    use RefreshDatabase;

    private function boot(): User
    {
        $this->seed(DemoDataSeeder::class);

        return User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
    }

    public function test_team_page_renders(): void
    {
        $user = $this->boot();
        $this->actingAs($user)->get(route('team.index'))->assertOk()->assertSee('Team & Roles');
    }

    public function test_add_member_creates_a_user_with_role(): void
    {
        $user = $this->boot();

        Livewire::actingAs($user)->test(TeamRoster::class)
            ->set('name', 'New Coordinator')
            ->set('email', 'new.coord@elitebhub.com')
            ->set('title', 'Logistics Lead')
            ->set('role', 'manager')
            ->call('save')
            ->assertHasNoErrors();

        $created = User::where('email', 'new.coord@elitebhub.com')->firstOrFail();
        $this->assertSame('manager', $created->role);
        $this->assertSame('Logistics Lead', $created->title);
        $this->assertNotEmpty($created->password); // random password seeded
    }

    public function test_photo_upload_sets_avatar_path(): void
    {
        Storage::fake('public');
        $user = $this->boot();

        Livewire::actingAs($user)->test(TeamRoster::class)
            ->set('name', 'Photo Person')
            ->set('email', 'photo@elitebhub.com')
            ->set('role', 'coordinator')
            ->set('photo', UploadedFile::fake()->image('me.jpg'))
            ->call('save')
            ->assertHasNoErrors();

        $u = User::where('email', 'photo@elitebhub.com')->firstOrFail();
        $this->assertStringStartsWith('storage/avatars/', $u->avatar_path);
        Storage::disk('public')->assertExists(str_replace('storage/', '', $u->avatar_path));
    }

    public function test_email_must_be_unique(): void
    {
        $user = $this->boot();

        Livewire::actingAs($user)->test(TeamRoster::class)
            ->set('name', 'Dupe')
            ->set('email', $user->email) // already taken
            ->set('role', 'viewer')
            ->call('save')
            ->assertHasErrors('email');
    }

    public function test_cannot_delete_self_but_can_delete_others(): void
    {
        $user = $this->boot();
        $other = User::where('email', '!=', $user->email)->firstOrFail();

        $c = Livewire::actingAs($user)->test(TeamRoster::class);

        $c->call('delete', $user->id);
        $this->assertDatabaseHas('users', ['id' => $user->id]); // self preserved

        $c->call('delete', $other->id);
        $this->assertDatabaseMissing('users', ['id' => $other->id]);
    }
}
