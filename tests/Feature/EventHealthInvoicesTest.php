<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Invoice;
use App\Services\EventHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventHealthInvoicesTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        return Event::factory()->create([
            'stage' => 'planning',
            'budget_cents' => 50_000_00,
        ]);
    }

    private function overdueInvoice(Event $event, int $total, int $paid = 0): Invoice
    {
        $invoice = Invoice::create([
            'number' => Invoice::nextNumber(),
            'status' => 'sent',
            'event_id' => $event->id,
            'tax_pct' => 0,
            'fee_pct' => 0,
            'due_on' => now()->subWeek()->toDateString(),
            'paid_cents' => $paid,
            'paid_at' => $paid > 0 ? now()->toDateString() : null,
        ]);

        $invoice->lines()->create([
            'description' => 'Overdue work',
            'qty' => 1,
            'unit_cents' => $total,
        ]);

        return $invoice->fresh()->load('lines.payment');
    }

    public function test_relations_eager_load_invoice_lines_and_payments(): void
    {
        $this->assertContains('invoices.lines.payment', EventHealthService::RELATIONS);
    }

    public function test_an_overdue_invoice_pulls_the_budget_score_down(): void
    {
        $event = $this->event();
        $service = app(EventHealthService::class);

        // A clean event with a cap and no spend has nothing to judge yet.
        $this->assertNull($service->breakdown($event->fresh()->load(EventHealthService::RELATIONS))['components']['budget']);

        $this->overdueInvoice($event, 20_000_00);

        $score = $service->breakdown($event->fresh()->load(EventHealthService::RELATIONS))['components']['budget'];

        $this->assertNotNull($score);
        $this->assertLessThan(50, $score, 'JD20k overdue against a JD50k cap is not a healthy budget');
    }

    public function test_a_paid_invoice_past_its_due_date_does_not_hurt_the_score(): void
    {
        $event = $this->event();
        $this->overdueInvoice($event, 20_000_00, 20_000_00);

        $score = app(EventHealthService::class)
            ->breakdown($event->fresh()->load(EventHealthService::RELATIONS))['components']['budget'];

        $this->assertNull($score, 'settled invoices are not a collection problem');
    }

    public function test_overdue_invoices_appear_in_the_advisor(): void
    {
        $event = $this->event();
        $this->overdueInvoice($event, 12_000_00);

        $attention = implode(' | ', app(EventHealthService::class)
            ->aiSummary($event->fresh()->load(EventHealthService::RELATIONS))['attention']);

        $this->assertStringContainsString('overdue invoice', $attention);
        $this->assertStringContainsString('still out', $attention);
    }

    public function test_scoring_a_loaded_set_does_not_re_query_invoices(): void
    {
        $events = Event::factory()->count(3)->create(['stage' => 'planning', 'budget_cents' => 40_000_00]);

        foreach ($events as $event) {
            $this->overdueInvoice($event, 5_000_00);
        }

        $loaded = Event::query()->whereIn('id', $events->pluck('id'))
            ->with(EventHealthService::RELATIONS)
            ->get();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $service = app(EventHealthService::class);
        foreach ($loaded as $event) {
            $service->breakdown($event);
            $service->aiSummary($event);
        }

        $invoiceQueries = collect(DB::getQueryLog())
            ->filter(fn (array $q) => str_contains(strtolower($q['query']), 'invoices'));

        $this->assertTrue($invoiceQueries->isEmpty(), 'invoices must come from the eager load, not per-event queries');
    }
}
