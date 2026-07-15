<?php

namespace Tests\Feature;

use App\Livewire\RequirementsCatalog;
use App\Models\Requirement;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class EquipmentCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function boot(): User
    {
        $this->seed(DemoDataSeeder::class);

        return User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
    }

    public function test_pdf_export_downloads(): void
    {
        $user = $this->boot();
        Requirement::create(['name' => 'AV System', 'unit_price_cents' => 120000]);

        $pdf = $this->actingAs($user)->get(route('requirements.pdf'));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
    }

    public function test_csv_import_creates_equipment_with_header_mapping(): void
    {
        $user = $this->boot();
        $before = Requirement::count();

        $csv = "name,price,notes\nStage & Lighting,9000,main hall\nAV & Sound,12000.50,\n,,skip me\n";
        $file = UploadedFile::fake()->createWithContent('equipment.csv', $csv);

        Livewire::actingAs($user)->test(RequirementsCatalog::class)
            ->set('importFile', $file)
            ->call('import')
            ->assertHasNoErrors();

        $this->assertSame($before + 2, Requirement::count()); // blank-name row skipped
        $stage = Requirement::where('name', 'Stage & Lighting')->firstOrFail();
        $this->assertSame(900000, $stage->unit_price_cents);
        $this->assertSame('main hall', $stage->notes);
        $this->assertSame(1200050, Requirement::where('name', 'AV & Sound')->value('unit_price_cents'));
    }

    public function test_xlsx_import_works(): void
    {
        $user = $this->boot();
        $before = Requirement::count();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Name', 'Price', 'Notes'],
            ['Projector', 450, '4K laser'],
            ['Truss', 300, ''],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'eq').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $file = UploadedFile::fake()->createWithContent('equipment.xlsx', file_get_contents($path));

        Livewire::actingAs($user)->test(RequirementsCatalog::class)
            ->set('importFile', $file)
            ->call('import')
            ->assertHasNoErrors();

        $this->assertSame($before + 2, Requirement::count());
        $this->assertSame(45000, Requirement::where('name', 'Projector')->value('unit_price_cents'));
    }
}
