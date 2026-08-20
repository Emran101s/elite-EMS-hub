<?php

namespace Tests\Feature;

use App\Livewire\InvoiceEditor;
use App\Livewire\InvoicesLedger;
use App\Models\Event;
use App\Models\EventContract;
use App\Models\EventContractPayment;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The ledger could raise an invoice off a contract installment and then had
 * nothing to say: no way to add a line, correct a price, set the terms, or bill
 * anything the schedule did not already describe. So an invoice for four things
 * had to be four invoices, and a typo was permanent.
 */
class InvoiceEditorTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $this->seed(DemoDataSeeder::class);

        foreach (Event::whereNull('archived_at')->orderBy('id')->take(1)->get() as $event) {
            EventContract::forEvent($event)->ensurePayments();
        }

        return User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
    }

    private function payment(): EventContractPayment
    {
        return EventContractPayment::with(['event.client', 'contract'])
            ->where('amount_cents', '>', 0)->orderBy('id')->firstOrFail();
    }

    /** The company profile is not part of the demo seed, so tests make one. */
    private function house(array $attrs): \App\Models\CompanyProfile
    {
        $house = \App\Models\CompanyProfile::firstOrNew([]);
        $house->fill($attrs + ['name' => $house->name ?: 'Elite Business Hub'])->save();

        \App\Models\CompanyProfile::forgetHouse();

        return $house;
    }

    private function editor(Invoice $invoice, ?User $user = null)
    {
        return Livewire::actingAs($user ?? User::factory()->create(['role' => 'admin']))
            ->test(InvoiceEditor::class, ['invoice' => $invoice]);
    }

    /* ── creating ── */

    public function test_a_blank_invoice_can_be_started_from_the_ledger(): void
    {
        $user = $this->actor();

        Livewire::actingAs($user)->test(InvoicesLedger::class)
            ->call('create')
            ->assertRedirect(route('invoices.edit', Invoice::latest('id')->first()));

        $invoice = Invoice::latest('id')->firstOrFail();
        $this->assertSame('draft', $invoice->status);
        $this->assertCount(0, $invoice->lines, 'a blank invoice starts blank');
        $this->assertNotNull($invoice->due_on);
    }

    public function test_raising_from_an_installment_lands_in_the_editor(): void
    {
        $user = $this->actor();
        $p = $this->payment();

        Livewire::actingAs($user)->test(InvoicesLedger::class)
            ->call('raise', $p->id)
            ->assertRedirect(route('invoices.edit', Invoice::latest('id')->first()));
    }

    public function test_the_editor_page_renders(): void
    {
        $user = $this->actor();
        $invoice = Invoice::fromPayment($this->payment());

        $this->actingAs($user)->get(route('invoices.edit', $invoice))->assertOk()
            ->assertSee($invoice->number)
            ->assertSee('What the client receives');
    }

    /* ── lines ── */

    public function test_a_line_can_be_added_edited_and_removed(): void
    {
        $this->actor();
        $invoice = Invoice::fromPayment($this->payment());
        $was = $invoice->lines->count();

        $c = $this->editor($invoice);

        $c->call('newLine')
            ->set('description', 'Additional AV crew, 3 days')
            ->set('qty', '3')
            ->set('unit', '850')
            ->call('saveLine');

        $invoice->refresh()->load('lines');
        $this->assertCount($was + 1, $invoice->lines);

        $line = $invoice->lines->last();
        $this->assertSame('Additional AV crew, 3 days', $line->description);
        $this->assertEquals(85_000, $line->unit_cents);
        $this->assertEquals(255_000, $line->amountCents(), '3 × 850.00');

        // Edited.
        $c->call('editLine', $line->id)->set('unit', '900')->call('saveLine');
        $this->assertEquals(90_000, $line->fresh()->unit_cents);

        // Removed.
        $c->call('deleteLine', $line->id);
        $this->assertCount($was, $invoice->fresh()->lines);
    }

    public function test_a_line_needs_a_description_and_a_number(): void
    {
        $this->actor();
        $invoice = Invoice::fromPayment($this->payment());

        $this->editor($invoice)
            ->call('newLine')
            ->set('description', '')
            ->set('qty', 'x')
            ->call('saveLine')
            ->assertHasErrors(['description', 'qty']);
    }

    public function test_lines_can_be_reordered(): void
    {
        $this->actor();
        $invoice = Invoice::fromPayment($this->payment());
        $invoice->lines()->create(['description' => 'Second', 'qty' => 1, 'unit_cents' => 100, 'sort' => 1]);
        $invoice->refresh()->load('lines');

        [$first, $second] = [$invoice->lines[0], $invoice->lines[1]];

        $this->editor($invoice)->call('moveLine', $second->id, -1);

        $this->assertSame($second->id, $invoice->fresh()->load('lines')->lines->first()->id);
        $this->assertSame($first->id, $invoice->fresh()->load('lines')->lines->last()->id);
    }

    /**
     * Editing a line after money is recorded has to move the schedule with it,
     * or the two ledgers drift apart at exactly the moment somebody is
     * correcting a mistake.
     */
    public function test_editing_a_line_re_allocates_what_was_collected(): void
    {
        $this->actor();
        $p = $this->payment();
        $invoice = Invoice::fromPayment($p);

        $invoice->update(['status' => 'sent']);
        $invoice = $invoice->fresh()->load('lines');
        $invoice->update(['paid_cents' => $invoice->totalCents()]);

        $this->assertSame($p->amount_cents, $p->fresh()->paid_cents);

        // The line is corrected downward — the allocation follows.
        $line = $invoice->lines->first();
        $this->editor($invoice->fresh()->load('lines'))
            ->call('editLine', $line->id)
            ->set('unit', (string) ($p->amount_cents / 200))   // half, in currency units
            ->call('saveLine');

        $this->assertSame((int) round($p->amount_cents / 2), $p->fresh()->paid_cents);
    }

    /* ── the document ── */

    public function test_the_details_save_and_reach_the_paper(): void
    {
        $user = $this->actor();
        $invoice = Invoice::fromPayment($this->payment());

        $this->editor($invoice)
            ->set('bill_to', 'Gulf Holdings PLC')
            ->set('tax_pct', '16')
            ->set('terms', 'Payment within 30 days by bank transfer.')
            ->call('saveDetails');

        $invoice->refresh();
        $this->assertSame('Gulf Holdings PLC', $invoice->bill_to);
        $this->assertSame(16.0, $invoice->tax_pct);

        // And the document shows it — the preview IS the invoice.
        $this->actingAs($user)->get(route('invoices.edit', $invoice))->assertOk()
            ->assertSee('Gulf Holdings PLC')
            ->assertSee('Payment within 30 days by bank transfer.');
    }

    public function test_a_bad_tax_rate_is_refused(): void
    {
        $this->actor();
        $invoice = Invoice::fromPayment($this->payment());

        $this->editor($invoice)->set('tax_pct', '250')->call('saveDetails')
            ->assertHasErrors(['tax_pct']);
    }

    public function test_the_editor_can_send_record_and_void(): void
    {
        $this->actor();
        $invoice = Invoice::fromPayment($this->payment());
        $c = $this->editor($invoice);

        $c->call('markSent');
        $this->assertSame('sent', $invoice->fresh()->status);

        $c->call('record');
        $invoice = $invoice->fresh()->load('lines');
        $this->assertEquals($invoice->totalCents(), $invoice->paid_cents);
        $this->assertSame('paid', $invoice->state());

        $c->call('clearPaid');
        $this->assertSame(0, $invoice->fresh()->paid_cents);

        $c->call('void');
        $this->assertSame('void', $invoice->fresh()->state());
    }

    public function test_deleting_a_draft_returns_to_the_ledger(): void
    {
        $this->actor();
        $invoice = Invoice::fromPayment($this->payment());

        $this->editor($invoice)->call('destroyDraft')
            ->assertRedirect(route('invoices.index'));

        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
    }

    public function test_a_sent_invoice_is_not_deleted_by_the_editor(): void
    {
        $this->actor();
        $invoice = Invoice::fromPayment($this->payment());
        $invoice->update(['status' => 'sent']);

        $this->editor($invoice->fresh()->load('lines'))->call('destroyDraft');

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    }

    public function test_only_writers_may_edit(): void
    {
        $this->actor();
        $invoice = Invoice::fromPayment($this->payment());
        $viewer = User::create(['name' => 'Vic Viewer', 'email' => 'viewer@ebh.test',
            'password' => bcrypt('x'), 'role' => 'viewer']);

        $this->editor($invoice, $viewer)
            ->call('newLine')->assertForbidden();
    }

    /* ══ the house settings, and the fee ══ */

    public function test_a_new_invoice_takes_the_currency_and_fee_from_settings(): void
    {
        $user = $this->actor();

        $this->house(['default_currency' => 'USD', 'default_management_fee_pct' => 12.5]);

        Livewire::actingAs($user)->test(InvoicesLedger::class)->call('create');

        $invoice = Invoice::latest('id')->firstOrFail();
        $this->assertSame('USD', $invoice->currency, 'the house currency, not a constant');
        $this->assertSame(12.5, $invoice->fee_pct);
    }

    /**
     * The fee sits between the work and the tax, and is taxed with it: it is
     * part of what is being charged.
     */
    public function test_the_fee_is_charged_on_the_work_and_taxed_with_it(): void
    {
        $this->actor();
        $invoice = Invoice::create(['number' => Invoice::nextNumber(), 'status' => 'draft',
            'fee_pct' => 15, 'tax_pct' => 16]);
        $invoice->lines()->create(['description' => 'Rooms', 'qty' => 36, 'unit_cents' => 95_00]);
        $invoice = $invoice->fresh()->load('lines');

        $this->assertEquals(3_420_00, $invoice->subtotalCents());
        $this->assertSame(513_00, $invoice->feeCents(), '15% of the work');
        $this->assertEquals(3_933_00, $invoice->netCents());
        $this->assertSame((int) round(3_933_00 * 0.16), $invoice->taxCents(), 'tax on the net, fee included');
        $this->assertEquals(3_933_00 + (int) round(3_933_00 * 0.16), $invoice->totalCents());
    }

    /** With no fee the arithmetic is exactly what it always was. */
    public function test_no_fee_leaves_the_totals_untouched(): void
    {
        $this->actor();
        $invoice = Invoice::create(['number' => Invoice::nextNumber(), 'status' => 'draft', 'tax_pct' => 16]);
        $invoice->lines()->create(['description' => 'Rooms', 'qty' => 1, 'unit_cents' => 1_000_00]);
        $invoice = $invoice->fresh()->load('lines');

        $this->assertSame(0, $invoice->feeCents());
        $this->assertEquals($invoice->subtotalCents(), $invoice->netCents());
        $this->assertSame(160_00, $invoice->taxCents());
    }

    /**
     * A contract installment is a share of the contract VALUE, and that value
     * already includes the management fee. Charging it again bills the client
     * twice for the same thing.
     */
    public function test_an_invoice_raised_from_a_schedule_carries_no_fee(): void
    {
        $this->actor();

        $this->house(['default_management_fee_pct' => 15]);

        $invoice = Invoice::fromPayment($this->payment());

        $this->assertSame(0.0, (float) $invoice->fee_pct,
            'the schedule already includes the fee');
        $this->assertEquals($invoice->subtotalCents(), $invoice->netCents());
    }

    /** A negotiated rate lives on the event, so attaching one adopts it. */
    public function test_attaching_an_event_adopts_that_events_fee(): void
    {
        $this->actor();
        $event = Event::whereNull('archived_at')->firstOrFail();
        $event->update(['management_fee_pct' => 22.5]);

        $invoice = Invoice::create(['number' => Invoice::nextNumber(), 'status' => 'draft', 'fee_pct' => 15]);

        $this->editor($invoice)->set('event_id', $event->id);

        $this->assertSame(22.5, (float) $invoice->fresh()->fee_pct);
    }

    /** The fee reaches the paper as its own row, not smeared across the lines. */
    public function test_the_fee_appears_on_the_document(): void
    {
        $user = $this->actor();
        $invoice = Invoice::create(['number' => Invoice::nextNumber(), 'status' => 'draft', 'fee_pct' => 15]);
        $invoice->lines()->create(['description' => 'Rooms', 'qty' => 1, 'unit_cents' => 1_000_00]);

        $this->actingAs($user)->get(route('invoices.edit', $invoice))->assertOk()
            ->assertSee('Management fee (15%)');
    }

    /** The PDF route must not be swallowed by the editor's {invoice}. */
    public function test_the_pdf_and_the_editor_are_different_pages(): void
    {
        $user = $this->actor();
        $invoice = Invoice::fromPayment($this->payment());

        $pdf = $this->actingAs($user)->get(route('invoices.pdf', $invoice))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));

        $editor = $this->actingAs($user)->get(route('invoices.edit', $invoice))->assertOk();
        $this->assertStringContainsString('text/html', $editor->headers->get('content-type'));
    }
}
