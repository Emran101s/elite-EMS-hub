<?php

namespace Tests\Feature;

use App\Livewire\CompanySettings;
use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every invoice says where to send the money, and Settings decides what it says.
 *
 * The details lived in a Word template outside the platform, so every invoice
 * raised here went out without them — an invoice somebody has to reply to
 * before they can pay it.
 */
class InvoicePaymentDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function house(array $accounts = []): CompanyProfile
    {
        $profile = CompanyProfile::current();
        $profile->update(['bank_accounts' => $accounts ?: [$this->usd()]]);

        CompanyProfile::forgetHouse();

        return $profile->fresh();
    }

    private function usd(): array
    {
        return [
            'label' => 'USD Account',
            'account_name' => 'Al Sattam for Exhibitions, Conferences and Consulting Services',
            'bank_name' => 'Bank Al Etihad — Jordan',
            'account_no' => '039026525115101',
            'iban' => 'JO06 UBSI 1270 0003 9026 5225 1151 01',
            'swift' => 'UBSIJOAX',
            'currency' => 'USD — United States Dollar',
        ];
    }

    private function invoice(): Invoice
    {
        $invoice = Invoice::create(['number' => Invoice::nextNumber(), 'status' => 'sent']);
        $invoice->lines()->create(['description' => 'Event management', 'qty' => 1, 'unit_cents' => 10_000_00]);

        return $invoice->fresh()->load('lines');
    }

    public function test_the_account_is_printed_on_the_invoice(): void
    {
        $this->house();
        $invoice = $this->invoice();

        $html = $this->actingAs(User::factory()->create(['role' => 'manager']))
            ->get(route('invoices.pdf', $invoice))->assertOk();

        // Rendered by headless Chrome, so assert on the HTML the renderer gets.
        $paper = view('invoices.paper', [
            'invoice' => $invoice, 'company' => ['name' => 'EBH'],
            'theme' => ['primary' => '#0B1F3A', 'accent' => '#D4AF37'], 'screen' => true,
        ])->render();

        $this->assertStringContainsString('Payment details', $paper);
        $this->assertStringContainsString('JO06 UBSI 1270 0003 9026 5225 1151 01', $paper);
        $this->assertStringContainsString('UBSIJOAX', $paper);
        $this->assertStringContainsString('USD Account', $paper);

        $this->assertSame('application/pdf', $html->headers->get('content-type'));
    }

    /** The point of putting them in Settings: change once, every invoice follows. */
    public function test_changing_the_bank_in_settings_changes_the_invoice(): void
    {
        $this->house();
        $invoice = $this->invoice();

        $render = fn () => view('invoices.paper', [
            'invoice' => $invoice, 'company' => ['name' => 'EBH'],
            'theme' => ['primary' => '#0B1F3A', 'accent' => '#D4AF37'], 'screen' => true,
        ])->render();

        $this->assertStringContainsString('Bank Al Etihad — Jordan', $render());

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(CompanySettings::class)
            ->set('bank_accounts.0.bank_name', 'Arab Bank — Jordan')
            ->call('save');

        CompanyProfile::forgetHouse();

        $this->assertStringContainsString('Arab Bank — Jordan', $render());
        $this->assertStringNotContainsString('Bank Al Etihad', $render());
    }

    public function test_a_second_account_appears_beside_the_first(): void
    {
        $this->house([
            $this->usd(),
            ['label' => 'JOD Account', 'account_name' => 'Al Sattam', 'bank_name' => 'Bank Al Etihad — Jordan',
                'account_no' => '039016525115101', 'iban' => 'JO44 UBSI 1270 0003 9016 5225 1151 01',
                'swift' => 'UBSIJOAX', 'currency' => 'JOD — Jordanian Dinar'],
        ]);

        $this->assertCount(2, CompanyProfile::bankAccounts());

        $paper = view('invoices.paper', [
            'invoice' => $this->invoice(), 'company' => ['name' => 'EBH'],
            'theme' => ['primary' => '#0B1F3A', 'accent' => '#D4AF37'], 'screen' => true,
        ])->render();

        $this->assertStringContainsString('USD Account', $paper);
        $this->assertStringContainsString('JOD Account', $paper);
    }

    /** A row somebody opened and never filled must not reach a document. */
    public function test_an_empty_row_never_reaches_the_invoice(): void
    {
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(CompanySettings::class)
            ->call('addBankAccount')
            ->set('bank_accounts.0.label', 'A heading and nothing else')
            ->call('save');

        CompanyProfile::forgetHouse();

        $this->assertSame([], CompanyProfile::bankAccounts());

        $paper = view('invoices.paper', [
            'invoice' => $this->invoice(), 'company' => ['name' => 'EBH'],
            'theme' => ['primary' => '#0B1F3A', 'accent' => '#D4AF37'], 'screen' => true,
        ])->render();

        $this->assertStringNotContainsString('Payment details', $paper);
    }

    /** An IBAN is printed in fours, whatever way it was typed in. */
    public function test_the_iban_is_grouped_for_reading(): void
    {
        $unspaced = $this->usd();
        $unspaced['iban'] = 'JO06UBSI1270000390265225115101';
        $this->house([$unspaced]);

        $paper = view('invoices.paper', [
            'invoice' => $this->invoice(), 'company' => ['name' => 'EBH'],
            'theme' => ['primary' => '#0B1F3A', 'accent' => '#D4AF37'], 'screen' => true,
        ])->render();

        $this->assertStringContainsString('JO06 UBSI 1270 0003 9026 5225 1151 01', $paper);

        // …and what was typed is what is stored: normalising input is how a
        // digit gets lost.
        $this->assertSame('JO06UBSI1270000390265225115101', CompanyProfile::bankAccounts()[0]['iban']);
    }

    /** The same block, the same source, on an offer. */
    public function test_a_proposal_carries_the_same_details(): void
    {
        $this->house();

        $proposal = \App\Models\Proposal::create([
            'number' => \App\Models\Proposal::nextNumber(), 'title' => 'An offer',
            'status' => 'draft', 'currency' => 'JOD',
        ]);
        $proposal->lines()->create(['description' => 'Delivery', 'qty' => 1, 'unit_cents' => 5_000_00]);

        $paper = view('proposals.paper', [
            'proposal' => $proposal->fresh()->load('lines'), 'company' => ['name' => 'EBH'],
            'theme' => ['primary' => '#0B1F3A', 'accent' => '#D4AF37'], 'screen' => true,
        ])->render();

        $this->assertStringContainsString('Payment details', $paper);
        $this->assertStringContainsString('JO06 UBSI 1270 0003 9026 5225 1151 01', $paper);
    }

    public function test_settings_keeps_what_was_entered(): void
    {
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(CompanySettings::class)
            ->call('addBankAccount')
            ->set('bank_accounts.0.label', 'USD Account')
            ->set('bank_accounts.0.iban', 'JO06 UBSI 1270 0003 9026 5225 1151 01')
            ->set('bank_accounts.0.swift', 'UBSIJOAX')
            ->call('save')
            ->assertHasNoErrors();

        CompanyProfile::forgetHouse();
        $saved = CompanyProfile::bankAccounts();

        $this->assertCount(1, $saved);
        $this->assertSame('JO06 UBSI 1270 0003 9026 5225 1151 01', $saved[0]['iban']);
        $this->assertSame('UBSIJOAX', $saved[0]['swift']);
    }
}
