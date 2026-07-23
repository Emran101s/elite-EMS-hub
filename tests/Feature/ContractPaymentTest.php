<?php

namespace Tests\Feature;

use App\Livewire\Hub\ContractTab;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventContract;
use App\Models\User;
use App\Services\CommandCenterService;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContractPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(): array
    {
        $this->seed(DemoDataSeeder::class);
        $event = Event::where('name', 'ICFT 2026')->firstOrFail();
        $contract = EventContract::forEvent($event);
        $contract->ensurePayments();

        return [$event, $contract, User::where('email', 'emran.itan@elitebhub.com')->firstOrFail()];
    }

    public function test_the_schedule_becomes_trackable_installments(): void
    {
        [$event, $contract] = $this->ctx();
        $payments = $contract->payments;

        // 30/30/20/20 → four rows that add up to the estimate, to the cent.
        $this->assertCount(4, $payments);
        $this->assertSame([30.0, 30.0, 20.0, 20.0], $payments->pluck('pct')->all());
        $this->assertSame(
            $contract->data['financials']['estimated_total_cents'],
            $payments->sum('amount_cents')
        );

        // Due dates follow the shape: signing = today, then 60/30/7 days out.
        $this->assertTrue($payments[0]->due_on->isToday());
        $this->assertSame($event->starts_at->copy()->subDays(60)->toDateString(), $payments[1]->due_on->toDateString());
        $this->assertSame($event->starts_at->copy()->subDays(7)->toDateString(), $payments[3]->due_on->toDateString());

        // Idempotent — money survives a re-run.
        $contract->ensurePayments();
        $this->assertSame(4, $contract->payments()->count());
    }

    public function test_recording_money_moves_the_status(): void
    {
        [$event, $contract, $user] = $this->ctx();
        $first = $contract->payments()->orderBy('sort')->first();

        // Partial payment.
        Livewire::actingAs($user)->test(ContractTab::class, ['event' => $event])
            ->call('selectContract', $contract->id)
            ->call('recordPayment', $first->id, $first->amount_cents / 100 / 2);
        $this->assertSame('partial', $first->fresh()->status());

        // Blank amount settles the remainder in full.
        Livewire::actingAs($user)->test(ContractTab::class, ['event' => $event])
            ->call('selectContract', $contract->id)
            ->call('recordPayment', $first->id);
        $first->refresh();
        $this->assertSame('paid', $first->status());
        $this->assertSame($first->amount_cents, $first->paid_cents);

        // Undo (bounced transfer) — back to pending.
        Livewire::actingAs($user)->test(ContractTab::class, ['event' => $event])
            ->call('selectContract', $contract->id)
            ->call('clearPayment', $first->id);
        $this->assertSame(0, $first->fresh()->paid_cents);
    }

    public function test_a_missed_due_date_reads_overdue_and_alerts_the_command_center(): void
    {
        [$event, $contract, $user] = $this->ctx();
        $p = $contract->payments()->orderBy('sort')->first();
        $this->actingAs($user);
        $p->update(['due_on' => now()->subWeek()->toDateString()]);

        $this->assertSame('overdue', $p->fresh()->status());

        $alerts = app(CommandCenterService::class)->alerts()->pluck('title')->implode(' | ');
        $this->assertStringContainsString('installment overdue', $alerts);

        // Paying it clears both the status and the alert.
        $p->update(['paid_cents' => $p->amount_cents, 'paid_at' => now()->toDateString()]);
        $this->assertSame('paid', $p->fresh()->status());
        $this->assertStringNotContainsString(
            $event->name.' — installment overdue',
            app(CommandCenterService::class)->alerts()->pluck('title')->implode(' | ')
        );
    }

    public function test_repricing_respects_money_already_received(): void
    {
        [$event, $contract, $user] = $this->ctx();
        [$paid, $open] = $contract->payments()->orderBy('sort')->limit(2)->get();
        $this->actingAs($user);
        $paid->update(['paid_cents' => $paid->amount_cents, 'paid_at' => now()->toDateString()]);
        $originalPaidAmount = $paid->amount_cents;

        // The estimate doubles.
        $data = $contract->data;
        $data['financials']['estimated_total_cents'] *= 2;
        $contract->update(['data' => $data]);
        $contract->repriceUnpaidPayments();

        // Cash received is history; open installments re-price.
        $this->assertSame($originalPaidAmount, $paid->fresh()->amount_cents);
        $this->assertSame(
            (int) round($data['financials']['estimated_total_cents'] * 0.30),
            $open->fresh()->amount_cents
        );
    }

    public function test_recording_a_payment_is_gated_and_audited(): void
    {
        [$event, $contract] = $this->ctx();
        $p = $contract->payments()->orderBy('sort')->first();
        $coordinator = User::where('role', 'coordinator')->firstOrFail();

        // Coordinators can't touch client money.
        Livewire::actingAs($coordinator)->test(ContractTab::class, ['event' => $event])
            ->call('selectContract', $contract->id)
            ->call('recordPayment', $p->id)
            ->assertForbidden();

        // A manager can — and the movement lands in the audit trail.
        $manager = User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
        Livewire::actingAs($manager)->test(ContractTab::class, ['event' => $event])
            ->call('selectContract', $contract->id)
            ->call('recordPayment', $p->id);

        $log = AuditLog::where('auditable_type', 'EventContractPayment')->latest('id')->firstOrFail();
        $this->assertSame($manager->id, $log->user_id);
        $this->assertArrayHasKey('paid_cents', $log->changes);
    }
}
