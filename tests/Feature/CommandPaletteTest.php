<?php

namespace Tests\Feature;

use App\Livewire\CommandPalette;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommandPaletteTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $this->seed(DemoDataSeeder::class);

        return User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
    }

    public function test_palette_is_mounted_in_the_app_shell(): void
    {
        $this->actingAs($this->actor())->get(route('events.index'))
            ->assertOk()
            ->assertSeeLivewire(CommandPalette::class);
    }

    public function test_it_finds_records_across_entity_types(): void
    {
        $user = $this->actor();

        Livewire::actingAs($user)->test(CommandPalette::class)
            ->set('q', 'ICFT')
            ->assertSee('ICFT 2026');

        Livewire::actingAs($user)->test(CommandPalette::class)
            ->set('q', 'Royal')
            ->assertSee('Royal Convention Centre')     // venue
            ->assertSee('Royal Catering Services');    // supplier
    }

    public function test_short_queries_return_nothing(): void
    {
        // One character would match half the database — stay quiet until two.
        Livewire::actingAs($this->actor())->test(CommandPalette::class)
            ->set('q', 'I')
            ->assertDontSee('ICFT 2026');
    }
}
