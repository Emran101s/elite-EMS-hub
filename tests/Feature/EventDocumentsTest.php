<?php

namespace Tests\Feature;

use App\Livewire\Hub\ModuleDocuments;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EventDocumentsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Event,1:User} */
    private function ctx(): array
    {
        Storage::fake('local');
        $this->seed(DemoDataSeeder::class);

        return [
            Event::where('name', 'ICFT 2026')->firstOrFail(),
            User::where('email', 'emran.itan@elitebhub.com')->firstOrFail(),
        ];
    }

    private function pdf(string $name = 'hotel contract signed.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 120, 'application/pdf');
    }

    public function test_a_dropped_file_is_named_and_filed_under_its_module(): void
    {
        [$event, $user] = $this->ctx();

        $c = Livewire::actingAs($user)->test(ModuleDocuments::class,
            ['event' => $event, 'module' => 'accommodation'])
            ->set('upload', $this->pdf());

        // The proposed name is readable, not the raw filename.
        $c->assertSet('name', 'Hotel Contract Signed')
            ->assertSet('assignTo', 'accommodation')
            ->call('store')
            ->assertHasNoErrors();

        $doc = $event->documents()->firstOrFail();
        $this->assertSame('Hotel Contract Signed', $doc->name);
        $this->assertSame('accommodation', $doc->module);
        $this->assertSame('hotel contract signed.pdf', $doc->original_name);
        $this->assertSame($user->id, $doc->uploaded_by);
        $this->assertNull($doc->folder_id, 'lands at the drawer root');
        Storage::disk('local')->assertExists($doc->path);
    }

    public function test_the_document_is_visible_from_the_module_it_belongs_to(): void
    {
        [$event, $user] = $this->ctx();

        Livewire::actingAs($user)->test(ModuleDocuments::class,
            ['event' => $event, 'module' => 'accommodation'])
            ->set('upload', $this->pdf())->call('store');

        // Present in Accommodation…
        Livewire::actingAs($user)->test(ModuleDocuments::class,
            ['event' => $event, 'module' => 'accommodation'])
            ->assertSee('Hotel Contract Signed');

        // …and absent from an unrelated module's drawer.
        Livewire::actingAs($user)->test(ModuleDocuments::class,
            ['event' => $event, 'module' => 'budget'])
            ->assertDontSee('Hotel Contract Signed');
    }

    public function test_subfolders_nest_and_files_land_inside_the_open_one(): void
    {
        [$event, $user] = $this->ctx();

        $c = Livewire::actingAs($user)->test(ModuleDocuments::class,
            ['event' => $event, 'module' => 'venue'])
            ->set('newFolder', 'Floor plans')->call('addFolder');

        $folder = $event->documentFolders()->where('name', 'Floor plans')->firstOrFail();
        $this->assertSame('venue', $folder->module);
        $this->assertNull($folder->parent_id);

        // A subfolder of a subfolder.
        $c->call('openFolder', $folder->id)
            ->set('newFolder', 'Approved')->call('addFolder');

        $child = $event->documentFolders()->where('name', 'Approved')->firstOrFail();
        $this->assertSame($folder->id, $child->parent_id);
        $this->assertSame(['Floor plans', 'Approved'], collect($child->trail())->pluck('name')->all());

        // Dropping while inside the child files it there.
        $c->call('openFolder', $child->id)
            ->set('upload', $this->pdf('main hall.pdf'))
            ->call('store');

        $this->assertSame($child->id, $event->documents()->firstOrFail()->folder_id);
    }

    public function test_deleting_a_folder_keeps_its_documents(): void
    {
        [$event, $user] = $this->ctx();

        $c = Livewire::actingAs($user)->test(ModuleDocuments::class,
            ['event' => $event, 'module' => 'venue'])
            ->set('newFolder', 'Quotes')->call('addFolder');

        $folder = $event->documentFolders()->firstOrFail();
        $c->call('openFolder', $folder->id)->set('upload', $this->pdf())->call('store');

        $doc = $event->documents()->firstOrFail();
        $this->assertSame($folder->id, $doc->folder_id);

        $c->call('deleteFolder', $folder->id);

        // The paper survives, moved up to the module root.
        $this->assertNotNull($doc->fresh(), 'deleting a folder must not destroy its documents');
        $this->assertNull($doc->fresh()->folder_id);
        $this->assertSame('venue', $doc->fresh()->module);
    }

    public function test_a_document_can_be_refiled_under_another_module(): void
    {
        [$event, $user] = $this->ctx();

        $c = Livewire::actingAs($user)->test(ModuleDocuments::class,
            ['event' => $event, 'module' => 'accommodation'])
            ->set('upload', $this->pdf())->call('store');

        $doc = $event->documents()->firstOrFail();
        $c->call('reassign', $doc->id, 'transportation');

        $this->assertSame('transportation', $doc->fresh()->module);
    }

    public function test_the_reassign_control_renders_on_the_page(): void
    {
        [$event, $user] = $this->ctx();

        $c = Livewire::actingAs($user)->test(ModuleDocuments::class,
            ['event' => $event, 'module' => 'accommodation'])
            ->set('upload', $this->pdf())->call('store');

        $doc = $event->documents()->firstOrFail();

        $c->assertSee('wire:change="reassign('.$doc->id.', $event.target.value)"', false);
    }

    public function test_the_library_wall_lists_every_drawer_with_its_colour(): void
    {
        [$event, $user] = $this->ctx();

        $wall = Livewire::actingAs($user)->test(ModuleDocuments::class,
            ['event' => $event, 'library' => true])
            ->viewData('wall');

        $accommodation = $wall->firstWhere('key', 'accommodation');
        $this->assertSame('Accommodation', $accommodation['label']);
        $this->assertSame(Event::MODULE_COLORS['accommodation'], $accommodation['color']);

        // Event-wide is always offered, and every colour is a distinct hex.
        $this->assertNotNull($wall->firstWhere('key', null));
        $colors = $wall->pluck('color')->all();
        $this->assertSame(count($colors), count(array_unique($colors)), 'each drawer needs its own colour');
    }

    public function test_a_drawer_stays_visible_when_its_module_is_switched_off(): void
    {
        [$event, $user] = $this->ctx();

        Livewire::actingAs($user)->test(ModuleDocuments::class,
            ['event' => $event, 'module' => 'sponsors'])
            ->set('upload', $this->pdf())->call('store');

        $event->update(['enabled_modules' => ['tasks']]);   // sponsors switched off

        $wall = Livewire::actingAs($user)->test(ModuleDocuments::class,
            ['event' => $event, 'library' => true])->viewData('wall');

        $sponsors = $wall->firstWhere('key', 'sponsors');
        $this->assertNotNull($sponsors, 'papers must not disappear with the module');
        $this->assertFalse($sponsors['enabled']);
        $this->assertSame(1, $sponsors['documents']);
    }

    public function test_deleting_a_document_removes_the_stored_file(): void
    {
        [$event, $user] = $this->ctx();

        $c = Livewire::actingAs($user)->test(ModuleDocuments::class,
            ['event' => $event, 'module' => 'venue'])
            ->set('upload', $this->pdf())->call('store');

        $doc = $event->documents()->firstOrFail();
        $path = $doc->path;
        Storage::disk('local')->assertExists($path);

        $c->call('delete', $doc->id);

        $this->assertNull(EventDocument::find($doc->id));
        Storage::disk('local')->assertMissing($path);
    }

    public function test_a_document_cannot_be_fetched_through_another_event(): void
    {
        [$event, $user] = $this->ctx();
        $other = Event::where('name', '!=', 'ICFT 2026')->firstOrFail();

        Livewire::actingAs($user)->test(ModuleDocuments::class,
            ['event' => $event, 'module' => 'venue'])
            ->set('upload', $this->pdf())->call('store');

        $doc = $event->documents()->firstOrFail();

        $this->actingAs($user)->get(route('events.documents.download', [$event, $doc]))->assertOk();
        $this->actingAs($user)->get(route('events.documents.download', [$other, $doc]))->assertNotFound();
    }

    public function test_a_viewer_cannot_upload_or_delete(): void
    {
        [$event] = $this->ctx();
        $viewer = User::create(['name' => 'Vic Viewer', 'email' => 'v@ebh.test',
            'password' => bcrypt('x'), 'role' => 'viewer']);

        Livewire::actingAs($viewer)->test(ModuleDocuments::class,
            ['event' => $event, 'module' => 'venue'])
            ->set('newFolder', 'Sneaky')
            ->call('addFolder')
            ->assertForbidden();

        $this->assertSame(0, $event->documentFolders()->count());
    }
}
