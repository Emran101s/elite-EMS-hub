<?php

namespace Tests\Feature;

use App\Livewire\Hub\ContractTab;
use App\Models\Event;
use App\Models\EventContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventContractTest extends TestCase
{
    use RefreshDatabase;

    private function make(): array
    {
        $user = User::create(['name' => 'PM', 'email' => 'pm@ebh.test', 'password' => bcrypt('x')]);
        $event = Event::create(['name' => 'Test Summit', 'type' => 'conference', 'city' => 'Amman', 'country' => 'Jordan', 'starts_at' => now(), 'status' => 'planning', 'budget_cents' => 35000000, 'currency' => 'JOD']);

        return [$user, $event];
    }

    public function test_contract_seeds_with_parties_and_financials(): void
    {
        [, $event] = $this->make();

        $c = EventContract::forEvent($event);

        $this->assertStringStartsWith('EBH-CTR-', $c->reference);
        $this->assertCount(2, $c->data['second_parties']);
        $this->assertSame(80, $c->data['second_parties'][0]['share']);
        $this->assertSame(20, $c->data['second_parties'][1]['share']);
        $this->assertSame(35000000, $c->data['financials']['estimated_total_cents']);
        // payment schedule totals 100%
        $this->assertSame(100, collect($c->data['financials']['payment_schedule'])->sum('pct'));
    }

    public function test_clauses_render_bilingual_with_variables(): void
    {
        [, $event] = $this->make();
        $c = EventContract::forEvent($event);

        $clauses = \App\Support\ContractClauses::clauses($c->data);
        $recitals = \App\Support\ContractClauses::recitals($c->data);

        // scope of work present in both languages
        $this->assertSame('Scope of Work', $clauses[0]['en_title']);
        $this->assertSame('نطاق العمل', $clauses[0]['ar_title']);
        $this->assertNotEmpty($recitals['ar']);
        // cost-share rows carry the entity shares
        $cost = collect($clauses)->firstWhere('type', 'costshare');
        $this->assertSame(80, $cost['rows'][0]['share']);
    }

    public function test_editing_persists(): void
    {
        [$user, $event] = $this->make();
        EventContract::forEvent($event);

        Livewire::actingAs($user)->test(ContractTab::class, ['event' => $event])
            ->set('data.second_parties.0.name_en', 'World People Assembly')
            ->set('data.second_parties.0.share', 75)
            ->set('data.second_parties.1.share', 25);

        $c = EventContract::where('event_id', $event->id)->first();
        $this->assertSame('World People Assembly', $c->data['second_parties'][0]['name_en']);
        $this->assertSame(75, $c->data['second_parties'][0]['share']);
    }

    public function test_pdf_downloads(): void
    {
        [$user, $event] = $this->make();

        $res = $this->actingAs($user)->get(route('events.contract.pdf', $event));
        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
    }
}
