<?php

namespace Tests\Feature;

use App\Livewire\ContractsRegister;
use App\Models\Event;
use App\Models\EventContract;
use App\Models\User;
use App\Support\NavPanel;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContractsRegisterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A book with paperwork in it.
     *
     * The demo seeder builds events but no contracts — a document is made when
     * somebody drafts one, which is the right default and no use to a register
     * test. So three events get documents here, in three different states, on
     * purpose: the whole point of this page is that it spans events.
     */
    private function actor(): User
    {
        $this->seed(DemoDataSeeder::class);

        $events = Event::whereNull('archived_at')->orderBy('id')->take(3)->get();
        $this->assertCount(3, $events, 'the fixture needs three events to span');

        foreach ($events as $i => $event) {
            $client = EventContract::forEvent($event);
            $client->ensurePayments();
            $client->ensureSignatories();
            $client->update(['status' => ['draft', 'sent', 'signed'][$i]]);

            // A second, differently typed document on each, so the type filter
            // has something to filter and the register has something to sort.
            EventContract::create([
                'event_id' => $event->id,
                'type' => 'letter',
                'title' => 'Letter of intent',
                'language' => 'en',
                'reference' => 'EBH-LTR-'.$event->id,
                'status' => 'draft',
                'version' => 1,
                'data' => [],
            ]);
        }

        return User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
    }

    public function test_the_register_page_renders(): void
    {
        $this->actingAs($this->actor())->get(route('contracts.index'))->assertOk()
            ->assertSee('Contracts')
            ->assertSee('What is drafted, out for pen, and signed.', false);
    }

    /**
     * The register is the whole book. An event's own Deck answers "what does
     * this one look like"; this answers "what is waiting on a pen", which is
     * not a question about one event.
     */
    public function test_it_lists_every_document_across_every_event(): void
    {
        $user = $this->actor();

        $expected = EventContract::whereHas('event', fn ($q) => $q->whereNull('archived_at'))->count();
        $this->assertGreaterThan(1, $expected, 'the fixture needs documents on more than one event');

        $docs = Livewire::actingAs($user)->test(ContractsRegister::class)->viewData('docs');

        $this->assertCount($expected, $docs);
        $this->assertGreaterThan(1, $docs->pluck('event_id')->unique()->count(),
            'documents from more than one event reach the register');
    }

    /** An archived event is out of the book, so its paperwork is too. */
    public function test_archived_events_are_left_out(): void
    {
        $user = $this->actor();
        $doc = EventContract::whereHas('event', fn ($q) => $q->whereNull('archived_at'))->firstOrFail();

        $before = Livewire::actingAs($user)->test(ContractsRegister::class)->viewData('docs')->count();

        $doc->event->update(['archived_at' => now()]);

        $after = Livewire::actingAs($user)->test(ContractsRegister::class)->viewData('docs');

        $this->assertCount($before - $doc->event->contracts()->count(), $after);
        $this->assertNotContains($doc->id, $after->pluck('id'));
    }

    public function test_status_and_type_filters_narrow_the_register(): void
    {
        $user = $this->actor();
        $c = Livewire::actingAs($user)->test(ContractsRegister::class);

        $c->call('setStatus', 'draft');
        $this->assertTrue($c->viewData('docs')->every(fn ($d) => $d->status === 'draft'));

        $c->call('setStatus', 'all')->call('setType', 'client');
        $this->assertTrue($c->viewData('docs')->every(fn ($d) => $d->type === 'client'));

        // A key that is not a status or a type falls back rather than emptying
        // the page — a bad query string should not look like "no contracts".
        $c->call('setStatus', 'nonsense')->call('setType', 'nonsense');
        $this->assertSame('all', $c->get('status'));
        $this->assertSame('all', $c->get('type'));
    }

    public function test_search_matches_reference_event_and_counterparty(): void
    {
        $user = $this->actor();
        $doc = EventContract::with('event')
            ->whereHas('event', fn ($q) => $q->whereNull('archived_at'))
            ->whereNotNull('reference')->firstOrFail();

        $c = Livewire::actingAs($user)->test(ContractsRegister::class);

        $c->set('q', $doc->reference);
        $this->assertContains($doc->id, $c->viewData('docs')->pluck('id'));

        // By the event's name, which is how you look for it when you cannot
        // remember a reference — which is always.
        $c->set('q', $doc->event->name);
        $this->assertContains($doc->id, $c->viewData('docs')->pluck('id'));

        $c->set('q', 'zzz-no-such-document');
        $this->assertCount(0, $c->viewData('docs'));
    }

    /**
     * Outstanding is counted off the installments, not the contract value: a
     * signed contract with three of four installments paid is not owed in full,
     * and a register that says so is worse than one that says nothing.
     */
    public function test_outstanding_counts_installments_not_contract_value(): void
    {
        $user = $this->actor();

        $doc = EventContract::with('payments')
            ->whereHas('event', fn ($q) => $q->whereNull('archived_at'))
            ->whereHas('payments')->first();

        if (! $doc) {
            $this->markTestSkipped('the fixture carries no contract with a payment schedule');
        }

        $payment = $doc->payments->first();
        $payment->update(['paid_cents' => $payment->amount_cents]);   // settled in full

        $figures = Livewire::actingAs($user)->test(ContractsRegister::class)->viewData('figures');
        $outstanding = collect($figures)->firstWhere('label', 'Outstanding');

        $expected = EventContract::whereHas('event', fn ($q) => $q->whereNull('archived_at'))
            ->with('payments')->get()
            ->sum(fn ($c) => $c->payments->sum(fn ($p) => $p->outstandingCents()));

        // The settled installment is out of the figure, and the figure is not
        // the sum of the contract values.
        $this->assertSame(0, $payment->fresh()->outstandingCents());
        $this->assertStringContainsString(
            $expected >= 1000 ? (string) round($expected / 100 / 1000) : (string) ($expected / 100),
            $outstanding['value'],
        );
    }

    /**
     * The lanes come from the model's own pipelineColumn(), so the board and
     * the event's Deck cannot disagree about where a document sits.
     */
    public function test_the_pipeline_groups_by_the_models_own_column(): void
    {
        $user = $this->actor();

        $lanes = Livewire::actingAs($user)->test(ContractsRegister::class)
            ->call('setView', 'pipeline')->viewData('lanes');

        foreach ($lanes as $lane) {
            foreach ($lane['docs'] as $doc) {
                $this->assertSame($lane['key'], $doc->pipelineColumn());
            }
        }

        // Void is only drawn when something is actually void: an empty lane is
        // a lane you have to read to dismiss.
        $this->assertNotContains('void', $lanes->pluck('key'),
            'nothing is void in the fixture, so the lane stays away');

        EventContract::whereHas('event', fn ($q) => $q->whereNull('archived_at'))
            ->first()->update(['status' => 'void']);

        $lanes = Livewire::actingAs($user)->test(ContractsRegister::class)
            ->call('setView', 'pipeline')->viewData('lanes');

        $this->assertContains('void', $lanes->pluck('key'));
    }

    /** Undated documents sort last rather than as the epoch. */
    public function test_the_register_sorts_by_the_next_unpaid_installment(): void
    {
        $user = $this->actor();

        $docs = Livewire::actingAs($user)->test(ContractsRegister::class)
            ->call('sortBy', 'due')->viewData('docs');

        $due = $docs->map(fn ($c) => $c->payments->first(fn ($p) => $p->status() !== 'paid')?->due_on?->timestamp);

        $dated = $due->filter()->values();
        $this->assertSame($dated->sort()->values()->all(), $dated->all(), 'dated documents come first, in order');

        // …and every undated one is after every dated one.
        $firstUndated = $due->search(null, true);
        if ($firstUndated !== false) {
            $this->assertTrue($due->slice($firstUndated)->every(fn ($t) => $t === null));
        }
    }

    public function test_the_nav_links_to_it_now_that_it_exists(): void
    {
        $user = $this->actor();

        $panel = collect(NavPanel::panel())
            ->flatMap(fn ($s) => $s['items'])
            ->firstWhere('label', 'Contracts');

        $this->assertNotNull($panel['href'], 'the Contracts row is no longer a "coming soon" placeholder');
        $this->assertSame(route('contracts.index'), $panel['href']);

        // This used to also assert the Command Center's own HTML contained the
        // contracts URL. It no longer does, and that is the intended
        // behaviour: the context sidebar renders only the *active area's* core
        // links, so Contracts appears under Commercial rather than on every
        // page. What matters is that the row exists, points somewhere real,
        // and that the destination opens — which is what is asserted now.
        $this->actingAs($user)->get($panel['href'])->assertOk();
    }
}
