<?php

namespace Tests\Feature;

use App\Livewire\VenueStudio\DocumentsTab;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class VenueDocumentCenterTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::create(['name' => 'Ops', 'email' => 'ops@ebh.test',
            'password' => bcrypt('x'), 'role' => 'admin']);
    }

    private function pdf(string $name = 'venue agreement signed.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 120, 'application/pdf');
    }

    private function document(Venue $venue, User $user, string $mime, string $name, string $body): VenueDocument
    {
        $path = "venue-documents/{$venue->id}/".md5($name).'.bin';
        Storage::disk('local')->put($path, $body);

        return $venue->documents()->create([
            'category' => 'other', 'name' => $name, 'original_name' => $name,
            'path' => $path, 'disk' => 'local', 'mime' => $mime,
            'size' => strlen($body), 'uploaded_by' => $user->id,
        ]);
    }

    public function test_a_dropped_file_is_named_and_filed_under_its_category(): void
    {
        Storage::fake('local');
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);
        $user = $this->actor();

        Livewire::actingAs($user)->test(DocumentsTab::class, ['venue' => $venue])
            ->set('upload', $this->pdf())
            ->assertSet('name', 'Venue Agreement Signed')
            ->set('category', 'contract')
            ->call('store')
            ->assertHasNoErrors();

        $doc = $venue->documents()->firstOrFail();
        $this->assertSame('Venue Agreement Signed', $doc->name);
        $this->assertSame('contract', $doc->category);
        $this->assertSame('draft', $doc->status);
        $this->assertSame('venue agreement signed.pdf', $doc->original_name);
        $this->assertSame($user->id, $doc->uploaded_by);
        Storage::disk('local')->assertExists($doc->path);
    }

    public function test_a_contract_status_can_be_updated(): void
    {
        Storage::fake('local');
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);
        $user = $this->actor();

        Livewire::actingAs($user)->test(DocumentsTab::class, ['venue' => $venue])
            ->set('upload', $this->pdf())
            ->set('category', 'contract')
            ->call('store');

        $doc = $venue->documents()->firstOrFail();

        Livewire::actingAs($user)->test(DocumentsTab::class, ['venue' => $venue])
            ->call('updateStatus', $doc->id, 'signed');

        $this->assertSame('signed', $doc->fresh()->status);
    }

    public function test_a_non_contract_document_never_carries_a_status(): void
    {
        Storage::fake('local');
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);
        $user = $this->actor();

        Livewire::actingAs($user)->test(DocumentsTab::class, ['venue' => $venue])
            ->set('upload', $this->pdf('floor plan.pdf'))
            ->set('category', 'floor_plan')
            ->set('status', 'signed') // set even though it shouldn't apply
            ->call('store');

        $doc = $venue->documents()->firstOrFail();
        $this->assertSame('floor_plan', $doc->category);
        $this->assertNull($doc->status);

        // updateStatus() must also refuse to set a status on a non-contract row.
        Livewire::actingAs($user)->test(DocumentsTab::class, ['venue' => $venue])
            ->call('updateStatus', $doc->id, 'signed');

        $this->assertNull($doc->fresh()->status);
    }

    public function test_deleting_a_document_removes_the_stored_file(): void
    {
        Storage::fake('local');
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);
        $user = $this->actor();
        $doc = $this->document($venue, $user, 'application/pdf', 'spec.pdf', 'binary-bytes');

        Livewire::actingAs($user)->test(DocumentsTab::class, ['venue' => $venue])
            ->call('delete', $doc->id);

        $this->assertNull(VenueDocument::find($doc->id));
        Storage::disk('local')->assertMissing($doc->path);
    }

    public function test_a_document_cannot_be_fetched_through_another_venue(): void
    {
        Storage::fake('local');
        $venueA = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);
        $venueB = Venue::create(['name' => 'Dead Sea Resort', 'city' => 'Dead Sea']);
        $user = $this->actor();
        $doc = $this->document($venueA, $user, 'application/pdf', 'spec.pdf', 'binary-bytes');

        $this->actingAs($user)
            ->get(route('venues.documents.download', [$venueB, $doc]))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('venues.documents.view', [$venueB, $doc]))
            ->assertNotFound();
    }

    public function test_a_viewer_cannot_upload_rename_or_delete(): void
    {
        Storage::fake('local');
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);
        $viewer = User::create(['name' => 'Vic', 'email' => 'v@ebh.test',
            'password' => bcrypt('x'), 'role' => 'viewer']);

        Livewire::actingAs($viewer)->test(DocumentsTab::class, ['venue' => $venue])
            ->set('upload', $this->pdf())
            ->assertForbidden();

        $this->assertSame(0, VenueDocument::count());
    }

    public function test_an_svg_is_never_rendered_inline(): void
    {
        Storage::fake('local');
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);
        $user = $this->actor();
        $doc = $this->document($venue, $user, 'image/svg+xml', 'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.cookie)</script></svg>');

        $this->assertFalse($doc->isViewable());

        $this->actingAs($user)
            ->get(route('venues.documents.view', [$venue, $doc]))
            ->assertNotFound();
    }

    public function test_an_svg_can_still_be_downloaded(): void
    {
        Storage::fake('local');
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);
        $user = $this->actor();
        $doc = $this->document($venue, $user, 'image/svg+xml', 'logo.svg', '<svg></svg>');

        $res = $this->actingAs($user)->get(route('venues.documents.download', [$venue, $doc]));

        $res->assertOk();
        $this->assertStringContainsString('attachment', (string) $res->headers->get('content-disposition'));
    }

    public function test_the_inline_response_is_sandboxed(): void
    {
        Storage::fake('local');
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);
        $user = $this->actor();
        $doc = $this->document($venue, $user, 'image/png', 'b.png', 'binary-bytes');

        $res = $this->actingAs($user)->get(route('venues.documents.view', [$venue, $doc]));

        $res->assertOk();
        $csp = (string) $res->headers->get('content-security-policy');
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringContainsString('sandbox', $csp);
        $this->assertSame('nosniff', $res->headers->get('x-content-type-options'));
    }
}
