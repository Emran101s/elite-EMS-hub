<?php

namespace Tests\Feature;

use App\Livewire\Hub\AccommodationTab;
use App\Livewire\Hub\ExhibitionTab;
use App\Livewire\Hub\SpeakersTab;
use App\Livewire\Hub\TransportationTab;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventModulesTest extends TestCase
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

    public function test_speaker_can_be_added_and_confirmed(): void
    {
        [$event, $user] = $this->ctx();

        Livewire::actingAs($user)->test(SpeakersTab::class, ['event' => $event])
            ->call('newItem')
            ->set('name', 'Dr. Layla Haddad')
            ->set('title', 'Chief Economist')
            ->set('is_keynote', true)
            ->set('fee', '5000')
            ->call('save')
            ->assertHasNoErrors();

        $s = $event->speakers()->firstOrFail();
        $this->assertSame('Dr. Layla Haddad', $s->name);
        $this->assertTrue($s->is_keynote);
        $this->assertSame(500000, $s->fee_cents);

        Livewire::actingAs($user)->test(SpeakersTab::class, ['event' => $event])->call('setStatus', $s->id, 'confirmed');
        $this->assertSame('confirmed', $s->fresh()->status);
    }

    public function test_exhibitor_booth_is_tracked(): void
    {
        [$event, $user] = $this->ctx();

        Livewire::actingAs($user)->test(ExhibitionTab::class, ['event' => $event])
            ->call('newItem')
            ->set('company', 'Acme Tech')
            ->set('booth_number', 'A-12')
            ->set('package', 'premium')
            ->set('fee', '8000')
            ->set('paid', '4000')
            ->call('save')
            ->assertHasNoErrors();

        $x = $event->exhibitors()->firstOrFail();
        $this->assertSame(800000, $x->fee_cents);
        $this->assertSame(400000, $x->outstandingCents());
    }

    public function test_transport_movement_is_saved(): void
    {
        [$event, $user] = $this->ctx();

        Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->call('newItem')
            ->set('type', 'vip')
            ->set('route', 'Airport → Hotel')
            ->set('passengers', 3)
            ->set('cost', '250')
            ->call('save')
            ->assertHasNoErrors();

        $t = $event->transport()->firstOrFail();
        $this->assertSame('Airport → Hotel', $t->route);
        $this->assertSame(25000, $t->cost_cents);
    }

    public function test_accommodation_autocomputes_cost_from_rate(): void
    {
        [$event, $user] = $this->ctx();

        Livewire::actingAs($user)->test(AccommodationTab::class, ['event' => $event])
            ->call('newItem')
            ->set('hotel', 'Kempinski Amman')
            ->set('rooms', 2)
            ->set('check_in', '2026-11-09')
            ->set('check_out', '2026-11-14')   // 5 nights
            ->set('rate', '150')                // 150 × 2 rooms × 5 nights = 1500
            ->call('save')
            ->assertHasNoErrors();

        $a = $event->accommodations()->firstOrFail();
        $this->assertSame(5, $a->nights());
        $this->assertSame(10, $a->roomNights());
        $this->assertSame(150000, $a->cost_cents);
    }

    public function test_currency_formatting_switches_symbol(): void
    {
        [$event] = $this->ctx();

        $event->update(['currency' => 'USD']);
        $this->assertSame('$1,250', $event->money(125000));

        $event->update(['currency' => 'JOD']);
        $this->assertSame('JD 1,250', $event->fresh()->money(125000));
    }

    public function test_new_module_tabs_render(): void
    {
        [$event, $user] = $this->ctx();
        $event->update(['enabled_modules' => ['speakers', 'transportation', 'accommodation', 'exhibition']]);

        foreach (['speakers', 'transportation', 'accommodation', 'exhibition'] as $tab) {
            $this->actingAs($user)->get(route('events.hub', [$event, 'tab' => $tab]))
                ->assertOk()->assertSee(ucfirst($tab === 'transportation' ? 'Transport' : $tab), false);
        }
    }
}
