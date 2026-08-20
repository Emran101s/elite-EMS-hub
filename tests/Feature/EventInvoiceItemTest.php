<?php

namespace Tests\Feature;

use App\Livewire\Hub\PricingTab;
use App\Livewire\InvoiceEditor;
use App\Models\Event;
use App\Models\EventInvoiceItem;
use App\Models\Invoice;
use App\Models\ServiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * The house price list is a starting point, not a rate card.
 *
 * A room negotiated at 78 for one summit is 95 for the next, and the cost
 * behind it moves too — so the prices an event is billed at belong to the
 * event, nothing is rolled in automatically, and the invoice bills from the
 * event's own list.
 */
class EventInvoiceItemTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function event(): Event
    {
        return Event::factory()->create(['currency' => 'JOD'])->fresh();
    }

    private function houseRoom(): ServiceItem
    {
        return ServiceItem::create([
            'code' => 'ACC-DBL', 'name' => 'Double room, 5★', 'category' => 'Accommodation',
            'unit' => 'room_night', 'unit_price_cents' => 95_00, 'currency' => 'JOD', 'tax_pct' => 16,
        ]);
    }

    private function tab(Event $event, ?User $u = null)
    {
        return Livewire::actingAs($u ?? $this->admin())->test(PricingTab::class, ['event' => $event]);
    }

    /* ── the event owns its prices ── */

    public function test_a_new_event_starts_with_no_prices_of_its_own(): void
    {
        $this->houseRoom();
        $event = $this->event();

        $this->assertCount(0, $event->invoiceItems,
            'the house list is never rolled in — a stale copy is worse than none');
    }

    public function test_an_item_carries_both_a_cost_and_a_price(): void
    {
        $event = $this->event();

        $this->tab($event)->call('newItem')
            ->set('name', 'Double room, 5★')
            ->set('code', 'ACC-DBL')
            ->set('unit', 'room_night')
            ->set('cost', '78')
            ->set('sell', '95')
            ->call('save');

        $item = $event->invoiceItems()->firstOrFail();

        $this->assertEquals(78_00, $item->cost_cents);
        $this->assertEquals(95_00, $item->sell_cents);
        $this->assertEquals(17_00, $item->marginCents());
        $this->assertSame(18, $item->marginPct());
        $this->assertFalse($item->isUnderwater());
    }

    public function test_a_price_below_cost_is_called_what_it_is(): void
    {
        $event = $this->event();
        $item = $event->invoiceItems()->create([
            'name' => 'Sold at a loss', 'unit' => 'item', 'cost_cents' => 100_00, 'sell_cents' => 80_00,
        ]);

        $this->assertTrue($item->isUnderwater());
        $this->assertEquals(-20_00, $item->marginCents());

        $this->assertCount(1, $this->tab($event)->viewData('underwater'));
    }

    /** Two events may price the same code differently. That is the point. */
    public function test_the_same_code_can_be_priced_differently_on_two_events(): void
    {
        $a = $this->event();
        $b = $this->event();

        $a->invoiceItems()->create(['code' => 'ACC-DBL', 'name' => 'Room', 'unit' => 'room_night', 'sell_cents' => 95_00]);
        $b->invoiceItems()->create(['code' => 'ACC-DBL', 'name' => 'Room', 'unit' => 'room_night', 'sell_cents' => 78_00]);

        $this->assertEquals(95_00, $a->invoiceItems()->first()->sell_cents);
        $this->assertEquals(78_00, $b->invoiceItems()->first()->sell_cents);
    }

    public function test_a_code_cannot_be_priced_twice_on_one_event(): void
    {
        $event = $this->event();
        $event->invoiceItems()->create(['code' => 'ACC-DBL', 'name' => 'Room', 'unit' => 'item', 'sell_cents' => 95_00]);

        $this->tab($event)->call('newItem')->set('name', 'Another room')->set('code', 'ACC-DBL')
            ->call('save')->assertHasErrors(['code']);
    }

    /* ── pulling from the house list ── */

    public function test_pulling_copies_the_house_price_and_leaves_the_cost_blank(): void
    {
        $house = $this->houseRoom();
        $event = $this->event();

        $this->tab($event)->call('pullFromHouse', $house->id);

        $item = $event->invoiceItems()->firstOrFail();

        $this->assertSame($house->id, $item->service_item_id, 'provenance is kept');
        $this->assertEquals(95_00, $item->sell_cents, 'at the house price, to begin with');
        $this->assertEquals(0, $item->cost_cents,
            'what a supplier will charge for THIS event is a fact nobody has yet');
        $this->assertSame('room_night', $item->unit);
    }

    /** Changing the house price never moves an event that has been priced. */
    public function test_repricing_the_house_list_leaves_the_event_alone(): void
    {
        $house = $this->houseRoom();
        $event = $this->event();

        $this->tab($event)->call('pullFromHouse', $house->id);

        $house->update(['unit_price_cents' => 140_00]);

        $this->assertEquals(95_00, $event->invoiceItems()->first()->sell_cents,
            'the event was priced at 95 and stays priced at 95');
    }

    public function test_the_house_list_says_what_is_already_here(): void
    {
        $house = $this->houseRoom();
        $event = $this->event();

        $c = $this->tab($event)->call('toggleCatalogue');
        $this->assertNotContains($house->id, $c->viewData('taken'));

        $c->call('pullFromHouse', $house->id);
        $this->assertContains($house->id, $c->viewData('taken'));
    }

    /* ── the template and the import ── */

    public function test_the_template_downloads_for_this_event(): void
    {
        $event = $this->event();

        $res = $this->actingAs($this->admin())
            ->get(route('events.pricing.template', $event))->assertOk();

        $this->assertStringContainsString('spreadsheetml', $res->headers->get('content-type'));
        $this->assertStringContainsString('invoice-items', $res->headers->get('content-disposition'));
    }

    public function test_importing_prices_the_event_and_reprices_on_a_second_pass(): void
    {
        $event = $this->event();

        $c = $this->tab($event)->set('importFile', $this->sheet([
            ['Code', 'Item', 'Category', 'Unit', 'Costs us', 'We charge', 'Tax %', 'Detail'],
            ['ACC-DBL', 'Double room, 5★', 'Accommodation', 'Per room per night', 78, 95, 16, ''],
            ['CAT-LUN', 'Delegate lunch', 'Catering', 'Per person', 17, 22, 16, ''],
            ['NO-NAME', '', 'Catering', 'Per person', 1, 2, 16, ''],
        ]))->call('import');

        $this->assertSame(2, $event->invoiceItems()->count());
        $this->assertStringContainsString('2 added', $c->get('importMsg'));
        $this->assertStringContainsString('skipped', $c->get('importMsg'));

        $room = $event->invoiceItems()->where('code', 'ACC-DBL')->firstOrFail();
        $this->assertEquals(78_00, $room->cost_cents);
        $this->assertEquals(95_00, $room->sell_cents);
        $this->assertSame('room_night', $room->unit);

        // A second pass at a new price is a correction, not a duplicate.
        $c->set('importFile', $this->sheet([
            ['Code', 'Item', 'Category', 'Unit', 'Costs us', 'We charge', 'Tax %', 'Detail'],
            ['ACC-DBL', 'Double room, 5★', 'Accommodation', 'Per room per night', 72, 88, 16, ''],
        ]))->call('import');

        $this->assertSame(2, $event->invoiceItems()->count());
        $this->assertEquals(88_00, $room->fresh()->sell_cents);
        $this->assertStringContainsString('1 repriced', $c->get('importMsg'));
    }

    /* ── what it is all for ── */

    public function test_an_invoice_for_an_event_bills_from_that_events_prices(): void
    {
        $this->houseRoom();                       // the house sells at 95
        $event = $this->event();
        $event->invoiceItems()->create([          // this event agreed 78
            'code' => 'ACC-DBL', 'name' => 'Double room, 5★', 'unit' => 'room_night',
            'cost_cents' => 62_00, 'sell_cents' => 78_00, 'currency' => 'JOD',
        ]);

        $invoice = Invoice::create(['number' => Invoice::nextNumber(), 'status' => 'draft',
            'event_id' => $event->id]);

        $c = Livewire::actingAs($this->admin())->test(InvoiceEditor::class, ['invoice' => $invoice])
            ->call('newLine');

        $this->assertTrue($c->viewData('priceListIsEvent'));

        $item = $event->invoiceItems()->firstOrFail();
        $c->call('pick', $item->id)->set('factors', [12, 3])->call('saveLine');

        $line = $invoice->fresh()->load('lines')->lines->firstOrFail();

        $this->assertEquals(78_00, $line->unit_cents, "this event's price, not the house 95");
        $this->assertSame(36.0, $line->qty);
        $this->assertSame('Double room, 5★ — 12 rooms × 3 nights', $line->description);
    }

    /** An invoice with no event has only the house list, which is right. */
    public function test_an_invoice_with_no_event_bills_from_the_house_list(): void
    {
        $house = $this->houseRoom();
        $invoice = Invoice::create(['number' => Invoice::nextNumber(), 'status' => 'draft']);

        $c = Livewire::actingAs($this->admin())->test(InvoiceEditor::class, ['invoice' => $invoice])
            ->call('newLine');

        $this->assertFalse($c->viewData('priceListIsEvent'));
        $this->assertContains($house->id, $c->viewData('catalogue')->pluck('id'));

        $c->call('pick', $house->id)->set('factors', [2, 1])->call('saveLine');

        $this->assertEquals(95_00, $invoice->fresh()->load('lines')->lines->first()->unit_cents);
    }

    public function test_a_retired_item_is_not_offered_on_an_invoice(): void
    {
        $event = $this->event();
        $item = $event->invoiceItems()->create(['name' => 'Room', 'unit' => 'item', 'sell_cents' => 95_00]);
        $invoice = Invoice::create(['number' => Invoice::nextNumber(), 'status' => 'draft', 'event_id' => $event->id]);

        $c = Livewire::actingAs($this->admin())->test(InvoiceEditor::class, ['invoice' => $invoice])->call('newLine');
        $this->assertContains($item->id, $c->viewData('catalogue')->pluck('id'));

        $item->update(['active' => false]);
        $this->assertNotContains($item->id, $c->call('newLine')->viewData('catalogue')->pluck('id'));
    }

    public function test_only_budget_writers_may_price_an_event(): void
    {
        $event = $this->event();
        $viewer = User::create(['name' => 'Vic Viewer', 'email' => 'viewer@ebh.test',
            'password' => bcrypt('x'), 'role' => 'viewer']);

        $this->tab($event, $viewer)->call('newItem')->assertForbidden();
    }

    public function test_the_tab_renders_in_the_hub(): void
    {
        $event = $this->event();
        $event->invoiceItems()->create(['name' => 'Double room', 'unit' => 'room_night',
            'cost_cents' => 78_00, 'sell_cents' => 95_00]);

        $this->actingAs($this->admin())
            ->get(route('events.hub', [$event, 'tab' => 'pricing']))->assertOk()
            ->assertSee('Invoice items')
            ->assertSee('Double room');
    }

    /** @param list<list<mixed>> $rows */
    private function sheet(array $rows): UploadedFile
    {
        $ss = new Spreadsheet;
        $ss->getActiveSheet()->fromArray($rows, null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'evp').'.xlsx';
        (new Xlsx($ss))->save($path);

        Storage::fake('local');

        return UploadedFile::fake()->createWithContent('invoice-items.xlsx', file_get_contents($path));
    }
}
