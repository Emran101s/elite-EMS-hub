<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventContract;
use App\Models\User;
use App\Support\ContractClauses;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The project-value clause used to freeze whatever the figure was the moment
 * the document was generated — usually 0, since nothing had been priced yet.
 * It now quotes {{value}}, resolved live from Value & Payments every time the
 * document is rendered.
 */
class ContractLiveValueTest extends TestCase
{
    use RefreshDatabase;

    public function test_livevalue_formats_english_with_currency_and_words(): void
    {
        $out = ContractClauses::liveValue([
            'currency' => 'USD',
            'financials' => ['contract_value_cents' => 125000],
        ], 'en');

        $this->assertSame('USD 1,250.00 (One Thousand Two Hundred Fifty US Dollars Only)', $out);
    }

    public function test_livevalue_formats_arabic_with_arabic_currency_name(): void
    {
        $out = ContractClauses::liveValue([
            'currency' => 'JOD',
            'financials' => ['contract_value_cents' => 5107150],
        ], 'ar');

        $this->assertStringContainsString('51,071.50', $out);
        $this->assertStringContainsString('ديناراً أردنياً', $out);
    }

    public function test_resolvevalue_only_touches_text_that_contains_the_token(): void
    {
        $d = ['currency' => 'USD', 'financials' => ['contract_value_cents' => 100000]];

        $this->assertSame(
            'Untouched clause text.',
            ContractClauses::resolveValue('Untouched clause text.', $d, 'en'),
        );
        $this->assertSame(
            'The value is USD 1,000.00 (One Thousand US Dollars Only).',
            ContractClauses::resolveValue('The value is {{value}}.', $d, 'en'),
        );
    }

    public function test_a_freshly_generated_clause_carries_the_token_not_a_frozen_figure(): void
    {
        $clauses = ContractClauses::clauses([
            'currency' => 'USD',
            'financials' => ['value_mode' => 'estimate', 'contract_value_cents' => 0],
        ]);

        $value = collect($clauses)->firstWhere('en_title', 'Estimated Project Value');
        $this->assertStringContainsString('{{value}}', $value['en'][0]);
        $this->assertStringNotContainsString('0.00', $value['en'][0]);
    }

    public function test_a_new_client_contract_seeds_from_the_budget_forecast_not_the_unset_cap(): void
    {
        // budget_cents is a manually-typed cap, usually left at 0 — the seed
        // has to come from the priced lines (sell price, fee included), which
        // is exactly what "From budget" pulls in later. See Event::costForecast().
        $event = Event::factory()->create(['budget_cents' => 0, 'currency' => 'USD', 'management_fee_pct' => 15]);
        $event->budgetItems()->create([
            'category' => 'Venues', 'description' => 'Hall', 'estimated_cents' => 250000, 'quantity' => 1,
        ]);

        $contract = EventContract::createFor($event, 'client');

        $this->assertSame(287500, $contract->data['financials']['contract_value_cents']);
    }

    public function test_the_live_preview_shows_the_current_value_and_payments_figure(): void
    {
        $this->seed(DemoDataSeeder::class);
        $event = Event::query()->firstOrFail();
        $contract = EventContract::createFor($event, 'client');
        $user = User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();

        // First open freezes the blocks into storage — exactly what happens the
        // first time somebody opens the editor and it autosaves.
        $contract->update(['data' => [...$contract->data, 'blocks' => $contract->blocks()]]);

        $data = $contract->data;
        $data['financials']['contract_value_cents'] = 6000000;
        $data['financials']['estimated_total_cents'] = 6000000;
        $contract->update(['data' => $data]);

        $res = $this->actingAs($user)->get(route('events.hub', [$event, 'tab' => 'contract', 'contract' => $contract->id]));
        $res->assertOk()->assertSee('60,000.00', false);
    }

    /**
     * The retrofit migration: an existing document, generated before {{value}}
     * existed, has the old figure baked into its stored blocks. It should read
     * live after the fix without anyone touching the document.
     */
    public function test_an_already_generated_contract_reads_live_after_the_retrofit(): void
    {
        $event = Event::factory()->create(['currency' => 'JOD']);
        $contract = EventContract::createFor($event, 'client');
        $data = $contract->data;
        // An "estimate"-mode document is the one whose title reads "Estimated
        // Project Value" — the case this whole fix is about.
        $data['financials']['value_mode'] = 'estimate';
        $contract->update(['data' => $data]);
        $contract->update(['data' => [...$contract->fresh()->data, 'blocks' => $contract->fresh()->blocks()]]);

        // Simulate a contract generated before {{value}} existed: the frozen
        // number from whatever the estimate was at creation (here, zero).
        $data = $contract->fresh()->data;
        foreach ($data['blocks'] as $i => $b) {
            if (($b['title_en'] ?? '') === 'Estimated Project Value') {
                $data['blocks'][$i]['en'][0] = 'The Parties agree that the current estimated value of the Project is JOD 0.00 (Zero Jordanian Dinars Only).';
                $data['blocks'][$i]['ar'][0] = 'يتفق الطرفان على أن القيمة التقديرية الحالية للمشروع هي 0.00 ديناراً أردنياً (صفر ديناراً أردنياً لا غير).';
            }
        }
        $data['financials']['contract_value_cents'] = 5107150;
        $data['financials']['estimated_total_cents'] = 5107150;
        $contract->update(['data' => $data]);

        // The migration already ran during RefreshDatabase's setup, against an
        // empty table — invoke its retrofit directly, the way a real one-off
        // run touches whatever rows exist at the time.
        (require base_path('database/migrations/2026_08_03_121000_make_contract_value_clause_live.php'))->up();

        $block = collect($contract->fresh()->blocks())->firstWhere('title_en', 'Estimated Project Value');
        $this->assertNotNull($block);
        $this->assertStringContainsString('{{value}}', $block['en'][0]);
        $this->assertStringContainsString('{{value}}', $block['ar'][0]);
    }
}
