<?php

namespace Tests\Feature;

use App\Livewire\CatalogueSettings;
use App\Livewire\InvoiceEditor;
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
 * The price list, and the thing it exists for.
 *
 * Every invoice line used to be typed from nothing, so the same room rate was
 * entered a dozen different ways. The interesting column is the unit:
 * accommodation is sold per room per night, transport per vehicle per day, and
 * the editor asks for both numbers rather than leaving somebody to multiply in
 * their head and type 36 with nothing explaining where 36 came from.
 */
class ServiceCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function room(): ServiceItem
    {
        return ServiceItem::create([
            'code' => 'ACC-DBL', 'name' => 'Double room, 5★', 'category' => 'Accommodation',
            'unit' => 'room_night', 'unit_price_cents' => 95_00, 'currency' => 'JOD', 'tax_pct' => 16,
        ]);
    }

    /* ── the unit does the counting ── */

    public function test_a_room_night_is_counted_in_rooms_and_nights(): void
    {
        $item = $this->room();

        $this->assertSame(['Rooms', 'Nights'], $item->factors());
        $this->assertSame(36.0, $item->quantityFrom([12, 3]));
        $this->assertSame('Double room, 5★ — 12 rooms × 3 nights', $item->describe([12, 3]));
    }

    public function test_a_fixed_fee_is_one_of_itself_and_asks_for_nothing(): void
    {
        $fee = ServiceItem::create(['name' => 'Management fee', 'unit' => 'fixed', 'unit_price_cents' => 500_00]);

        $this->assertSame([], $fee->factors());
        $this->assertSame(1.0, $fee->quantityFrom([]));
        $this->assertSame('Management fee', $fee->describe([]));
    }

    /** Half a filled form should still price something. */
    public function test_a_blank_factor_counts_as_one_rather_than_as_nothing(): void
    {
        $item = $this->room();

        $this->assertSame(5.0, $item->quantityFrom([5, '']));
        $this->assertSame(5.0, $item->quantityFrom([5]));
        $this->assertSame('Double room, 5★ — 5 rooms', $item->describe([5, '']));
    }

    /* ── the settings page ── */

    public function test_the_price_list_page_renders_and_groups_by_category(): void
    {
        $this->room();

        $this->actingAs($this->admin())->get(route('catalogue.index'))->assertOk()
            ->assertSee('Price list')
            ->assertSee('Accommodation')
            ->assertSee('Double room, 5★', false);
    }

    public function test_an_item_can_be_added_edited_and_retired(): void
    {
        $c = Livewire::actingAs($this->admin())->test(CatalogueSettings::class);

        $c->call('newItem')
            ->set('name', 'Coach, 50 seats')
            ->set('code', 'TRN-BUS')
            ->set('itemCategory', 'Transportation')
            ->set('unit', 'vehicle_trip')
            ->set('price', '250')
            ->call('save');

        $item = ServiceItem::firstOrFail();
        $this->assertSame('Coach, 50 seats', $item->name);
        $this->assertSame(250_00, $item->unit_price_cents);
        $this->assertSame('vehicle_trip', $item->unit);

        $c->call('edit', $item->id)->set('price', '275')->call('save');
        $this->assertSame(275_00, $item->fresh()->unit_price_cents);

        // Retired rather than deleted: history is history.
        $c->call('toggleActive', $item->id);
        $this->assertFalse($item->fresh()->active);
        $this->assertCount(0, ServiceItem::active()->get());
    }

    public function test_a_duplicate_code_is_refused(): void
    {
        $this->room();

        Livewire::actingAs($this->admin())->test(CatalogueSettings::class)
            ->call('newItem')->set('name', 'Another room')->set('code', 'ACC-DBL')
            ->call('save')->assertHasErrors(['code']);
    }

    public function test_only_settings_writers_may_change_the_price_list(): void
    {
        $viewer = User::create(['name' => 'Vic Viewer', 'email' => 'viewer@ebh.test',
            'password' => bcrypt('x'), 'role' => 'viewer']);

        Livewire::actingAs($viewer)->test(CatalogueSettings::class)
            ->call('newItem')->assertForbidden();
    }

    /* ── the template and the import ── */

    public function test_the_template_downloads_as_a_spreadsheet(): void
    {
        $res = $this->actingAs($this->admin())->get(route('catalogue.template'))->assertOk();

        $this->assertStringContainsString('spreadsheetml', $res->headers->get('content-type'));
        $this->assertStringContainsString('price-list-template', $res->headers->get('content-disposition'));
    }

    /** A re-import is a correction, not a second copy. */
    public function test_importing_adds_new_rows_and_updates_the_ones_it_recognises(): void
    {
        $this->room();

        $file = $this->sheet([
            ['Code', 'Item', 'Category', 'Unit', 'Unit price', 'Currency', 'Tax %', 'Detail'],
            ['ACC-DBL', 'Double room, 5★', 'Accommodation', 'Per room per night', 110, 'JOD', 16, 'Now with breakfast'],
            ['CAT-LUN', 'Delegate lunch', 'Catering', 'Per person', 22, 'JOD', 16, ''],
            // A real mistake: somebody filled the code and forgot the name.
            ['XX-NONAME', '', 'Catering', 'Per person', 10, 'JOD', 16, ''],
        ]);

        $c = Livewire::actingAs($this->admin())->test(CatalogueSettings::class)
            ->set('importFile', $file)->call('import');

        $this->assertSame(2, ServiceItem::count(), 'the known code was corrected, not duplicated');
        $this->assertSame(110_00, ServiceItem::where('code', 'ACC-DBL')->first()->unit_price_cents);

        $lunch = ServiceItem::where('code', 'CAT-LUN')->firstOrFail();
        $this->assertSame('person', $lunch->unit, 'the unit label maps back to its key');

        $this->assertStringContainsString('1 added', $c->get('importMsg'));
        $this->assertStringContainsString('1 updated', $c->get('importMsg'));
        $this->assertStringContainsString('skipped', $c->get('importMsg'), 'the nameless row is reported, not imported');
    }

    /* ── what it is all for: pricing a line ── */

    public function test_picking_an_item_prices_the_line_from_rooms_and_nights(): void
    {
        $item = $this->room();
        $invoice = Invoice::create(['number' => Invoice::nextNumber(), 'status' => 'draft']);

        $c = Livewire::actingAs($this->admin())->test(InvoiceEditor::class, ['invoice' => $invoice]);

        $c->call('pick', $item->id)
            ->set('factors', [12, 3])
            ->call('saveLine');

        $line = $invoice->fresh()->load('lines')->lines->firstOrFail();

        $this->assertSame(36.0, $line->qty, '12 rooms × 3 nights');
        $this->assertSame(95_00, $line->unit_cents, 'at the price the list says');
        $this->assertSame(3420_00, $line->amountCents());
        $this->assertSame('Double room, 5★ — 12 rooms × 3 nights', $line->description,
            'the line says how it was arrived at');
    }

    public function test_the_picker_can_be_abandoned_for_a_typed_line(): void
    {
        $item = $this->room();
        $invoice = Invoice::create(['number' => Invoice::nextNumber(), 'status' => 'draft']);

        Livewire::actingAs($this->admin())->test(InvoiceEditor::class, ['invoice' => $invoice])
            ->call('pick', $item->id)
            ->call('unpick')
            ->set('description', 'Something we do not sell twice')
            ->set('qty', '1')
            ->set('unit', '400')
            ->call('saveLine');

        $line = $invoice->fresh()->load('lines')->lines->firstOrFail();
        $this->assertSame('Something we do not sell twice', $line->description);
        $this->assertSame(400_00, $line->unit_cents);
    }

    public function test_a_retired_item_is_not_offered_on_an_invoice(): void
    {
        $item = $this->room();
        $invoice = Invoice::create(['number' => Invoice::nextNumber(), 'status' => 'draft']);

        $c = Livewire::actingAs($this->admin())->test(InvoiceEditor::class, ['invoice' => $invoice])
            ->call('newLine');

        $this->assertContains($item->id, $c->viewData('catalogue')->pluck('id'));

        $item->update(['active' => false]);
        $this->assertNotContains($item->id, $c->call('newLine')->viewData('catalogue')->pluck('id'));
    }

    /** A price list is a tool for writing a line, not furniture on the page. */
    public function test_the_catalogue_is_not_loaded_until_a_line_is_open(): void
    {
        $this->room();
        $invoice = Invoice::create(['number' => Invoice::nextNumber(), 'status' => 'draft']);

        $c = Livewire::actingAs($this->admin())->test(InvoiceEditor::class, ['invoice' => $invoice]);

        $this->assertCount(0, $c->viewData('catalogue'));
        $this->assertGreaterThan(0, $c->call('newLine')->viewData('catalogue')->count());
    }

    /** @param list<list<mixed>> $rows */
    private function sheet(array $rows): UploadedFile
    {
        $ss = new Spreadsheet;
        $ss->getActiveSheet()->fromArray($rows, null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'cat').'.xlsx';
        (new Xlsx($ss))->save($path);

        // Livewire holds a TemporaryUploadedFile on a file property, so the
        // fixture has to be one — a plain UploadedFile fails to hydrate.
        Storage::fake('local');

        return UploadedFile::fake()->createWithContent('price-list.xlsx', file_get_contents($path));
    }
}
