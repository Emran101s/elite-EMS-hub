<?php

namespace Tests\Feature;

use App\Livewire\InvoicesLedger;
use App\Models\Event;
use App\Models\EventContract;
use App\Models\EventContractPayment;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $this->seed(DemoDataSeeder::class);

        foreach (Event::whereNull('archived_at')->orderBy('id')->take(2)->get() as $event) {
            EventContract::forEvent($event)->ensurePayments();
        }

        return User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
    }

    private function payment(): EventContractPayment
    {
        return EventContractPayment::with(['event.client', 'contract'])
            ->where('amount_cents', '>', 0)->orderBy('id')->firstOrFail();
    }

    /* ── the model ── */

    public function test_an_invoice_raised_from_an_installment_copies_its_figures(): void
    {
        $this->actor();
        $p = $this->payment();

        $invoice = Invoice::fromPayment($p);

        $this->assertSame($p->event_id, $invoice->event_id);
        $this->assertSame($p->contract_id, $invoice->contract_id);
        $this->assertSame('draft', $invoice->status);
        $this->assertCount(1, $invoice->lines);
        $this->assertSame($p->amount_cents, $invoice->subtotalCents());
        $this->assertSame($p->due_on?->toDateString(), $invoice->due_on?->toDateString(),
            'the promise the contract made is the date it is due');
    }

    /**
     * The line records where it came from; it does not defer to it. Editing an
     * invoice must never quietly rewrite the agreement behind it.
     */
    public function test_editing_a_line_does_not_touch_the_contract_schedule(): void
    {
        $this->actor();
        $p = $this->payment();
        $was = $p->amount_cents;

        $invoice = Invoice::fromPayment($p);
        $invoice->lines->first()->update(['unit_cents' => 12345, 'description' => 'Amended']);

        $this->assertSame($was, $p->fresh()->amount_cents, 'the installment is untouched');
        $this->assertSame(12345, $invoice->fresh()->load('lines')->subtotalCents());
        $this->assertSame($p->id, $invoice->lines->first()->payment_id, 'provenance survives the edit');
    }

    public function test_totals_carry_tax_and_round_once(): void
    {
        $this->actor();

        $invoice = Invoice::create([
            'number' => Invoice::nextNumber(), 'status' => 'draft', 'tax_pct' => 16,
        ]);
        $invoice->lines()->createMany([
            ['description' => 'A', 'qty' => 3, 'unit_cents' => 33_33, 'sort' => 0],
            ['description' => 'B', 'qty' => 1.5, 'unit_cents' => 1_00, 'sort' => 1],
        ]);
        $invoice->load('lines');

        // 3 × 33.33 = 99.99, 1.5 × 1.00 = 1.50 → 101.49
        $this->assertSame(10149, $invoice->subtotalCents());
        $this->assertSame((int) round(10149 * 0.16), $invoice->taxCents());
        $this->assertSame(10149 + (int) round(10149 * 0.16), $invoice->totalCents());
    }

    /**
     * Draft and void are what the office decided, so they win. Everything else
     * is arithmetic — a sent invoice with the money in is paid, whatever
     * anybody remembered to click.
     */
    public function test_state_is_decided_by_money_and_dates_not_by_a_column(): void
    {
        $this->actor();
        $invoice = Invoice::create(['number' => Invoice::nextNumber(), 'status' => 'draft']);
        $invoice->lines()->create(['description' => 'Fee', 'qty' => 1, 'unit_cents' => 100_00]);
        $invoice->load('lines');

        $this->assertSame('draft', $invoice->state(), 'a draft is a draft even when overdue on paper');

        $invoice->update(['status' => 'sent', 'due_on' => now()->addWeek()]);
        $this->assertSame('sent', $invoice->fresh()->load('lines')->state());

        $invoice->update(['due_on' => now()->subWeek()]);
        $this->assertSame('overdue', $invoice->fresh()->load('lines')->state());

        $invoice->update(['paid_cents' => 40_00]);
        $this->assertSame('partial', $invoice->fresh()->load('lines')->state());

        $invoice->update(['paid_cents' => 100_00]);
        $this->assertSame('paid', $invoice->fresh()->load('lines')->state());

        $invoice->update(['status' => 'void']);
        $this->assertSame('void', $invoice->fresh()->load('lines')->state(), 'void overrides the arithmetic');
    }

    public function test_numbers_are_sequential_and_never_collide(): void
    {
        $this->actor();

        $a = Invoice::create(['number' => Invoice::nextNumber(), 'status' => 'draft']);
        $b = Invoice::create(['number' => Invoice::nextNumber(), 'status' => 'draft']);

        $this->assertSame('EBH-INV-'.now()->format('Y').'-001', $a->number);
        $this->assertSame('EBH-INV-'.now()->format('Y').'-002', $b->number);

        // Deleting the first leaves a gap; the next number walks past it rather
        // than colliding on the unique index.
        $a->delete();
        $c = Invoice::create(['number' => Invoice::nextNumber(), 'status' => 'draft']);
        $this->assertNotSame($b->number, $c->number);
    }

    /* ── the page ── */

    public function test_the_ledger_renders_and_offers_what_has_not_been_raised(): void
    {
        $user = $this->actor();

        $this->actingAs($user)->get(route('invoices.index'))->assertOk()
            ->assertSee('Invoices')
            ->assertSee('ready to invoice');

        $ready = Livewire::actingAs($user)->test(InvoicesLedger::class)->viewData('ready');
        $this->assertGreaterThan(0, $ready->count());
    }

    public function test_raising_from_the_page_creates_a_draft_and_clears_it_from_the_ready_list(): void
    {
        $user = $this->actor();
        $p = $this->payment();

        $c = Livewire::actingAs($user)->test(InvoicesLedger::class);
        $this->assertContains($p->id, $c->viewData('ready')->pluck('id'));

        // Raising redirects into the editor, so the assertion about the ready
        // list belongs to a fresh page rather than to the component that left.
        $c->call('raise', $p->id)->assertRedirect();

        $invoice = Invoice::with('lines')->latest('id')->firstOrFail();
        $this->assertSame($p->id, $invoice->lines->first()->payment_id);

        $this->assertNotContains(
            $p->id,
            Livewire::actingAs($user)->test(InvoicesLedger::class)->viewData('ready')->pluck('id'),
            'an installment that has been billed is no longer waiting to be',
        );
    }

    /** Raising twice off one installment is the easy mistake, so it is refused. */
    public function test_an_installment_cannot_be_invoiced_twice(): void
    {
        $user = $this->actor();
        $p = $this->payment();

        $c = Livewire::actingAs($user)->test(InvoicesLedger::class);
        $c->call('raise', $p->id)->call('raise', $p->id);

        $this->assertSame(1, InvoiceLine::where('payment_id', $p->id)->count());
        $this->assertSame(1, Invoice::count());
    }

    public function test_recording_money_settles_in_full_when_blank_and_marks_a_draft_sent(): void
    {
        $user = $this->actor();
        $p = $this->payment();

        $c = Livewire::actingAs($user)->test(InvoicesLedger::class)->call('raise', $p->id);
        $invoice = Invoice::with('lines')->latest('id')->firstOrFail();
        $this->assertSame('draft', $invoice->status);

        $c->call('record', $invoice->id);

        $invoice = $invoice->fresh()->load('lines');
        $this->assertSame($invoice->totalCents(), $invoice->paid_cents);
        $this->assertSame('paid', $invoice->state());
        $this->assertSame('sent', $invoice->status,
            'money against a draft means it was sent and nobody said so');
    }

    public function test_a_part_payment_never_exceeds_the_total(): void
    {
        $user = $this->actor();
        $p = $this->payment();

        $c = Livewire::actingAs($user)->test(InvoicesLedger::class)->call('raise', $p->id);
        $invoice = Invoice::with('lines')->latest('id')->firstOrFail();

        $c->call('record', $invoice->id, 9_999_999);
        $this->assertSame($invoice->fresh()->load('lines')->totalCents(), $invoice->fresh()->paid_cents);
        $this->assertSame(0, $invoice->fresh()->load('lines')->outstandingCents());
    }

    /**
     * Once a document has left the building its number stays in the book. An
     * auditor asking about a missing number is not a conversation anybody wants
     * to have — that is what Void is for.
     */
    public function test_only_a_draft_can_be_deleted(): void
    {
        $user = $this->actor();
        $p = $this->payment();

        $c = Livewire::actingAs($user)->test(InvoicesLedger::class)->call('raise', $p->id);
        $invoice = Invoice::latest('id')->firstOrFail();

        $c->call('markSent', $invoice->id);
        $c->call('destroyDraft', $invoice->id);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);

        $c->call('void', $invoice->id);
        $this->assertSame('void', $invoice->fresh()->state());

        // A genuine draft goes.
        $other = Invoice::create(['number' => Invoice::nextNumber(), 'status' => 'draft']);
        $c->call('destroyDraft', $other->id);
        $this->assertDatabaseMissing('invoices', ['id' => $other->id]);
    }

    public function test_only_writers_may_raise_or_record(): void
    {
        $this->actor();
        $p = $this->payment();
        $viewer = User::create(['name' => 'Vic Viewer', 'email' => 'viewer@ebh.test',
            'password' => bcrypt('x'), 'role' => 'viewer']);

        Livewire::actingAs($viewer)->test(InvoicesLedger::class)
            ->call('raise', $p->id)->assertForbidden();

        $this->assertSame(0, Invoice::count());
    }

    public function test_the_state_filter_uses_the_derived_state(): void
    {
        $user = $this->actor();
        $c = Livewire::actingAs($user)->test(InvoicesLedger::class);

        $c->call('raise', $this->payment()->id);
        $c->call('setState', 'draft');
        $this->assertTrue($c->viewData('rows')->every(fn ($i) => $i->state() === 'draft'));

        $c->call('setState', 'paid');
        $this->assertCount(0, $c->viewData('rows'));

        $c->call('setState', 'nonsense');
        $this->assertSame('all', $c->get('state'));
    }

    public function test_the_pdf_downloads(): void
    {
        $user = $this->actor();
        $invoice = Invoice::fromPayment($this->payment());

        $res = $this->actingAs($user)->get(route('invoices.pdf', $invoice))->assertOk();

        $this->assertSame('application/pdf', $res->headers->get('content-type'));
        $this->assertStringContainsString($invoice->number, $res->headers->get('content-disposition'));
    }

    public function test_the_nav_links_to_it_now_that_it_exists(): void
    {
        $panel = collect(\App\Support\NavPanel::panel())
            ->flatMap(fn ($s) => $s['items'])
            ->firstWhere('label', 'Invoices');

        $this->assertSame(route('invoices.index'), $panel['href']);
    }

    /* ══ the link back to the schedule ══
       Without this the two ledgers contradict each other about the same money:
       an invoice collected in full while its installment still reads "overdue,
       JD52,500 outstanding". ══ */

    public function test_money_recorded_on_an_invoice_lands_on_its_installment(): void
    {
        $user = $this->actor();
        $p = $this->payment();
        $this->assertSame(0, $p->paid_cents);

        $c = Livewire::actingAs($user)->test(InvoicesLedger::class)->call('raise', $p->id);
        $invoice = Invoice::latest('id')->firstOrFail();

        $c->call('record', $invoice->id);

        $p->refresh();
        $this->assertSame($p->amount_cents, $p->paid_cents);
        $this->assertSame('paid', $p->status(), 'the schedule agrees with the invoice');
        $this->assertNotNull($p->paid_at);
    }

    public function test_a_part_payment_lands_proportionally(): void
    {
        $user = $this->actor();
        $p = $this->payment();

        $c = Livewire::actingAs($user)->test(InvoicesLedger::class)->call('raise', $p->id);
        $invoice = Invoice::with('lines')->latest('id')->firstOrFail();

        $c->call('record', $invoice->id, $invoice->totalCents() / 100 / 2);   // half

        $p->refresh();
        $this->assertSame((int) round($invoice->totalCents() / 2), $p->paid_cents);
        $this->assertSame('partial', $p->status());
    }

    /**
     * Tax is collected on top of the lines and settles no installment, so a
     * client paying an invoice in full settles its lines EXACTLY — with nothing
     * left over to overpay the schedule with.
     */
    public function test_tax_does_not_overpay_the_installment(): void
    {
        $user = $this->actor();
        $p = $this->payment();

        $c = Livewire::actingAs($user)->test(InvoicesLedger::class)->call('raise', $p->id);
        $invoice = Invoice::latest('id')->firstOrFail();
        $invoice->update(['tax_pct' => 16]);

        $c->call('record', $invoice->id);   // the whole tax-inclusive total

        $invoice = $invoice->fresh()->load('lines');
        $p->refresh();

        $this->assertGreaterThan($p->amount_cents, $invoice->paid_cents, 'more cash came in than the line is worth');
        $this->assertSame($p->amount_cents, $p->paid_cents, 'but the installment is settled, not overpaid');
        $this->assertSame(0, $p->outstandingCents());
    }

    /** Voiding hands the installment back: it is billable again, and unpaid. */
    public function test_voiding_releases_the_installment(): void
    {
        $user = $this->actor();
        $p = $this->payment();

        $c = Livewire::actingAs($user)->test(InvoicesLedger::class)->call('raise', $p->id);
        $invoice = Invoice::latest('id')->firstOrFail();
        $c->call('record', $invoice->id);
        $this->assertSame('paid', $p->fresh()->status());

        $c->call('void', $invoice->id);

        $p->refresh()->load('invoiceLines.invoice');
        $this->assertSame(0, $p->paid_cents, 'a void invoice collected nothing');
        $this->assertFalse($p->isInvoiced(), 'and is no longer asking for it');
        $this->assertContains($p->id, $c->viewData('ready')->pluck('id'), 'so it can be billed again');
    }

    /** Deleting a draft raised by mistake hands the installment back too. */
    public function test_deleting_a_draft_releases_the_installment(): void
    {
        $user = $this->actor();
        $p = $this->payment();

        $c = Livewire::actingAs($user)->test(InvoicesLedger::class)->call('raise', $p->id);
        $invoice = Invoice::latest('id')->firstOrFail();

        $c->call('destroyDraft', $invoice->id);

        $p->refresh()->load('invoiceLines.invoice');
        $this->assertFalse($p->isInvoiced());
        $this->assertContains($p->id, $c->viewData('ready')->pluck('id'));
    }

    /**
     * Two places that take the same payment is how a ledger counts it twice, so
     * the Payments page stops recording once an invoice owns the installment.
     */
    public function test_the_payments_page_will_not_record_an_invoiced_installment(): void
    {
        $user = $this->actor();
        $p = $this->payment();

        Livewire::actingAs($user)->test(InvoicesLedger::class)->call('raise', $p->id);

        Livewire::actingAs($user)->test(\App\Livewire\PaymentsLedger::class)
            ->call('record', $p->id, 100);

        $this->assertSame(0, $p->fresh()->paid_cents, 'the invoice is where that money goes');

        // …and it starts recording again the moment nothing is asking for it.
        Invoice::latest('id')->firstOrFail()->update(['status' => 'void']);

        Livewire::actingAs($user)->test(\App\Livewire\PaymentsLedger::class)
            ->call('record', $p->id, 100);

        $this->assertSame(100_00, $p->fresh()->paid_cents);
    }
}
