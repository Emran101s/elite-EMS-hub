<?php

namespace Tests\Feature;

use App\Livewire\ProposalEditor;
use App\Livewire\ProposalsDesk;
use App\Models\Client;
use App\Models\CompanyProfile;
use App\Models\Deal;
use App\Models\Proposal;
use App\Models\ServiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The desk could start a proposal from a deal and then had nothing to say: the
 * only line was the one carried over from the deal's value, and there was no
 * way to add a second, mark one optional, or price any of it from the list of
 * what the company actually sells.
 */
class ProposalEditorTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function house(array $attrs): CompanyProfile
    {
        $h = CompanyProfile::firstOrNew([]);
        $h->fill($attrs + ['name' => $h->name ?: 'Elite Business Hub'])->save();
        CompanyProfile::forgetHouse();

        return $h;
    }

    private function deal(): Deal
    {
        return Deal::create([
            'client_id' => Client::create(['name' => 'World People Assembly'])->id,
            'title' => 'Riyadh Leadership Summit',
            'stage' => 'proposal', 'type' => 'conference',
            'value_cents' => 120_000_00, 'currency' => 'JOD', 'probability' => 55,
        ]);
    }

    private function room(): ServiceItem
    {
        return ServiceItem::create([
            'code' => 'ACC-DBL', 'name' => 'Double room, 5★', 'category' => 'Accommodation',
            'unit' => 'room_night', 'unit_price_cents' => 95_00, 'currency' => 'JOD',
        ]);
    }

    private function editor(Proposal $p, ?User $u = null)
    {
        return Livewire::actingAs($u ?? $this->admin())->test(ProposalEditor::class, ['proposal' => $p]);
    }

    /* ── getting there ── */

    public function test_drafting_from_a_deal_opens_the_editor(): void
    {
        $deal = $this->deal();

        Livewire::actingAs($this->admin())->test(ProposalsDesk::class)
            ->call('draftFor', $deal->id)
            ->assertRedirect(route('proposals.edit', Proposal::latest('id')->first()));
    }

    public function test_the_editor_renders_with_the_document_beside_it(): void
    {
        $p = Proposal::forDeal($this->deal());

        $this->actingAs($this->admin())->get(route('proposals.edit', $p))->assertOk()
            ->assertSee($p->number)
            ->assertSee('What the client receives');
    }

    /* ── lines ── */

    public function test_a_line_can_be_added_edited_reordered_and_removed(): void
    {
        $p = Proposal::forDeal($this->deal());
        $was = $p->lines->count();
        $c = $this->editor($p);

        $c->call('newLine')
            ->set('description', 'Hybrid streaming')
            ->set('detail', 'Two cameras and an operator.')
            ->set('qty', '3')
            ->set('unit', '950')
            ->call('saveLine');

        $p->refresh()->load('lines');
        $this->assertCount($was + 1, $p->lines);

        $line = $p->lines->last();
        $this->assertSame(285_000, $line->amountCents());
        $this->assertSame('Two cameras and an operator.', $line->detail);

        $c->call('editLine', $line->id)->set('unit', '1000')->call('saveLine');
        $this->assertSame(100_000, $line->fresh()->unit_cents);

        $c->call('moveLine', $line->id, -1);
        $this->assertSame($line->id, $p->fresh()->load('lines')->lines->first()->id);

        $c->call('deleteLine', $line->id);
        $this->assertCount($was, $p->fresh()->lines);
    }

    /**
     * Optional means quoted and not counted — in the offer so the client can
     * say yes, out of the total so the headline price is the one being agreed.
     */
    public function test_a_line_can_be_made_optional_and_leaves_the_total(): void
    {
        $p = Proposal::forDeal($this->deal());
        $c = $this->editor($p);

        $c->call('newLine')->set('description', 'Gala dinner')->set('qty', '1')
            ->set('unit', '25000')->set('optional', true)->call('saveLine');

        $p->refresh()->load('lines');
        $this->assertSame(120_000_00, $p->subtotalCents(), 'the headline price is unchanged');
        $this->assertSame(25_000_00, $p->optionalCents());

        // …and it can be counted back in without opening the line.
        $optional = $p->lines->firstWhere('optional', true);
        $c->call('toggleOptional', $optional->id);

        $this->assertSame(145_000_00, $p->fresh()->load('lines')->subtotalCents());
    }

    /* ── the price list ── */

    public function test_a_line_can_be_priced_from_the_catalogue(): void
    {
        $item = $this->room();
        $p = Proposal::forDeal($this->deal());

        $this->editor($p)->call('pick', $item->id)->set('factors', [12, 3])->call('saveLine');

        $line = $p->fresh()->load('lines')->lines->last();

        $this->assertSame(36.0, $line->qty, '12 rooms × 3 nights');
        $this->assertSame(95_00, $line->unit_cents);
        $this->assertSame('Double room, 5★ — 12 rooms × 3 nights', $line->description);
    }

    public function test_a_retired_item_is_not_offered(): void
    {
        $item = $this->room();
        $p = Proposal::forDeal($this->deal());
        $c = $this->editor($p)->call('newLine');

        $this->assertContains($item->id, $c->viewData('catalogue')->pluck('id'));

        $item->update(['active' => false]);
        $this->assertNotContains($item->id, $c->call('newLine')->viewData('catalogue')->pluck('id'));
    }

    /* ── the fee ── */

    /**
     * A deal's value is already a whole-job figure. A fee on top of it inflates
     * the quote by the fee, the same way charging one on a contract installment
     * bills it twice — so it is offered, never assumed.
     */
    public function test_an_offer_from_a_deal_starts_with_no_fee(): void
    {
        $this->house(['default_management_fee_pct' => 15]);

        $p = Proposal::forDeal($this->deal());

        $this->assertSame(0.0, (float) $p->fee_pct);
        $this->assertSame($p->subtotalCents(), $p->netCents());
    }

    public function test_the_house_fee_is_one_click_away(): void
    {
        $this->house(['default_management_fee_pct' => 15]);
        $p = Proposal::forDeal($this->deal());

        $this->editor($p)->call('applyHouseFee');

        $p->refresh()->load('lines');
        $this->assertSame(15.0, (float) $p->fee_pct);
        $this->assertSame(18_000_00, $p->feeCents(), '15% of 120,000');
        $this->assertSame(138_000_00, $p->netCents());
    }

    public function test_the_fee_is_taxed_with_the_work_and_skips_the_optional(): void
    {
        $p = Proposal::forDeal($this->deal());
        $p->lines()->create(['description' => 'Extra', 'qty' => 1, 'unit_cents' => 50_000_00, 'optional' => true]);
        $p->update(['fee_pct' => 15, 'tax_pct' => 16]);
        $p = $p->fresh()->load('lines');

        $this->assertSame(18_000_00, $p->feeCents(), 'the optional line is outside the fee');
        $this->assertSame(138_000_00, $p->netCents());
        $this->assertSame((int) round(138_000_00 * 0.16), $p->taxCents(), 'tax on the net, fee included');
        $this->assertSame(138_000_00 + (int) round(138_000_00 * 0.16), $p->totalCents());
    }

    public function test_the_fee_reaches_the_document(): void
    {
        $p = Proposal::forDeal($this->deal());
        $p->update(['fee_pct' => 15]);

        $this->actingAs($this->admin())->get(route('proposals.edit', $p))->assertOk()
            ->assertSee('Management fee (15%)');
    }

    /* ── the life of the offer ── */

    public function test_the_editor_can_send_accept_and_decline(): void
    {
        $p = Proposal::forDeal($this->deal());
        $c = $this->editor($p);

        $c->call('send');
        $this->assertSame('sent', $p->fresh()->status);

        $c->call('accept');
        $p->refresh();
        $this->assertSame('accepted', $p->state());
        $this->assertNotNull($p->event_id, 'accepting opened the event');
    }

    public function test_declining_from_the_editor_records_the_reason(): void
    {
        $p = Proposal::forDeal($this->deal());
        $p->update(['status' => 'sent']);

        $this->editor($p->fresh()->load('lines'))
            ->set('reason', 'Went in-house')->call('decline');

        $this->assertSame('declined', $p->fresh()->state());
        $this->assertSame('Went in-house', $p->fresh()->decline_reason);
    }

    public function test_only_a_draft_is_deleted_and_it_returns_to_the_desk(): void
    {
        $p = Proposal::forDeal($this->deal());

        $this->editor($p)->call('destroyDraft')->assertRedirect(route('proposals.index'));
        $this->assertDatabaseMissing('proposals', ['id' => $p->id]);
    }

    public function test_only_writers_may_edit(): void
    {
        $p = Proposal::forDeal($this->deal());
        $viewer = User::create(['name' => 'Vic Viewer', 'email' => 'viewer@ebh.test',
            'password' => bcrypt('x'), 'role' => 'viewer']);

        $this->editor($p, $viewer)->call('newLine')->assertForbidden();
    }

    /** The PDF route must not be swallowed by the editor's {proposal}. */
    public function test_the_pdf_and_the_editor_are_different_pages(): void
    {
        $user = $this->admin();
        $p = Proposal::forDeal($this->deal());

        $pdf = $this->actingAs($user)->get(route('proposals.pdf', $p))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));

        $editor = $this->actingAs($user)->get(route('proposals.edit', $p))->assertOk();
        $this->assertStringContainsString('text/html', $editor->headers->get('content-type'));
    }
}
