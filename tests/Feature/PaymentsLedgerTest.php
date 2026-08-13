<?php

namespace Tests\Feature;

use App\Livewire\PaymentsLedger;
use App\Models\Event;
use App\Models\EventContract;
use App\Models\EventContractPayment;
use App\Models\User;
use App\Support\NavPanel;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentsLedgerTest extends TestCase
{
    use RefreshDatabase;

    /** Two events with real schedules — the page's whole claim is that it spans them. */
    private function actor(): User
    {
        $this->seed(DemoDataSeeder::class);

        foreach (Event::whereNull('archived_at')->orderBy('id')->take(2)->get() as $event) {
            $contract = EventContract::forEvent($event);
            $contract->ensurePayments();
        }

        return User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
    }

    public function test_the_ledger_renders(): void
    {
        $this->actingAs($this->actor())->get(route('payments.index'))->assertOk()
            ->assertSee('Payments')
            ->assertSee('in the order money is due', false);
    }

    public function test_it_lists_installments_from_every_event(): void
    {
        $user = $this->actor();

        $rows = Livewire::actingAs($user)->test(PaymentsLedger::class)->viewData('rows');

        $this->assertGreaterThan(1, $rows->pluck('event_id')->unique()->count());
        $this->assertCount(
            EventContractPayment::whereHas('event', fn ($q) => $q->whereNull('archived_at'))->count(),
            $rows,
        );
    }

    /** Undated installments sort last — one is not overdue since 1970. */
    public function test_rows_are_ordered_by_due_date_with_undated_last(): void
    {
        $user = $this->actor();

        $first = EventContractPayment::orderBy('id')->firstOrFail();
        $first->update(['due_on' => null]);

        $rows = Livewire::actingAs($user)->test(PaymentsLedger::class)->viewData('rows');

        $stamps = $rows->map(fn ($p) => $p->due_on?->timestamp);
        $dated = $stamps->filter()->values();

        $this->assertSame($dated->sort()->values()->all(), $dated->all());
        $this->assertNull($stamps->last(), 'the undated installment sorts to the end');
    }

    public function test_the_month_grouping_carries_its_own_subtotal(): void
    {
        $user = $this->actor();

        $months = Livewire::actingAs($user)->test(PaymentsLedger::class)->viewData('months');

        foreach ($months as $month) {
            $this->assertSame(
                $month['rows']->sum(fn ($p) => $p->outstandingCents()),
                $month['due'],
            );
        }
    }

    /**
     * "Settled" has to mean every installment is settled, not that the total
     * rounds to nothing — a zero-value installment past its date reads Overdue
     * on its own row, and a month calling itself settled above it is a
     * contradiction on one screen.
     */
    public function test_a_month_is_settled_only_when_every_row_is_paid(): void
    {
        $user = $this->actor();

        // Park the row in a month nothing else can occupy.
        //
        // This assertion is about a MONTH's aggregate, so it only means
        // anything if our row is the only one in its bucket. `subWeek()` used
        // to be that isolation, and it was not: ensurePayments() always writes
        // a deposit dated now(), so the current month is never empty. A week
        // ago only leaves the month on days 1–7 — so this test passed for the
        // first week of every month and failed for the other three. It went
        // green for weeks, then blocked every PR from the 8th.
        //
        // Two years back is clear by construction: schedules are generated
        // from now() and the event's starts_at, never from the distant past.
        $p = EventContractPayment::orderBy('id')->firstOrFail();
        $p->update(['amount_cents' => 0, 'paid_cents' => 0, 'due_on' => now()->subYears(2)->startOfMonth()]);

        $this->assertSame(0, $p->fresh()->outstandingCents());
        $this->assertSame('overdue', $p->fresh()->status());

        $months = Livewire::actingAs($user)->test(PaymentsLedger::class)->viewData('months');
        $month = $months->first(fn ($m) => $m['rows']->contains('id', $p->id));

        // State the isolation rather than assume it: if seeding ever reaches
        // back this far, this fails saying so instead of returning a
        // mysterious non-zero total from somebody else's installment.
        $this->assertCount(1, $month['rows'], 'the row must be alone in its month for a month-level assertion to mean anything');

        $this->assertSame(0, $month['due'], 'nothing is outstanding…');
        $this->assertFalse($month['settled'], '…but the month is not settled while a row is overdue');
    }

    /** Blank settles in full — the same rule the Contract tab uses. */
    public function test_recording_a_payment_settles_in_full_when_left_blank(): void
    {
        $user = $this->actor();
        $p = EventContractPayment::where('amount_cents', '>', 0)->orderBy('id')->firstOrFail();

        Livewire::actingAs($user)->test(PaymentsLedger::class)->call('record', $p->id);

        $p->refresh();
        $this->assertSame($p->amount_cents, $p->paid_cents);
        $this->assertSame('paid', $p->status());
        $this->assertNotNull($p->paid_at);
    }

    public function test_a_part_payment_is_recorded_and_never_exceeds_the_installment(): void
    {
        $user = $this->actor();
        $p = EventContractPayment::where('amount_cents', '>', 10000)->orderBy('id')->firstOrFail();

        $c = Livewire::actingAs($user)->test(PaymentsLedger::class);

        $c->call('record', $p->id, 50);          // 50 units = 5,000 cents
        $this->assertSame(5000, $p->fresh()->paid_cents);
        $this->assertSame('partial', $p->fresh()->status());

        // Overpaying settles it rather than banking a negative outstanding.
        $c->call('record', $p->id, 9_999_999);
        $this->assertSame($p->amount_cents, $p->fresh()->paid_cents);
        $this->assertSame(0, $p->fresh()->outstandingCents());
    }

    public function test_clearing_undoes_a_recorded_payment(): void
    {
        $user = $this->actor();
        $p = EventContractPayment::where('amount_cents', '>', 0)->orderBy('id')->firstOrFail();

        $c = Livewire::actingAs($user)->test(PaymentsLedger::class);
        $c->call('record', $p->id);
        $this->assertSame('paid', $p->fresh()->status());

        $c->call('clear', $p->id);
        $this->assertSame(0, $p->fresh()->paid_cents);
        $this->assertNull($p->fresh()->paid_at);
    }

    /** Money is not something a viewer records. */
    public function test_only_writers_may_record_or_clear(): void
    {
        $this->actor();
        $viewer = User::create(['name' => 'Vic Viewer', 'email' => 'viewer@ebh.test',
            'password' => bcrypt('x'), 'role' => 'viewer']);
        $p = EventContractPayment::where('amount_cents', '>', 0)->orderBy('id')->firstOrFail();

        Livewire::actingAs($viewer)->test(PaymentsLedger::class)
            ->call('record', $p->id)->assertForbidden();

        $this->assertSame(0, $p->fresh()->paid_cents);
    }

    public function test_the_status_filter_uses_the_models_derived_state(): void
    {
        $user = $this->actor();
        $c = Livewire::actingAs($user)->test(PaymentsLedger::class);

        foreach (['overdue', 'pending', 'partial', 'paid'] as $state) {
            $c->call('setStatus', $state);
            $this->assertTrue($c->viewData('rows')->every(fn ($p) => $p->status() === $state), $state);
        }

        $c->call('setStatus', 'nonsense');
        $this->assertSame('all', $c->get('status'));
    }

    /**
     * A filter narrows what you are reading, not what you are owed — the
     * figures are counted across the whole book on purpose.
     */
    public function test_the_figures_ignore_the_filter(): void
    {
        $user = $this->actor();
        $c = Livewire::actingAs($user)->test(PaymentsLedger::class);

        $before = collect($c->viewData('figures'))->firstWhere('label', 'Outstanding')['value'];

        $c->call('setStatus', 'paid');
        $after = collect($c->viewData('figures'))->firstWhere('label', 'Outstanding')['value'];

        $this->assertSame($before, $after);
    }

    public function test_archived_events_are_left_out(): void
    {
        $user = $this->actor();
        $p = EventContractPayment::with('event')->orderBy('id')->firstOrFail();

        $p->event->update(['archived_at' => now()]);

        $rows = Livewire::actingAs($user)->test(PaymentsLedger::class)->viewData('rows');
        $this->assertNotContains($p->id, $rows->pluck('id'));
    }

    public function test_the_nav_links_to_it_now_that_it_exists(): void
    {
        $panel = collect(NavPanel::panel())
            ->flatMap(fn ($s) => $s['items'])
            ->firstWhere('label', 'Payments');

        $this->assertSame(route('payments.index'), $panel['href']);
    }
}
