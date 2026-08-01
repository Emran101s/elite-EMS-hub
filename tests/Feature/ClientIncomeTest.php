<?php

namespace Tests\Feature;

use App\Livewire\Hub\BudgetTab;
use App\Models\Event;
use App\Models\EventContract;
use App\Models\EventContractPayment;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Client money is counted once, wherever it was recorded.
 *
 * Three places record it and each is somebody's job on a different day: the
 * contract's payment schedule, an invoice raised outside that schedule, and a
 * figure typed into the budget. The budget read the first and the third, so a
 * paid one-off invoice was income that existed in the ledger and nowhere else.
 */
class ClientIncomeTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        return Event::factory()->create(['client_target_cents' => null]);
    }

    private function invoice(Event $event, int $total, int $paid): Invoice
    {
        $invoice = Invoice::create([
            'number' => Invoice::nextNumber(), 'status' => 'sent',
            'event_id' => $event->id, 'tax_pct' => 0, 'fee_pct' => 0,
        ]);

        $invoice->lines()->create(['description' => 'Additional stand build', 'qty' => 1, 'unit_cents' => $total]);
        $invoice->update(['paid_cents' => $paid, 'paid_at' => $paid > 0 ? now() : null]);

        return $invoice->fresh()->load('lines');
    }

    public function test_a_paid_invoice_is_income_even_with_no_contract(): void
    {
        $event = $this->event();
        $this->invoice($event, 12_000_00, 12_000_00);

        $this->assertSame(12_000_00, $event->fresh()->clientIncome()['collected']);
    }

    public function test_an_unpaid_invoice_is_not_income(): void
    {
        $event = $this->event();
        $this->invoice($event, 12_000_00, 0);

        $this->assertSame(0, $event->fresh()->clientIncome()['collected']);
    }

    public function test_a_void_invoice_collected_nothing_anybody_should_believe_in(): void
    {
        $event = $this->event();
        $invoice = $this->invoice($event, 12_000_00, 12_000_00);

        $invoice->update(['status' => 'void']);

        $this->assertSame(0, $event->fresh()->clientIncome()['collected']);
    }

    /**
     * The trap: an invoice raised from an installment pushes what it collects
     * onto that installment. Counting both books the same payment twice.
     */
    public function test_an_invoice_raised_from_an_installment_is_not_counted_twice(): void
    {
        $event = $this->event();
        $contract = EventContract::forEvent($event);

        $payment = EventContractPayment::create([
            'event_id' => $event->id, 'contract_id' => $contract->id,
            'label' => 'On signature', 'pct' => 100, 'amount_cents' => 50_000_00, 'sort' => 1,
        ]);

        $invoice = Invoice::fromPayment($payment);
        $invoice->update(['paid_cents' => 50_000_00, 'paid_at' => now()]);

        $money = $event->fresh()->clientIncome();

        $this->assertSame(50_000_00, $money['contract'], 'the schedule holds it');
        $this->assertSame(0, $money['invoices'], '…so the invoice must not hold it again');
        $this->assertSame(50_000_00, $money['collected']);
    }

    /** A mixed invoice: part against an installment, part extra work. */
    public function test_only_the_part_the_schedule_does_not_know_about_is_added(): void
    {
        $event = $this->event();
        $contract = EventContract::forEvent($event);

        $payment = EventContractPayment::create([
            'event_id' => $event->id, 'contract_id' => $contract->id,
            'label' => 'On signature', 'pct' => 100, 'amount_cents' => 30_000_00, 'sort' => 1,
        ]);

        $invoice = Invoice::fromPayment($payment);
        $invoice->lines()->create(['description' => 'Extra: a second stage', 'qty' => 1, 'unit_cents' => 8_000_00]);
        $invoice->update(['paid_cents' => 38_000_00, 'paid_at' => now()]);

        $money = $event->fresh()->clientIncome();

        $this->assertSame(30_000_00, $money['contract']);
        $this->assertSame(8_000_00, $money['invoices']);
        $this->assertSame(38_000_00, $money['collected']);
    }

    public function test_the_three_sources_add_up_and_are_named_on_the_screen(): void
    {
        $event = $this->event();
        $contract = EventContract::forEvent($event);

        $payment = EventContractPayment::create([
            'event_id' => $event->id, 'contract_id' => $contract->id,
            'label' => 'On signature', 'pct' => 100, 'amount_cents' => 20_000_00, 'sort' => 1,
        ]);
        Invoice::fromPayment($payment)->update(['paid_cents' => 20_000_00, 'paid_at' => now()]);

        $this->invoice($event, 5_000_00, 5_000_00);
        $event->incomeItems()->create(['source' => 'client', 'label' => 'Cash on site', 'amount_cents' => 1_000_00]);

        $c = Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(BudgetTab::class, ['event' => $event->fresh()]);

        $money = $c->viewData('clientMoney');

        $this->assertSame(20_000_00, $money['contract']);
        $this->assertSame(5_000_00, $money['invoices']);
        $this->assertSame(1_000_00, $money['manual']);
        $this->assertSame(26_000_00, $c->viewData('clientActual'));
    }

    /** The two screens that show client income must never disagree. */
    public function test_the_portfolio_summary_reaches_the_same_number(): void
    {
        $event = $this->event();
        $this->invoice($event, 9_000_00, 4_000_00);

        $event = $event->fresh();

        $this->assertSame($event->clientIncome()['collected'], $event->incomeSummary()['client']);
    }
}
