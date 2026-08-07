<?php

namespace Tests\Feature;

use App\Livewire\ProposalsDesk;
use App\Models\Client;
use App\Models\Deal;
use App\Models\Event;
use App\Models\EventBudgetItem;
use App\Models\Proposal;
use App\Models\User;
use App\Support\NavPanel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The pipeline had a "proposal" stage with nothing behind it: the stage said an
 * offer had been made and the offer lived in somebody's outbox, so the number a
 * deal was worth was typed twice and the two drifted.
 *
 * The rule that makes this worth building is the last group — accepting an
 * offer wins its deal, and winning is what opens the event, so the figure the
 * client agreed to becomes the event's budget with nobody retyping it.
 */
class ProposalTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function deal(array $attrs = []): Deal
    {
        return Deal::create($attrs + [
            'client_id' => Client::create(['name' => 'World People Assembly'])->id,
            'title' => 'Riyadh Leadership Summit',
            'stage' => 'proposal',
            'type' => 'conference',
            'value_cents' => 120_000_00,
            'currency' => 'JOD',
            'probability' => 55,
            'expected_event_on' => now()->addMonths(6)->toDateString(),
        ]);
    }

    private function desk()
    {
        return Livewire::actingAs($this->actor())->test(ProposalsDesk::class);
    }

    /* ── the offer ── */

    public function test_an_offer_starts_from_the_deal_it_is_trying_to_win(): void
    {
        $deal = $this->deal();

        $p = Proposal::forDeal($deal);

        $this->assertSame($deal->id, $p->deal_id);
        $this->assertSame($deal->client_id, $p->client_id);
        $this->assertSame('draft', $p->status);
        $this->assertSame($deal->title, $p->title);

        // The deal's value becomes the first line, so the offer starts from the
        // conversation rather than from an empty page.
        $this->assertCount(1, $p->lines);
        $this->assertSame(120_000_00, $p->subtotalCents());
        $this->assertStringStartsWith('EBH-PRO-'.now()->format('Y'), $p->number);
    }

    /**
     * An optional extra is quoted so the client can say yes to it, and left out
     * of the total so the headline price is the one under discussion.
     */
    public function test_optional_lines_are_quoted_but_not_counted(): void
    {
        $p = Proposal::forDeal($this->deal());
        $p->lines()->create(['description' => 'Gala dinner', 'qty' => 1, 'unit_cents' => 25_000_00, 'optional' => true]);
        $p->load('lines');

        $this->assertSame(120_000_00, $p->subtotalCents(), 'the headline price is unchanged');
        $this->assertSame(25_000_00, $p->optionalCents());
        $this->assertSame(120_000_00, $p->totalCents());
    }

    public function test_tax_sits_on_the_counted_lines_only(): void
    {
        $p = Proposal::forDeal($this->deal());
        $p->lines()->create(['description' => 'Extra', 'qty' => 1, 'unit_cents' => 50_000_00, 'optional' => true]);
        $p->update(['tax_pct' => 16]);
        $p = $p->fresh()->load('lines');

        $this->assertSame((int) round(120_000_00 * 0.16), $p->taxCents());
        $this->assertSame(120_000_00 + (int) round(120_000_00 * 0.16), $p->totalCents());
    }

    /**
     * A date passing is not an act, and an offer nobody sent cannot lapse.
     */
    public function test_expiry_is_derived_and_only_applies_to_an_offer_that_went_out(): void
    {
        $p = Proposal::forDeal($this->deal());
        $p->update(['valid_until' => now()->subWeek()]);

        $this->assertSame('draft', $p->fresh()->state(), 'a draft past its date was never made');

        $p->update(['status' => 'sent']);
        $this->assertSame('expired', $p->fresh()->state());
        $this->assertLessThan(0, $p->fresh()->daysLeft());

        $p->update(['valid_until' => now()->addDays(10)]);
        $this->assertSame('sent', $p->fresh()->state());
        $this->assertTrue($p->fresh()->isLive());

        // A decision outranks the arithmetic.
        $p->update(['status' => 'declined', 'valid_until' => now()->subWeek()]);
        $this->assertSame('declined', $p->fresh()->state());
    }

    /* ── accepting is what opens the event ── */

    public function test_accepting_wins_the_deal_and_opens_the_event_at_the_agreed_figure(): void
    {
        $deal = $this->deal();
        $p = Proposal::forDeal($deal);

        // Priced up during the conversation, and 16% tax added.
        $p->lines()->first()->update(['unit_cents' => 139_200_00]);
        $p->update(['status' => 'sent']);
        $p = $p->fresh()->load(['lines', 'deal']);

        $event = $p->accept();

        $deal->refresh();
        $p->refresh();

        $this->assertSame('won', $deal->stage);
        $this->assertSame(139_200_00, $deal->value_cents, 'agreed beats estimated');

        $this->assertInstanceOf(Event::class, $event);
        $this->assertSame($deal->title, $event->name);
        $this->assertSame(139_200_00, $event->budget_cents, 'the event is budgeted at what was agreed');
        $this->assertSame($event->id, $deal->event_id);
        $this->assertSame($event->id, $p->event_id, 'and the offer knows what it became');
        $this->assertSame('accepted', $p->state());
    }

    /**
     * The point of the whole chain: a PM opening a freshly-won event should
     * find the Budget tab already priced from what the client agreed to, not
     * an empty grid waiting for someone to retype the proposal by hand.
     */
    public function test_accepting_seeds_the_budget_from_the_priced_lines(): void
    {
        $deal = $this->deal(['value_cents' => 0]);
        $p = Proposal::forDeal($deal); // no lines yet — value_cents was 0

        $p->lines()->create(['description' => 'Venue hire', 'detail' => '3 days', 'qty' => 1, 'unit_cents' => 40_000_00, 'sort' => 1]);
        $p->lines()->create(['description' => 'AV production', 'qty' => 3, 'unit_cents' => 5_000_00, 'sort' => 2]);
        $p->lines()->create(['description' => 'Gala dinner', 'qty' => 1, 'unit_cents' => 25_000_00, 'optional' => true, 'sort' => 3]);
        $p->update(['status' => 'sent']);

        $event = $p->fresh()->load(['lines', 'deal'])->accept();

        $items = EventBudgetItem::where('event_id', $event->id)->where('source_type', 'proposal')->get();
        $this->assertCount(2, $items, 'the optional line was quoted, not agreed to, so it seeds nothing');

        $venue = $items->firstWhere('description', 'Venue hire — 3 days');
        $this->assertNotNull($venue);
        $this->assertSame(40_000_00, $venue->sell_cents, 'the sell side comes from the proposal');
        $this->assertSame(0, $venue->estimated_cents, 'the cost side is not something a proposal ever knew');
        $this->assertSame('Proposal Pricing', $venue->category);

        $av = $items->firstWhere('description', 'AV production');
        $this->assertSame(3, $av->quantity);
        $this->assertSame(15_000_00, $av->sell_cents);
    }

    /** Accepting twice must not double the budget lines either. */
    public function test_accepting_twice_does_not_duplicate_budget_lines(): void
    {
        $p = Proposal::forDeal($this->deal());
        $p->update(['status' => 'sent']);

        $p->fresh()->load(['lines', 'deal'])->accept();
        $event = $p->fresh()->load(['lines', 'deal'])->accept();

        $this->assertSame(
            1,
            EventBudgetItem::where('event_id', $event->id)->where('source_type', 'proposal')->count()
        );
    }

    /** Accepting twice must not open a second event. */
    public function test_accepting_is_idempotent(): void
    {
        $p = Proposal::forDeal($this->deal());
        $p->update(['status' => 'sent']);

        $first = $p->fresh()->load(['lines', 'deal'])->accept();
        $again = $p->fresh()->load(['lines', 'deal'])->accept();

        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, Event::count());
    }

    public function test_declining_records_the_reason_and_opens_nothing(): void
    {
        $p = Proposal::forDeal($this->deal());
        $p->update(['status' => 'sent']);

        $p->decline('Went with an in-house team');

        $p->refresh();
        $this->assertSame('declined', $p->state());
        $this->assertSame('Went with an in-house team', $p->decline_reason);
        $this->assertNotNull($p->decided_on);
        $this->assertSame(0, Event::count());
    }

    /* ── the desk ── */

    public function test_the_desk_renders_and_offers_the_deals_with_nothing_sent(): void
    {
        $deal = $this->deal();

        $this->actingAs($this->actor())->get(route('proposals.index'))->assertOk()
            ->assertSee('Proposals')
            ->assertSee('waiting for an offer');

        $ready = $this->desk()->viewData('ready');
        $this->assertContains($deal->id, $ready->pluck('id'));
    }

    public function test_drafting_from_a_deal_clears_it_from_the_waiting_list(): void
    {
        $deal = $this->deal();
        $c = $this->desk();

        // Drafting redirects into the editor, so the assertion about the
        // waiting list belongs to a fresh page rather than the one that left.
        $c->call('draftFor', $deal->id)->assertRedirect();

        $this->assertSame(1, Proposal::count());
        $this->assertNotContains($deal->id, $this->desk()->viewData('ready')->pluck('id'));
    }

    /**
     * One live offer per deal: a superseded offer is declined or expired first,
     * which keeps the history rather than quietly stacking two.
     */
    public function test_a_deal_cannot_carry_two_live_offers(): void
    {
        $deal = $this->deal();
        $c = $this->desk();

        $c->call('draftFor', $deal->id)->call('draftFor', $deal->id);
        $this->assertSame(1, Proposal::count());

        // Once the first is declined, a fresh offer can go out.
        Proposal::first()->decline('Repriced');
        $c->call('draftFor', $deal->id);
        $this->assertSame(2, Proposal::count());
    }

    public function test_an_expired_offer_can_be_put_back_on_the_table(): void
    {
        $deal = $this->deal();
        $p = Proposal::forDeal($deal);
        $p->update(['status' => 'sent', 'valid_until' => now()->subWeek()]);
        $this->assertSame('expired', $p->fresh()->state());

        $this->desk()->call('extend', $p->id);

        $this->assertSame('sent', $p->fresh()->state());
        $this->assertGreaterThan(20, $p->fresh()->daysLeft());
    }

    /** Only a draft goes. An offer that has been out stays in the book. */
    public function test_only_a_draft_can_be_deleted(): void
    {
        $p = Proposal::forDeal($this->deal());
        $c = $this->desk();

        $p->update(['status' => 'sent']);
        $c->call('destroyDraft', $p->id);
        $this->assertDatabaseHas('proposals', ['id' => $p->id]);

        $p->update(['status' => 'draft']);
        $c->call('destroyDraft', $p->id);
        $this->assertDatabaseMissing('proposals', ['id' => $p->id]);
    }

    public function test_only_writers_may_draft_or_accept(): void
    {
        $deal = $this->deal();
        $viewer = User::create(['name' => 'Vic Viewer', 'email' => 'viewer@ebh.test',
            'password' => bcrypt('x'), 'role' => 'viewer']);

        Livewire::actingAs($viewer)->test(ProposalsDesk::class)
            ->call('draftFor', $deal->id)->assertForbidden();

        $this->assertSame(0, Proposal::count());
    }

    /** The win rate counts offers that got an answer, not offers still out. */
    public function test_the_win_rate_ignores_offers_still_awaiting_an_answer(): void
    {
        Proposal::forDeal($this->deal())->update(['status' => 'accepted']);
        Proposal::forDeal($this->deal())->update(['status' => 'declined']);
        Proposal::forDeal($this->deal())->update(['status' => 'sent']);   // still out

        $rate = collect($this->desk()->viewData('figures'))->firstWhere('label', 'Win rate');

        $this->assertSame('50%', $rate['value'], 'one of the two that were decided');
        $this->assertStringContainsString('2 decided', $rate['note']);
    }

    public function test_the_pdf_downloads(): void
    {
        $p = Proposal::forDeal($this->deal());

        $res = $this->actingAs($this->actor())->get(route('proposals.pdf', $p))->assertOk();

        $this->assertSame('application/pdf', $res->headers->get('content-type'));
        $this->assertStringContainsString($p->number, $res->headers->get('content-disposition'));
    }

    public function test_the_nav_links_to_it_now_that_it_exists(): void
    {
        $panel = collect(NavPanel::panel())
            ->flatMap(fn ($s) => $s['items'])
            ->firstWhere('label', 'Proposals');

        $this->assertSame(route('proposals.index'), $panel['href']);
    }
}
