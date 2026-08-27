<?php

namespace Tests\Feature;

use App\Livewire\CatalogueSettings;
use App\Livewire\Hub\PricingTab;
use App\Livewire\InvoiceEditor;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\ServiceItem;
use App\Models\TaxonomyTerm;
use App\Models\User;
use App\Support\Taxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Where a service comes from, which is not what it is.
 *
 * The hotel's rate sheet, the production house's quote and the rental
 * company's list arrive separately from separate people, so each gets its own
 * tab and its own import. A document pricing a line searches all of them at
 * once, because a line does not care who supplies it.
 */
class PriceListSectionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function item(string $code, string $name, ?string $section, string $category = 'Misc'): ServiceItem
    {
        return ServiceItem::create([
            'code' => $code, 'name' => $name, 'category' => $category, 'section' => $section,
            'unit' => 'item', 'unit_price_cents' => 100_00, 'currency' => 'JOD',
        ]);
    }

    private function list(?User $u = null)
    {
        return Livewire::actingAs($u ?? $this->admin())->test(CatalogueSettings::class);
    }

    /* ── the tabs ── */

    public function test_a_section_shows_only_its_own_items(): void
    {
        $room = $this->item('ACC-DBL', 'Double room', 'hotel');
        $stage = $this->item('AV-STG', 'Main stage', 'production');

        $c = $this->list();
        $this->assertCount(2, $c->viewData('items'), 'everything, to begin with');

        $c->call('pickSection', 'hotel');

        $this->assertSame([$room->id], $c->viewData('items')->pluck('id')->all());

        $c->call('pickSection', 'production');

        $this->assertSame([$stage->id], $c->viewData('items')->pluck('id')->all());
    }

    public function test_every_section_is_counted_including_the_unfiled(): void
    {
        $this->item('A', 'Room', 'hotel');
        $this->item('B', 'Suite', 'hotel');
        $this->item('C', 'Stage', 'production');
        $this->item('D', 'Something nobody filed', null);

        $counts = $this->list()->viewData('counts');

        $this->assertSame(2, $counts['hotel']);
        $this->assertSame(1, $counts['production']);
        $this->assertSame(1, $counts['none'], 'the unfiled are browsable, not invisible');
        $this->assertArrayNotHasKey('equipment', $counts->all());
    }

    /** A category means nothing outside its own section. */
    public function test_opening_a_section_drops_the_category_filter(): void
    {
        $this->item('A', 'Lunch', 'hotel', 'Catering');
        $this->item('B', 'Radio', 'equipment', 'Comms');

        $c = $this->list()->set('category', 'Catering')->call('pickSection', 'equipment');

        $this->assertSame('all', $c->get('category'));
        $this->assertCount(1, $c->viewData('items'));
    }

    public function test_adding_while_a_section_is_open_files_it_there(): void
    {
        $this->list()->call('pickSection', 'equipment')
            ->call('newItem')
            ->assertSet('itemSection', 'equipment')
            ->set('name', 'Badge printer')
            ->call('save');

        $this->assertSame('equipment', ServiceItem::firstOrFail()->section);
    }

    public function test_a_section_can_be_renamed_without_moving_anything(): void
    {
        Taxonomy::seed();
        $item = $this->item('A', 'Room', 'hotel');

        TaxonomyTerm::where('taxonomy', 'service_section')->where('key', 'hotel')
            ->firstOrFail()->update(['label' => 'In-house']);
        Taxonomy::forget();

        $this->assertSame('hotel', $item->fresh()->section, 'the key records store never moves');
        $this->assertSame('In-house', $item->fresh()->sectionLabel());
    }

    /* ── the import ── */

    public function test_importing_inside_a_section_files_every_row_there(): void
    {
        $c = $this->list()->call('pickSection', 'equipment')
            ->set('importFile', $this->sheet([
                ['Code', 'Item', 'Category', 'Section', 'Unit', 'Unit price', 'Currency', 'Tax %', 'Detail'],
                ['EQP-RAD', 'Two-way radio', 'Comms', '', 'Per day', 6, 'JOD', 16, ''],
                ['EQP-LAP', 'Laptop', 'IT', '', 'Per day', 25, 'JOD', 16, ''],
            ]))->call('import');

        $this->assertStringContainsString('2 added', $c->get('importMsg'));
        $this->assertSame(2, ServiceItem::where('section', 'equipment')->count());
    }

    /** A sheet that names its own section wins over the open tab. */
    public function test_a_row_that_names_its_section_keeps_it(): void
    {
        $this->list()->call('pickSection', 'equipment')
            ->set('importFile', $this->sheet([
                ['Code', 'Item', 'Category', 'Section', 'Unit', 'Unit price', 'Currency', 'Tax %', 'Detail'],
                ['ACC-DBL', 'Double room', 'Accommodation', 'Hotel services', 'Per room per night', 95, 'JOD', 16, ''],
                ['EQP-RAD', 'Two-way radio', 'Comms', '', 'Per day', 6, 'JOD', 16, ''],
            ]))->call('import');

        $this->assertSame('hotel', ServiceItem::where('code', 'ACC-DBL')->firstOrFail()->section);
        $this->assertSame('equipment', ServiceItem::where('code', 'EQP-RAD')->firstOrFail()->section);
    }

    /**
     * The sheet gained a column in the middle. A reader that counts columns
     * would take an older sheet's Unit for its Section and be wrong silently.
     */
    public function test_a_sheet_written_before_sections_still_imports_correctly(): void
    {
        $this->list()->set('importFile', $this->sheet([
            ['Code', 'Item', 'Category', 'Unit', 'Unit price', 'Currency', 'Tax %', 'Detail'],
            ['ACC-DBL', 'Double room', 'Accommodation', 'Per room per night', 95, 'JOD', 16, 'B&B'],
        ]))->call('import');

        $item = ServiceItem::firstOrFail();

        $this->assertSame('room_night', $item->unit);
        $this->assertSame(95_00, $item->unit_price_cents);
        $this->assertNull($item->section, 'unstated, not guessed');
    }

    public function test_the_template_comes_in_a_section_flavour(): void
    {
        $all = $this->actingAs($this->admin())->get(route('catalogue.template'))->assertOk();
        $this->assertStringContainsString('elite-price-list-template', $all->headers->get('content-disposition'));

        $one = $this->actingAs($this->admin())
            ->get(route('catalogue.template', ['section' => 'equipment']))->assertOk();
        $this->assertStringContainsString('equipment-rental', $one->headers->get('content-disposition'));

        // A section nobody defined falls back to the whole sheet rather than 404ing.
        $this->actingAs($this->admin())
            ->get(route('catalogue.template', ['section' => 'nonsense']))->assertOk();
    }

    /* ── picking a price ── */

    /** "Retrieve from everywhere" — one box, every section. */
    public function test_an_invoice_line_searches_across_every_section(): void
    {
        $this->item('ACC-DBL', 'Double room', 'hotel', 'Accommodation');
        $this->item('AV-STG', 'Main stage', 'production', 'AV & Production');
        $this->item('EQP-RAD', 'Two-way radio', 'equipment', 'Comms');

        $invoice = Invoice::create(['number' => Invoice::nextNumber(), 'status' => 'draft']);

        $c = Livewire::actingAs($this->admin())->test(InvoiceEditor::class, ['invoice' => $invoice])
            ->call('newLine');

        $this->assertCount(3, $c->viewData('catalogue'));
    }

    /** Somebody types the words on the tab, not the key underneath it. */
    public function test_searching_a_section_by_name_finds_its_items(): void
    {
        Taxonomy::seed();
        $this->item('ACC-DBL', 'Double room', 'hotel', 'Accommodation');
        $this->item('AV-STG', 'Main stage', 'production', 'AV & Production');

        $invoice = Invoice::create(['number' => Invoice::nextNumber(), 'status' => 'draft']);

        $c = Livewire::actingAs($this->admin())->test(InvoiceEditor::class, ['invoice' => $invoice])
            ->call('newLine')->set('catalogueQuery', 'Hotel services');

        $this->assertSame(['ACC-DBL'], $c->viewData('catalogue')->pluck('code')->all());
    }

    /* ── an event's own list ── */

    public function test_pulling_an_item_carries_its_section_onto_the_event(): void
    {
        $house = $this->item('ACC-DBL', 'Double room', 'hotel', 'Accommodation');
        $event = Event::factory()->create();

        Livewire::actingAs($this->admin())->test(PricingTab::class, ['event' => $event])
            ->call('pullFromHouse', $house->id);

        $this->assertSame('hotel', $event->invoiceItems()->firstOrFail()->section);
    }

    public function test_an_events_import_reads_the_section_column(): void
    {
        $event = Event::factory()->create();

        Livewire::actingAs($this->admin())->test(PricingTab::class, ['event' => $event])
            ->set('importFile', $this->sheet([
                ['Code', 'Item', 'Category', 'Section', 'Unit', 'Costs us', 'We charge', 'Tax %', 'Detail'],
                ['EQP-RAD', 'Two-way radio', 'Comms', 'Equipment & rental', 'Per day', 4, 6, 16, ''],
            ]))->call('import');

        $item = $event->invoiceItems()->firstOrFail();

        $this->assertSame('equipment', $item->section);
        $this->assertEquals(4_00, $item->cost_cents);
        $this->assertEquals(6_00, $item->sell_cents);
    }

    /** @param list<list<mixed>> $rows */
    private function sheet(array $rows): UploadedFile
    {
        $ss = new Spreadsheet;
        $ss->getActiveSheet()->fromArray($rows, null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'sec').'.xlsx';
        (new Xlsx($ss))->save($path);

        Storage::fake('local');

        return UploadedFile::fake()->createWithContent('price-list.xlsx', file_get_contents($path));
    }
}
