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
        $this->assertSame(85_000, $line->unit_cents);
        $this->assertSame(255_000, $line->amountCents(), '3 × 850.00');

        // Edited.
        $c->call('editLine', $line->id)->set('unit', '900')->call('saveLine');
        $this->assertSame(90_000, $line->fresh()->unit_cents);

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
        $this->assertSame($invoice->totalCents(), $invoice->paid_cents);
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

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
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
