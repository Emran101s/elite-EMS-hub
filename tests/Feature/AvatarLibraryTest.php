<?php

namespace Tests\Feature;

use App\Livewire\AvatarLibrary;
use App\Models\Event;
use App\Models\EventAvatar;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AvatarLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function boot(): User
    {
        $this->seed(DemoDataSeeder::class);

        return User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
    }

    public function test_settings_hub_and_avatar_library_render(): void
    {
        $user = $this->boot();
        $this->actingAs($user)->get(route('settings.index'))->assertOk()->assertSee('Event Avatars');
        $this->actingAs($user)->get(route('settings.avatars'))->assertOk()->assertSee('Event Avatars');
    }

    public function test_upload_creates_an_avatar(): void
    {
        Storage::fake('public');
        $user = $this->boot();
        $before = EventAvatar::count();

        Livewire::actingAs($user)->test(AvatarLibrary::class)
            ->set('name', 'Riyadh Summit Stage')
            ->set('category', 'conference')
            ->set('image', UploadedFile::fake()->image('cover.png', 400, 300))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($before + 1, EventAvatar::count());
        $a = EventAvatar::where('name', 'Riyadh Summit Stage')->firstOrFail();
        $this->assertSame('riyadh-summit-stage', $a->slug);
        $this->assertStringStartsWith('storage/avatars/', $a->image_path);
        Storage::disk('public')->assertExists(str_replace('storage/', '', $a->image_path));
    }

    public function test_upload_requires_an_image(): void
    {
        $this->actingAs($user = $this->boot());
        Livewire::actingAs($user)->test(AvatarLibrary::class)
            ->set('name', 'No image here')
            ->call('save')
            ->assertHasErrors('image');
    }

    public function test_requires_a_name(): void
    {
        Storage::fake('public');
        $user = $this->boot();
        Livewire::actingAs($user)->test(AvatarLibrary::class)
            ->set('name', '')
            ->set('image', UploadedFile::fake()->image('c.png'))
            ->call('save')
            ->assertHasErrors('name');
    }

    public function test_toggle_active_and_safe_delete_detaches_events(): void
    {
        $user = $this->boot();
        $a = EventAvatar::create([
            'name' => 'Temp Avatar', 'slug' => 'temp-avatar', 'category' => 'conference',
            'is_active' => true, 'colors' => [], 'recommended_types' => [], 'sort_order' => 99,
        ]);
        $event = Event::firstOrFail();
        $event->update(['avatar_id' => $a->id]);

        $c = Livewire::actingAs($user)->test(AvatarLibrary::class);

        $c->call('toggleActive', $a->id);
        $this->assertFalse((bool) $a->fresh()->is_active);

        $c->call('delete', $a->id);
        $this->assertDatabaseMissing('event_avatars', ['id' => $a->id]);
        $this->assertNull($event->fresh()->avatar_id); // nullOnDelete — event survives, avatar detaches
    }
}
