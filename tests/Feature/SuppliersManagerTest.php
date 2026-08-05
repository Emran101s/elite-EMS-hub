<?php

namespace Tests\Feature;

use App\Livewire\SuppliersManager;
use App\Models\Event;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SuppliersManagerTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(): User
    {
        $this->seed(DemoDataSeeder::class);

        return User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
    }

    public function test_a_supplier_can_be_added(): void
    {
        $user = $this->ctx();

        Livewire::actingAs($user)->test(SuppliersManager::class)
            ->call('newItem')
            ->set('name', 'Petra Catering Co.')
            ->set('category', 'catering')
            ->set('rating', '4.5')
            ->set('city', 'Amman')
            ->call('save');

        $supplier = Supplier::where('name', 'Petra Catering Co.')->firstOrFail();
        $this->assertSame('catering', $supplier->category);
        $this->assertSame(4.5, $supplier->rating);
        $this->assertSame('Amman', $supplier->city);
    }

    public function test_a_supplier_can_be_edited_in_place(): void
    {
        $user = $this->ctx();
        $supplier = Supplier::create(['name' => 'Old Name', 'category' => 'catering', 'rating' => 3]);
        $before = Supplier::count();

        Livewire::actingAs($user)->test(SuppliersManager::class)
            ->call('edit', $supplier->id)
            ->assertSet('name', 'Old Name')
            ->set('name', 'New Name')
            ->set('rating', '4.8')
            ->call('save');

        $supplier->refresh();
        $this->assertSame('New Name', $supplier->name);
        $this->assertSame(4.8, $supplier->rating);
        $this->assertSame($before, Supplier::count(), 'editing must update in place, not create a duplicate');
    }

    public function test_a_supplier_can_be_deleted_and_referencing_records_survive(): void
    {
        $user = $this->ctx();
        $event = Event::query()->firstOrFail();
        $supplier = Supplier::create(['name' => 'Doomed Vendor', 'category' => 'logistics', 'rating' => 2]);
        $room = $event->rooms()->create(['name' => 'Test room', 'type' => 'breakout']);
        $budgetItem = $event->budgetItems()->create([
            'category' => 'Other', 'description' => 'Something', 'quantity' => 1, 'supplier_id' => $supplier->id,
        ]);

        Livewire::actingAs($user)->test(SuppliersManager::class)
            ->call('delete', $supplier->id);

        $this->assertNull(Supplier::find($supplier->id));
        $this->assertNull($budgetItem->fresh()->supplier_id, 'referencing rows keep working, just unlinked');
        $room->delete();
    }

    public function test_multiple_suppliers_can_be_bulk_deleted(): void
    {
        $user = $this->ctx();
        $a = Supplier::create(['name' => 'A', 'category' => 'catering', 'rating' => 1]);
        $b = Supplier::create(['name' => 'B', 'category' => 'decor', 'rating' => 1]);
        $keep = Supplier::create(['name' => 'Keep me', 'category' => 'decor', 'rating' => 1]);

        Livewire::actingAs($user)->test(SuppliersManager::class)
            ->call('toggleSelect', $a->id)
            ->call('toggleSelect', $b->id)
            ->call('deleteSelected');

        $this->assertNull(Supplier::find($a->id));
        $this->assertNull(Supplier::find($b->id));
        $this->assertNotNull(Supplier::find($keep->id));
    }

    public function test_the_suppliers_page_loads(): void
    {
        $user = $this->ctx();

        $this->actingAs($user)->get(route('suppliers.index'))->assertOk()->assertSee('Suppliers');
    }
}
