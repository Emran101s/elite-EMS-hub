<?php

namespace Tests\Feature;

use App\Livewire\ExhibitionFloorPlan;
use App\Livewire\Hub\ApprovalsTab;
use App\Livewire\Hub\BudgetTab;
use App\Livewire\Hub\ExhibitionTab;
use App\Livewire\Hub\RisksTab;
use App\Livewire\Hub\SponsorsTab;
use App\Livewire\Hub\TasksTab;
use App\Livewire\Hub\VenueTab;
use App\Livewire\RequirementsCatalog;
use App\Livewire\RoomLayoutBuilder;
use App\Models\Event;
use App\Models\EventBudgetItem;
use App\Models\Requirement;
use App\Models\User;
use App\Services\CurrencyService;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventHubActionsTest extends TestCase
{
    use RefreshDatabase;

    private function setup2(): array
    {
        $this->seed(DemoDataSeeder::class);

        return [
            Event::where('name', 'ICFT 2026')->firstOrFail(),
            User::where('email', 'emran.itan@elitebhub.com')->firstOrFail(),
        ];
    }

    public function test_task_can_be_added_and_completed(): void
    {
        [$event, $user] = $this->setup2();

        // Quick-add drops a task straight into a lane, no modal.
        Livewire::actingAs($user)->test(TasksTab::class, ['event' => $event])
            ->call('quickAdd', 'todo', 'Print VIP badges')
            ->assertHasNoErrors();

        $task = $event->tasks()->where('title', 'Print VIP badges')->firstOrFail();
        $this->assertSame('todo', $task->status);

        // Move it across the board and finish it.
        Livewire::actingAs($user)->test(TasksTab::class, ['event' => $event])
            ->call('moveTask', $task->id, 'doing')
            ->call('moveTask', $task->id, 'done');

        $this->assertSame('done', $task->fresh()->status);
    }

    public function test_budget_line_can_be_added_and_marked_paid(): void
    {
        [$event, $user] = $this->setup2();

        Livewire::actingAs($user)->test(BudgetTab::class, ['event' => $event])
            ->call('newLine')
            ->set('category', 'Logistics')
            ->set('description', 'Night security detail')
            ->set('quantity', 4)
            ->set('unit', '3000')     // 4 × 3000 = 12000 budgeted
            ->set('actual', '11500')
            ->set('paid', '5000')     // partial
            ->call('save')
            ->assertHasNoErrors();

        $item = $event->budgetItems()->where('description', 'Night security detail')->firstOrFail();
        $this->assertSame(1200000, $item->estimated_cents);
        $this->assertSame(1150000, $item->actual_cents);
        $this->assertSame(500000, $item->paid_cents);
        $this->assertSame('partial', $item->payment_status);

        Livewire::actingAs($user)->test(BudgetTab::class, ['event' => $event])
            ->call('markPaid', $item->id);

        $item->refresh();
        $this->assertSame('paid', $item->payment_status);
        $this->assertSame(1150000, $item->paid_cents); // paid up to actual
        $this->assertSame(0, $item->outstandingCents());
    }

    public function test_starter_template_and_budget_cap(): void
    {
        [$event, $user] = $this->setup2();
        $event->budgetItems()->delete(); // start from a clean ledger

        $c = Livewire::actingAs($user)->test(BudgetTab::class, ['event' => $event])
            ->set('budgetCap', '500000')            // $500k cap
            ->call('insertStarter');

        $this->assertSame(50000000, $event->fresh()->budget_cents);
        $this->assertSame(count(EventBudgetItem::STARTER_TEMPLATE), $event->budgetItems()->count());

        // idempotent — re-running adds no duplicates
        $c->call('insertStarter');
        $this->assertSame(count(EventBudgetItem::STARTER_TEMPLATE), $event->fresh()->budgetItems()->count());
    }

    public function test_management_fee_is_derived_and_editable(): void
    {
        [$event, $user] = $this->setup2();
        $event->budgetItems()->delete();
        $event->budgetItems()->create(['category' => 'venue', 'description' => 'Hall', 'quantity' => 1, 'estimated_cents' => 1000000, 'actual_cents' => 0, 'paid_cents' => 0, 'payment_status' => 'pending']);

        $c = Livewire::actingAs($user)->test(BudgetTab::class, ['event' => $event])
            ->set('feePct', '15');

        $this->assertSame(15.0, $event->fresh()->management_fee_pct);
        // 15% of 10,000 = 1,500 → grand 11,500
        $c->assertViewHas('feeEst', 150000)->assertViewHas('grandEst', 1150000);

        // amend the percentage
        $c->set('feePct', '10');
        $this->assertSame(10.0, $event->fresh()->management_fee_pct);
        $c->assertViewHas('feeEst', 100000)->assertViewHas('grandEst', 1100000);
    }

    public function test_budget_line_lands_in_a_category(): void
    {
        [$event, $user] = $this->setup2();

        Livewire::actingAs($user)->test(BudgetTab::class, ['event' => $event])
            ->call('newLine', 'Venues')
            ->set('description', 'Hall hire — 3 days')
            ->set('quantity', 3)
            ->set('unit', '20000')       // 3 × 20,000 = 60,000
            ->call('save')
            ->assertHasNoErrors();

        $line = $event->budgetItems()->where('description', 'Hall hire — 3 days')->firstOrFail();
        $this->assertSame('Venues', $line->category);
        $this->assertSame(6000000, $line->estimated_cents);
    }

    public function test_budget_categories_can_be_added_and_removed(): void
    {
        [$event, $user] = $this->setup2();

        // Defaults seed on mount.
        $c = Livewire::actingAs($user)->test(BudgetTab::class, ['event' => $event]);
        $this->assertSame(8, $event->budgetCategories()->count());

        // Add a custom category.
        $c->set('newCategoryName', 'Security & Safety')->call('addCategory');
        $this->assertSame(9, $event->fresh()->budgetCategories()->count());

        // Duplicate (case-insensitive) is rejected.
        $c->set('newCategoryName', 'security & safety')->call('addCategory')->assertHasErrors('newCategoryName');
        $this->assertSame(9, $event->fresh()->budgetCategories()->count());

        // Deleting a category with lines keeps them (moved elsewhere).
        $venues = $event->budgetCategories()->where('name', 'Venues')->firstOrFail();
        $line = $event->budgetItems()->create(['category' => 'Venues', 'description' => 'Hall XYZ', 'quantity' => 1, 'estimated_cents' => 500000, 'payment_status' => 'pending']);
        $before = $event->budgetItems()->count();
        $c->call('deleteCategory', $venues->id);
        $this->assertNull($event->budgetCategories()->where('name', 'Venues')->first());
        $this->assertSame($before, $event->budgetItems()->count()); // nothing lost
        $this->assertNotSame('Venues', $line->fresh()->category);    // moved to another category
    }

    public function test_budget_categories_can_be_reordered(): void
    {
        [$event, $user] = $this->setup2();
        $c = Livewire::actingAs($user)->test(BudgetTab::class, ['event' => $event]);

        $ids = $event->budgetCategories()->orderBy('position')->pluck('id')->all();
        // Move the last category to the front.
        $last = array_pop($ids);
        array_unshift($ids, $last);

        $c->call('reorderCategories', $ids);

        $this->assertSame($ids, $event->budgetCategories()->orderBy('position')->pluck('id')->all());
        $this->assertSame(0, $event->budgetCategories()->whereKey($last)->value('position'));
    }

    public function test_sponsor_packages_seed_and_can_be_managed(): void
    {
        [$event, $user] = $this->setup2();
        $c = Livewire::actingAs($user)->test(SponsorsTab::class, ['event' => $event]);

        $this->assertSame(9, $event->sponsorPackages()->count());
        $plat = $event->sponsorPackages()->where('name', 'Platinum Partner')->firstOrFail();
        $this->assertSame(3, $plat->slots); // Platinum Partner (3)
        $this->assertSame(10, $event->sponsorPackages()->where('name', 'Silver Partner')->value('slots'));

        // set a price + slots on Platinum
        $c->call('startEditPackage', $plat->id)->set('packageEditPrice', '50000')->set('packageEditSlots', '4')->call('savePackage');
        $this->assertSame(5000000, $plat->fresh()->price_cents);
        $this->assertSame(4, $plat->fresh()->slots);

        // add a custom package with its own slot limit; duplicate rejected
        $c->set('newPackageName', 'Knowledge Partner')->set('newPackagePrice', '20000')->set('newPackageSlots', '2')->call('addPackage');
        $this->assertSame(10, $event->fresh()->sponsorPackages()->count());
        $this->assertSame(2, $event->sponsorPackages()->where('name', 'Knowledge Partner')->value('slots'));
        $c->set('newPackageName', 'platinum partner')->call('addPackage')->assertHasErrors('newPackageName');
    }

    public function test_selling_a_sponsorship_autofills_amount_and_feeds_income(): void
    {
        [$event, $user] = $this->setup2();
        $event->sponsors()->delete();
        $event->ensureSponsorPackages();
        $event->sponsorPackages()->where('name', 'Gold Partner')->update(['price_cents' => 3000000]); // $30,000

        Livewire::actingAs($user)->test(SponsorsTab::class, ['event' => $event])
            ->call('newItem')
            ->set('name', 'Royal Jordanian')
            ->set('package', 'Gold Partner')          // triggers updatedPackage → auto-fill
            ->assertSet('amount', '30000')
            ->set('paid', '10000')
            ->call('save')
            ->assertHasNoErrors();

        $s = $event->sponsors()->where('name', 'Royal Jordanian')->firstOrFail();
        $this->assertSame(3000000, $s->amount_cents);
        $this->assertSame('Gold Partner', $s->package);

        // budget income reflects the sold sponsorship
        Livewire::actingAs($user)->test(BudgetTab::class, ['event' => $event])
            ->assertViewHas('sponsorsIncome', 3000000)
            ->assertViewHas('sponsorsReceived', 1000000);
    }

    public function test_income_targets_persist(): void
    {
        [$event, $user] = $this->setup2();
        Livewire::actingAs($user)->test(BudgetTab::class, ['event' => $event])
            ->set('clientTarget', '200000')
            ->set('sponsorshipTarget', '100000')
            ->set('exhibitionTarget', '50000')
            ->assertViewHas('clientTargetC', 20000000);

        $event->refresh();
        $this->assertSame(20000000, $event->client_target_cents);
        $this->assertSame(10000000, $event->sponsorship_target_cents);
        $this->assertSame(5000000, $event->exhibition_target_cents);
    }

    public function test_exhibition_target_persists(): void
    {
        [$event, $user] = $this->setup2();
        Livewire::actingAs($user)->test(ExhibitionTab::class, ['event' => $event])
            ->set('exhibitionTarget', '75000');
        $this->assertSame(7500000, $event->fresh()->exhibition_target_cents);
    }

    public function test_sponsor_package_benefits_persist(): void
    {
        [$event, $user] = $this->setup2();
        $c = Livewire::actingAs($user)->test(SponsorsTab::class, ['event' => $event]);
        $gold = $event->sponsorPackages()->where('name', 'Gold Partner')->firstOrFail();

        $c->call('startEditPackage', $gold->id)
            ->set('packageEditPrice', '30000')
            ->set('packageEditBlurb', 'Prime visibility')
            ->set('packageEditBenefits', "Logo on main stage\n2 exhibition booths\nSpeaking slot")
            ->call('savePackage');

        $gold->refresh();
        $this->assertSame(3000000, $gold->price_cents);
        $this->assertSame('Prime visibility', $gold->blurb);
        $this->assertSame(['Logo on main stage', '2 exhibition booths', 'Speaking slot'], $gold->benefits);
    }

    public function test_sponsorship_respects_package_slots(): void
    {
        [$event, $user] = $this->setup2();
        $event->sponsors()->delete();
        $event->ensureSponsorPackages(); // "Strategic Partner" has 1 slot

        $c = Livewire::actingAs($user)->test(SponsorsTab::class, ['event' => $event]);

        $c->call('newItem')->set('name', 'Alpha')->set('package', 'Strategic Partner')->set('amount', '10000')
            ->call('save')->assertHasNoErrors();
        $this->assertSame(1, $event->sponsors()->where('package', 'Strategic Partner')->count());

        // second sale of the same 1-slot package is blocked
        $c->call('newItem')->set('name', 'Beta')->set('package', 'Strategic Partner')->set('amount', '10000')
            ->call('save')->assertHasErrors('package');
        $this->assertSame(1, $event->sponsors()->where('package', 'Strategic Partner')->count());
    }

    public function test_sponsorship_prospectus_and_pdf_render(): void
    {
        [$event, $user] = $this->setup2();
        $event->ensureSponsorPackages();

        // full prospectus
        $this->actingAs($user)->get(route('events.sponsorship', $event))->assertOk()
            ->assertSee('Prospectus')->assertSee('Platinum Partner');

        // single-package sheet
        $this->actingAs($user)->get(route('events.sponsorship', $event).'?package=Gold%20Partner')->assertOk()
            ->assertSee('Gold Partner');

        // pdf download
        $pdf = $this->actingAs($user)->get(route('events.sponsorship.pdf', $event));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
    }

    public function test_exhibition_floor_plan_halls_place_move_and_fixtures(): void
    {
        [$event, $user] = $this->setup2();
        $ex = $event->exhibitors()->create(['company' => 'Acme Tech', 'booth_size' => '3×3', 'package' => 'standard', 'status' => 'confirmed']);

        $c = Livewire::actingAs($user)->test(ExhibitionFloorPlan::class, ['event' => $event]);

        // a default 30×20 hall is seeded
        $this->assertSame(1, $event->exhibitionHalls()->count());
        $hall = $event->exhibitionHalls()->first();
        $this->assertSame(30.0, $hall->width_m);

        // tray → creates a booth already assigned to the exhibitor, at hall centre
        $c->call('placeBooth', $ex->id);
        $booth = $event->booths()->firstOrFail();
        $this->assertSame($ex->id, $booth->exhibitor_id);
        $this->assertSame($hall->id, $booth->hall_id);
        $this->assertSame(3.0, $booth->w_m);
        $this->assertSame(15.0, $booth->x); // hall centre = 30/2

        // move stores metres (clamped to the hall)
        $c->call('moveBooth', $booth->id, 8.5, 6.0);
        $booth->refresh();
        $this->assertSame(8.5, $booth->x);
        $this->assertSame(6.0, $booth->y);

        // hall dimensions persist
        $c->set('hallW', '40')->set('hallL', '25');
        $this->assertSame(40.0, $hall->fresh()->width_m);

        // add a second hall + fixture there
        $c->call('addHall');
        $this->assertSame(2, $event->exhibitionHalls()->count());
        $hallBId = $c->get('hallId');
        $this->assertNotSame($hall->id, $hallBId); // switched to the new hall
        $c->call('addFixture', 'stage');
        $this->assertCount(1, $event->exhibitionHalls()->find($hallBId)->fixtures);

        // deleting the booth destroys inventory; the exhibitor returns to the tray
        $c->call('selectHall', $hall->id);
        $c->call('deleteBooth', $booth->id);
        $this->assertSame(0, $event->booths()->count());
        $this->assertNull($ex->fresh()->booth_number);
    }

    public function test_exhibition_floor_plan_and_pdf_render(): void
    {
        [$event, $user] = $this->setup2();
        $hall = $event->ensureExhibitionHall();
        $ex = $event->exhibitors()->create(['company' => 'Acme Tech', 'booth_size' => '3×3', 'package' => 'standard', 'status' => 'confirmed']);
        $event->booths()->create(['hall_id' => $hall->id, 'exhibitor_id' => $ex->id, 'number' => 'B01', 'price_cents' => 500000, 'x' => 10, 'y' => 8, 'w_m' => 3, 'h_m' => 3]);

        $this->actingAs($user)->get(route('events.exhibition-floor', $event))->assertOk()->assertSee('Floor Plan');

        $pdf = $this->actingAs($user)->get(route('events.exhibition-floor.pdf', $event));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
    }

    public function test_venue_requirements_cost_and_sync(): void
    {
        [$event, $user] = $this->setup2();
        $event->budgetItems()->delete();
        $event->rooms()->delete();
        $room = $event->rooms()->create(['name' => 'Main Hall', 'type' => 'main_hall', 'cost_cents' => 1000000]); // 10,000 hire

        // per-venue requirements are edited inside the venue detail
        $c = Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room]);
        $c->set('reqName', 'AV & sound')->set('reqCost', '5000')->call('addRequirement')->assertHasNoErrors();
        $c->set('reqName', 'Staging')->set('reqCost', '3000')->call('addRequirement');

        $room->refresh();
        $this->assertCount(2, $room->requirements);
        $this->assertSame(800000, $room->requirementsTotalCents());   // 5,000 + 3,000
        $this->assertSame(1800000, $room->totalCents());              // + 10,000 hire

        // budget sync mirrors the venue total under Venues, named with the room
        Livewire::actingAs($user)->test(BudgetTab::class, ['event' => $event]);
        $line = $event->budgetItems()->where('source_type', 'room')->first();
        $this->assertNotNull($line);
        $this->assertSame(1800000, $line->estimated_cents);
        $this->assertStringContainsString('Main Hall', $line->description);

        // remove a requirement → total + linked budget line follow
        $c->call('removeRequirement', $room->requirements[0]['id']);
        $this->assertSame(1300000, $room->fresh()->totalCents());     // 10,000 + 3,000
        Livewire::actingAs($user)->test(BudgetTab::class, ['event' => $event]);
        $this->assertSame(1300000, $event->budgetItems()->where('source_type', 'room')->first()->estimated_cents);
    }

    public function test_event_requirements_sync_to_budget(): void
    {
        [$event, $user] = $this->setup2();
        $event->budgetItems()->delete();
        $event->rooms()->delete();

        $c = Livewire::actingAs($user)->test(VenueTab::class, ['event' => $event]);
        $c->set('evReqName', 'Event insurance')->set('evReqCost', '4000')->call('addEventRequirement')->assertHasNoErrors();
        $c->set('evReqName', 'General security')->set('evReqCost', '6000')->call('addEventRequirement');

        $event->refresh();
        $this->assertCount(2, $event->event_requirements);
        $this->assertSame(1000000, $event->eventRequirementsTotalCents()); // 4,000 + 6,000

        // budget sync → one aggregate line under "Event Requirements"
        Livewire::actingAs($user)->test(BudgetTab::class, ['event' => $event]);
        $line = $event->budgetItems()->where('source_type', 'event_req')->first();
        $this->assertNotNull($line);
        $this->assertSame(1000000, $line->estimated_cents);
        $this->assertTrue($event->budgetCategories()->where('name', 'Event Requirements')->exists());
    }

    public function test_bulk_delete_selected_lines(): void
    {
        [$event, $user] = $this->setup2();
        $event->budgetItems()->delete();
        $a = $event->budgetItems()->create(['category' => 'Other', 'description' => 'A', 'quantity' => 1, 'estimated_cents' => 100, 'payment_status' => 'pending']);
        $b = $event->budgetItems()->create(['category' => 'Other', 'description' => 'B', 'quantity' => 1, 'estimated_cents' => 100, 'payment_status' => 'pending']);
        $keep = $event->budgetItems()->create(['category' => 'Other', 'description' => 'Keep', 'quantity' => 1, 'estimated_cents' => 100, 'payment_status' => 'pending']);

        Livewire::actingAs($user)->test(BudgetTab::class, ['event' => $event])
            ->call('toggleSelect', $a->id)
            ->call('toggleSelect', $b->id)
            ->call('deleteSelected');

        $this->assertNull($event->budgetItems()->find($a->id));
        $this->assertNull($event->budgetItems()->find($b->id));
        $this->assertNotNull($event->budgetItems()->find($keep->id));
    }

    public function test_requirements_catalog_and_pick_into_venue(): void
    {
        [$event, $user] = $this->setup2();

        // build the catalog
        Livewire::actingAs($user)->test(RequirementsCatalog::class)
            ->set('name', 'AV & sound system')->set('price', '5000')->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('requirements', ['name' => 'AV & sound system', 'unit_price_cents' => 500000]);

        $r = Requirement::firstOrFail();

        // picking from the catalog fills the venue requirement form (in the venue detail)
        $room = $event->rooms()->create(['name' => 'Hall', 'type' => 'main_hall']);
        Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->call('pickReq', $r->id)
            ->assertSet('reqName', 'AV & sound system')
            ->assertSet('reqCost', '5000');

        // catalog page renders
        $this->actingAs($user)->get(route('requirements.index'))->assertOk()->assertSee('Equipment Catalog');
    }

    public function test_bulk_delete_rooms(): void
    {
        [$event, $user] = $this->setup2();
        $event->rooms()->delete();
        $a = $event->rooms()->create(['name' => 'A', 'type' => 'breakout']);
        $b = $event->rooms()->create(['name' => 'B', 'type' => 'breakout']);
        $keep = $event->rooms()->create(['name' => 'Keep', 'type' => 'breakout']);

        Livewire::actingAs($user)->test(VenueTab::class, ['event' => $event])
            ->call('toggleSelect', $a->id)->call('toggleSelect', $b->id)->call('deleteSelected');

        $this->assertNull($event->rooms()->find($a->id));
        $this->assertNull($event->rooms()->find($b->id));
        $this->assertNotNull($event->rooms()->find($keep->id));
    }

    public function test_currency_service_uses_peg_in_tests(): void
    {
        $fx = app(CurrencyService::class);

        $this->assertSame(0.709, $fx->rate('USD', 'JOD'));
        $this->assertSame(1.0, $fx->rate('USD', 'USD'));
        $this->assertFalse($fx->isLive('USD', 'JOD'));
        $this->assertSame('JD 709', Event::moneyIn((int) round(100000 * 0.709), 'JOD'));
    }

    public function test_budget_income_and_net_result(): void
    {
        [$event, $user] = $this->setup2();
        $event->budgetItems()->delete();
        $event->sponsors()->delete();
        $event->budgetItems()->create(['category' => 'venue', 'description' => 'Hall', 'quantity' => 1, 'estimated_cents' => 1000000, 'payment_status' => 'pending']);
        $event->update(['management_fee_pct' => 0]); // keep cost = 10,000

        Livewire::actingAs($user)->test(BudgetTab::class, ['event' => $event])
            ->call('newIncome')
            ->set('incomeSource', 'tickets')
            ->set('incomeAmount', '15000')
            ->call('saveIncome')
            ->assertHasNoErrors()
            ->assertViewHas('totalIncome', 1500000)
            ->assertViewHas('netResult', 500000); // 15,000 income − 10,000 cost = +5,000
    }

    public function test_budget_auto_syncs_module_costs(): void
    {
        [$event, $user] = $this->setup2();
        $event->budgetItems()->delete();
        $event->rooms()->delete();
        $acc = $event->accommodations()->create(['hotel' => 'Kempinski', 'guest' => 'VIPs', 'rooms' => 1, 'cost_cents' => 800000, 'status' => 'booked']);
        $event->speakers()->create(['name' => 'Dr. Haddad', 'fee_cents' => 500000, 'status' => 'confirmed']);
        $event->rooms()->create(['name' => 'Astor A+B', 'type' => 'main_hall', 'cost_cents' => 1200000]);

        // mount auto-syncs → 3 linked lines
        Livewire::actingAs($user)->test(BudgetTab::class, ['event' => $event]);
        $this->assertSame(3, $event->budgetItems()->count());
        $line = $event->budgetItems()->where('source_type', 'accommodation')->first();
        $this->assertSame(800000, $line->estimated_cents);
        $this->assertTrue($line->isLinked());

        // linked lines are read-only in the budget
        Livewire::actingAs($user)->test(BudgetTab::class, ['event' => $event])->call('deleteLine', $line->id);
        $this->assertSame(3, $event->fresh()->budgetItems()->count());

        // change source → amount re-syncs on next mount (idempotent, no dupes)
        $acc->update(['cost_cents' => 950000]);
        Livewire::actingAs($user)->test(BudgetTab::class, ['event' => $event]);
        $this->assertSame(3, $event->fresh()->budgetItems()->count());
        $this->assertSame(950000, $line->fresh()->estimated_cents);

        // remove source → linked line disappears on next sync
        $acc->delete();
        Livewire::actingAs($user)->test(BudgetTab::class, ['event' => $event]);
        $this->assertSame(2, $event->fresh()->budgetItems()->count());
    }

    public function test_budget_approval_versioning_and_lock(): void
    {
        [$event, $user] = $this->setup2();
        $event->budgetItems()->delete();
        $line = $event->budgetItems()->create(['category' => 'venue', 'description' => 'Hall', 'quantity' => 1, 'estimated_cents' => 1000000, 'payment_status' => 'pending']);

        $c = Livewire::actingAs($user)->test(BudgetTab::class, ['event' => $event]);

        // submit → pending version created
        $c->set('approvalNote', 'Client baseline')->call('submitForApproval');
        $this->assertSame('pending', $event->fresh()->budget_status);
        $v = $event->budgetVersions()->first();
        $this->assertSame(1, $v->version);
        $this->assertSame('pending', $v->status);
        $this->assertSame(1000000, $v->totals['estimated']);

        // approve → locked, baseline frozen onto lines
        $c->call('approveBudget');
        $event->refresh();
        $this->assertSame('approved', $event->budget_status);
        $this->assertTrue($event->budgetLocked());
        $this->assertNotNull($event->budget_locked_at);
        $this->assertSame(1000000, $line->fresh()->approved_cents);

        // locked → edits are blocked
        $c->call('newLine')->set('unit', '5000')->set('description', 'Blocked line')->call('save');
        $this->assertSame(1, $event->fresh()->budgetItems()->count());
        $c->call('deleteLine', $line->id);
        $this->assertSame(1, $event->fresh()->budgetItems()->count());

        // revise → unlocked, edits allowed again, then re-approve as v2
        $c->call('reviseBudget');
        $this->assertSame('draft', $event->fresh()->budget_status);
        $c->call('newLine')->set('unit', '5000')->set('description', 'AV kit')->call('save');
        $this->assertSame(2, $event->fresh()->budgetItems()->count());

        $c->call('submitForApproval')->call('approveBudget');
        $this->assertSame(2, $event->budgetVersions()->max('version'));
        $this->assertSame(1, $event->budgetVersions()->where('status', 'superseded')->count());
        $this->assertSame(1, $event->budgetVersions()->where('status', 'approved')->count());
    }

    public function test_budget_rejection_returns_to_draft(): void
    {
        [$event, $user] = $this->setup2();
        $event->budgetItems()->delete();
        $event->budgetItems()->create(['category' => 'venue', 'description' => 'Hall', 'quantity' => 1, 'estimated_cents' => 500000, 'payment_status' => 'pending']);

        Livewire::actingAs($user)->test(BudgetTab::class, ['event' => $event])
            ->call('submitForApproval')
            ->call('rejectBudget');

        $this->assertSame('draft', $event->fresh()->budget_status);
        $this->assertSame('rejected', $event->budgetVersions()->first()->status);
        $this->assertFalse($event->fresh()->budgetLocked());
    }

    public function test_budget_renders_without_rooms(): void
    {
        [$event, $user] = $this->setup2();
        $event->rooms()->delete();

        Livewire::actingAs($user)->test(BudgetTab::class, ['event' => $event])
            ->assertOk()
            ->assertSee('Budget Controls');
    }

    public function test_budget_clear_all_and_duplicate(): void
    {
        [$event, $user] = $this->setup2();
        $event->budgetItems()->delete();
        $line = $event->budgetItems()->create(['category' => 'venue', 'description' => 'Hall', 'quantity' => 1, 'estimated_cents' => 500000, 'payment_status' => 'pending']);

        $c = Livewire::actingAs($user)->test(BudgetTab::class, ['event' => $event]);

        $c->call('duplicateLine', $line->id);
        $this->assertSame(2, $event->budgetItems()->count());
        $this->assertNotNull($event->budgetItems()->where('description', 'Hall (copy)')->first());

        $c->call('clearAllLines');
        $this->assertSame(0, $event->budgetItems()->count());
    }

    public function test_budget_saved_total_reflects_variance(): void
    {
        [$event, $user] = $this->setup2();
        $event->budgetItems()->delete();
        // budgeted 10,000 vs actual 9,000 → saved +1,000
        $event->budgetItems()->create(['category' => 'venue', 'description' => 'Under', 'quantity' => 1, 'estimated_cents' => 1000000, 'actual_cents' => 900000, 'payment_status' => 'pending']);
        // budgeted 5,000 vs actual 6,000 → over −1,000  (net saved = 0)
        $event->budgetItems()->create(['category' => 'catering', 'description' => 'Over', 'quantity' => 1, 'estimated_cents' => 500000, 'actual_cents' => 600000, 'payment_status' => 'pending']);
        // budgeted 3,000 with no actual → excluded from savings
        $event->budgetItems()->create(['category' => 'av', 'description' => 'Not yet', 'quantity' => 1, 'estimated_cents' => 300000, 'payment_status' => 'pending']);

        Livewire::actingAs($user)->test(BudgetTab::class, ['event' => $event])
            ->assertViewHas('savedTotal', 0)
            ->assertViewHas('hasActuals', true);
    }

    public function test_budget_pdf_downloads(): void
    {
        [$event, $user] = $this->setup2();
        $event->budgetItems()->create(['category' => 'venue', 'description' => 'Hall', 'quantity' => 1, 'estimated_cents' => 500000, 'actual_cents' => 0, 'paid_cents' => 0, 'payment_status' => 'pending']);

        $response = $this->actingAs($user)->get(route('events.budget.pdf', $event));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_risk_can_be_registered_and_escalated(): void
    {
        [$event, $user] = $this->setup2();

        Livewire::actingAs($user)->test(RisksTab::class, ['event' => $event])
            ->set('title', 'Speaker travel strike')
            ->set('category', 'logistics')
            ->set('probability', 4)
            ->set('impact', 3)
            ->call('save')
            ->assertHasNoErrors();

        $risk = $event->risks()->where('title', 'Speaker travel strike')->firstOrFail();
        $this->assertSame(12, $risk->severity());
        $this->assertSame('open', $risk->status);

        Livewire::actingAs($user)->test(RisksTab::class, ['event' => $event])
            ->call('setStatus', $risk->id, 'escalated');

        $this->assertSame('escalated', $risk->fresh()->status);
    }

    public function test_approval_flow_request_and_decide(): void
    {
        [$event, $user] = $this->setup2();

        Livewire::actingAs($user)->test(ApprovalsTab::class, ['event' => $event])
            ->set('title', 'Extra hostess staffing')
            ->set('type', 'supplier')
            ->call('save')
            ->assertHasNoErrors();

        $approval = $event->approvals()->where('title', 'Extra hostess staffing')->firstOrFail();
        $this->assertSame('pending', $approval->status);
        $this->assertSame($user->id, $approval->requested_by);

        // The requester can't decide their own request — a different manager does.
        $decider = User::where('email', 'layla.haddad@elitebhub.com')->firstOrFail();
        Livewire::actingAs($decider)->test(ApprovalsTab::class, ['event' => $event])
            ->call('decide', $approval->id, 'approved');

        $approval->refresh();
        $this->assertSame('approved', $approval->status);
        $this->assertSame($decider->id, $approval->decided_by);
        $this->assertNotNull($approval->decided_at);
    }

    public function test_actions_cannot_touch_another_events_records(): void
    {
        [$event, $user] = $this->setup2();
        $otherTask = Event::where('name', 'Tech Expo 2026')->firstOrFail()->tasks()->firstOrFail();

        $this->expectException(ModelNotFoundException::class);

        // The board only ever resolves tasks through its own event's relation,
        // so another event's task id is not found — never touched.
        Livewire::actingAs($user)->test(TasksTab::class, ['event' => $event])
            ->call('moveTask', $otherTask->id, 'done');
    }
}
