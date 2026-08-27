<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4.0: the shared eo-* supplier/venue picker, verified in isolation.
 *
 * Deliberately does not touch Budget, Transport, Accommodation or Catering —
 * those modules keep their own hand-written selects until each gets its own
 * approved conversion pass. This only proves the component itself renders
 * what the legacy selects already render, so wiring it in later is a
 * like-for-like swap.
 */
class EoEntitySelectTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_select_lists_suppliers_with_ids(): void
    {
        $a = Supplier::factory()->create(['name' => 'Prime AV']);
        $b = Supplier::factory()->create(['name' => 'Amman Staging Co']);

        $html = (string) $this->blade(
            '<x-eo.supplier-select wire:model="supplier_id" :suppliers="$suppliers" />',
            ['suppliers' => Supplier::whereIn('id', [$a->id, $b->id])->orderBy('name')->get(['id', 'name'])],
        );

        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('wire:model="supplier_id"', $html);
        $this->assertStringContainsString('value="'.$a->id.'"', $html);
        $this->assertStringContainsString('Prime AV', $html);
        $this->assertStringContainsString('Amman Staging Co', $html);
        // Default empty option, matching Transport/Catering's existing selects.
        $this->assertStringContainsString('— none —', $html);
    }

    public function test_supplier_select_can_drop_the_empty_option(): void
    {
        $supplier = Supplier::factory()->create();

        $html = (string) $this->blade(
            '<x-eo.supplier-select :suppliers="$suppliers" :empty-label="false" />',
            ['suppliers' => Supplier::whereKey($supplier->id)->get(['id', 'name'])],
        );

        $this->assertStringNotContainsString('— none —', $html);
    }

    public function test_venue_select_shows_the_city_as_a_subtitle(): void
    {
        $venue = Venue::factory()->create(['name' => 'Royal Convention Centre', 'city' => 'Amman']);

        $html = (string) $this->blade(
            '<x-eo.venue-select wire:model.live="venue_id" :venues="$venues" />',
            ['venues' => Venue::whereKey($venue->id)->get(['id', 'name', 'city'])],
        );

        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('wire:model.live="venue_id"', $html);
        $this->assertStringContainsString('Royal Convention Centre', $html);
        $this->assertStringContainsString('Amman', $html);
    }

    public function test_venue_without_a_city_shows_no_dangling_separator(): void
    {
        $venue = Venue::factory()->create(['name' => 'TBD Venue', 'city' => null]);

        $html = (string) $this->blade(
            '<x-eo.venue-select :venues="$venues" />',
            ['venues' => Venue::whereKey($venue->id)->get(['id', 'name', 'city'])],
        );

        $this->assertStringContainsString('TBD Venue', $html);
        $this->assertStringNotContainsString('TBD Venue ·', $html);
    }

    public function test_custom_label_and_empty_text_are_honoured(): void
    {
        $html = (string) $this->blade(
            '<x-eo.supplier-select label="Caterer" empty-label="— pick one —" :suppliers="$suppliers" />',
            ['suppliers' => collect()],
        );

        $this->assertStringContainsString('Caterer', $html);
        $this->assertStringContainsString('— pick one —', $html);
    }
}
