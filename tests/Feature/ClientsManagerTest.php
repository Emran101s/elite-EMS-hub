<?php

namespace Tests\Feature;

use App\Livewire\ClientsManager;
use App\Models\Client;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ClientsManagerTest extends TestCase
{
    use RefreshDatabase;

    private function boot(): User
    {
        $this->seed(DemoDataSeeder::class);

        return User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
    }

    public function test_clients_page_renders_from_settings(): void
    {
        $user = $this->boot();
        $this->actingAs($user)->get(route('settings.index'))->assertOk()->assertSee('Clients');
        $this->actingAs($user)->get(route('clients.index'))->assertOk()->assertSee('Clients');
    }

    public function test_add_client_with_logo(): void
    {
        Storage::fake('public');
        $user = $this->boot();
        $before = Client::count();

        Livewire::actingAs($user)->test(ClientsManager::class)
            ->set('name', 'Aramco Events')
            ->set('organization', 'Energy')
            ->set('email', 'sara@aramco.test')
            ->set('logo', UploadedFile::fake()->image('logo.png'))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($before + 1, Client::count());
        $c = Client::where('name', 'Aramco Events')->firstOrFail();
        $this->assertSame('Energy', $c->organization);
        $this->assertStringStartsWith('storage/clients/', $c->logo_path);
        Storage::disk('public')->assertExists(str_replace('storage/', '', $c->logo_path));
    }

    public function test_name_is_required(): void
    {
        $user = $this->boot();
        Livewire::actingAs($user)->test(ClientsManager::class)
            ->set('name', '')
            ->call('save')
            ->assertHasErrors('name');
    }

    public function test_delete_client_unlinks_events(): void
    {
        $user = $this->boot();
        $client = Client::create(['name' => 'Temp Client']);
        $event = Event::firstOrFail();
        $event->update(['client_id' => $client->id]);

        Livewire::actingAs($user)->test(ClientsManager::class)->call('delete', $client->id);

        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
        $this->assertNull($event->fresh()->client_id); // nullOnDelete
    }
}
