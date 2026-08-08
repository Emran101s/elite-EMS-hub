<?php

namespace Tests\Feature;

use App\Livewire\CrmPipeline;
use App\Models\Client;
use App\Models\Deal;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The handoff between the two halves of the sale.
 *
 * A deal reaching Proposal with nothing sent, and a deal marked won with
 * nothing agreed, were both only visible from the Proposals desk — a screen
 * the person having the conversation was not on. docs/18 §5 counted both as
 * critical, so the board carries the door and the warning now.
 */
class CrmProposalHandoffTest extends TestCase
{
    use RefreshDatabase;

    private function deal(array $attributes = []): Deal
    {
        $client = Client::create(['name' => 'World People Assembly']);

        return Deal::create(array_merge([
            'client_id' => $client->id,
            'title' => 'Regional Summit 2027',
            'stage' => 'negotiation',
            'type' => 'summit',
            'value_cents' => 4_500_000,
            'currency' => 'JOD',
            'probability' => 75,
        ], $attributes));
    }

    public function test_the_board_drafts_the_offer_and_lands_in_the_editor(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        $deal = $this->deal();

        Livewire::actingAs($user)->test(CrmPipeline::class)
            ->call('draftProposal', $deal->id)
            ->assertRedirect(route('proposals.edit', Proposal::firstOrFail()));

        $proposal = Proposal::with('lines')->firstOrFail();
        $this->assertSame($deal->id, $proposal->deal_id);
        $this->assertSame('draft', $proposal->status);
        $this->assertSame(4_500_000, $proposal->lines->first()?->unit_cents,
            'the figure already discussed starts the offer, rather than an empty page');
    }

    public function test_drafting_twice_opens_the_offer_already_on_the_table(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        $deal = $this->deal();

        $component = Livewire::actingAs($user)->test(CrmPipeline::class);
        $component->call('draftProposal', $deal->id);
        $first = Proposal::firstOrFail();

        $component->call('draftProposal', $deal->id)
            ->assertRedirect(route('proposals.edit', $first));

        $this->assertSame(1, Proposal::count(), 'one live offer per deal, not two');
    }

    public function test_the_inspector_offers_the_door_while_nothing_is_out(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        $deal = $this->deal();

        Livewire::actingAs($user)->test(CrmPipeline::class)
            ->call('select', $deal->id)
            ->assertSee('Draft proposal')
            ->assertSee('Winning now opens the event with an empty budget.');
    }

    public function test_the_inspector_shows_the_offer_once_there_is_one(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        $deal = $this->deal();
        $proposal = Proposal::forDeal($deal);

        Livewire::actingAs($user)->test(CrmPipeline::class)
            ->call('select', $deal->id)
            ->assertSee($proposal->number)
            ->assertDontSee('Draft proposal');
    }

    public function test_winning_without_an_agreed_figure_asks_first(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        $this->deal();

        Livewire::actingAs($user)->test(CrmPipeline::class)
            ->assertSee('Win without an agreed figure?');
    }

    /**
     * A sent offer is not an agreed one.
     *
     * The gate reads the accepted document, not the presence of paperwork —
     * a quote the client has gone quiet on is exactly the case worth warning
     * about.
     */
    public function test_an_offer_that_is_only_sent_still_warns(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        $deal = $this->deal();
        Proposal::forDeal($deal)->update(['status' => 'sent']);

        Livewire::actingAs($user)->test(CrmPipeline::class)
            ->assertSee('Win without an agreed figure?');
    }

    public function test_an_accepted_offer_wins_without_the_warning(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        $deal = $this->deal();
        Proposal::forDeal($deal)->update(['status' => 'accepted']);

        Livewire::actingAs($user)->test(CrmPipeline::class)
            ->assertDontSee('Win without an agreed figure?')
            ->assertSee('Mark won');
    }

    public function test_the_warning_does_not_block_the_win(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        $deal = $this->deal();

        Livewire::actingAs($user)->test(CrmPipeline::class)
            ->call('moveTo', $deal->id, 'won');

        $this->assertSame('won', $deal->fresh()->stage,
            'the gate is a warning on the way through, not a lock');
        $this->assertNotNull($deal->fresh()->event_id);
    }

    public function test_a_viewer_cannot_draft_an_offer(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $deal = $this->deal();

        Livewire::actingAs($viewer)->test(CrmPipeline::class)
            ->call('draftProposal', $deal->id)
            ->assertForbidden();

        $this->assertSame(0, Proposal::count());
    }
}
