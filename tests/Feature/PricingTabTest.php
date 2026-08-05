<?php

namespace Tests\Feature;

use App\Livewire\Hub\PricingTab;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PricingTabTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(): array
    {
        $this->seed(DemoDataSeeder::class);
        $event = Event::query()->firstOrFail();

        return [$event, User::where('email', 'emran.itan@elitebhub.com')->firstOrFail()];
    }

    public function test_a_price_list_item_can_be_deleted_directly(): void
    {
        [$event, $user] = $this->ctx();
        $item = $event->invoiceItems()->create([
            'name' => 'Main LED Screen', 'unit' => 'item', 'cost_cents' => 500000, 'sell_cents' => 667000,
            'currency' => $event->currency, 'active' => true, 'sort' => 1,
        ]);

        Livewire::actingAs($user)->test(PricingTab::class, ['event' => $event])
            ->call('destroy', $item->id);

        $this->assertNull($event->invoiceItems()->find($item->id));
    }

    public function test_editing_a_price_list_item_loads_its_values_and_updates_in_place(): void
    {
        [$event, $user] = $this->ctx();
        $item = $event->invoiceItems()->create([
            'name' => 'Main LED Screen', 'unit' => 'item', 'cost_cents' => 500000, 'sell_cents' => 667000,
            'currency' => $event->currency, 'active' => true, 'sort' => 1,
        ]);

        Livewire::actingAs($user)->test(PricingTab::class, ['event' => $event])
            ->call('edit', $item->id)
            ->assertSet('name', 'Main LED Screen')
            ->assertSet('sell', '6670')
            ->set('sell', '5500')
            ->call('save');

        $item->refresh();
        $this->assertSame(550000, $item->sell_cents);
        $this->assertSame(1, $event->invoiceItems()->count(), 'editing must update in place, not create a second row');
    }
}
