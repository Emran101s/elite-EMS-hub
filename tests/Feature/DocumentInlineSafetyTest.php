<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventDocument;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * An uploaded file must never be able to run script in the app's own origin.
 *
 * The hole: isViewable() used to accept anything matching `image/*`, and
 * image/svg+xml is a document that can carry <script>. The view route streams
 * the bytes back with the stored (attacker-supplied) Content-Type, same-origin,
 * under a CSP that permits inline script — so a stored SVG executed with the
 * viewer's session.
 */
class DocumentInlineSafetyTest extends TestCase
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

    private function document(Event $event, User $user, string $mime, string $name, string $body): EventDocument
    {
        $path = "event-documents/{$event->id}/".md5($name).'.bin';
        Storage::disk('local')->put($path, $body);

        return $event->documents()->create([
            'name' => $name,
            'original_name' => $name,
            'path' => $path,
            'disk' => 'local',
            'mime' => $mime,
            'size' => strlen($body),
            'uploaded_by' => $user->id,
        ]);
    }

    private const SVG_PAYLOAD = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.cookie)</script></svg>';

    public function test_an_svg_is_never_rendered_inline(): void
    {
        [$event, $user] = $this->ctx();
        $doc = $this->document($event, $user, 'image/svg+xml', 'logo.svg', self::SVG_PAYLOAD);

        $this->assertFalse($doc->isViewable(), 'an SVG must not be inline-viewable');

        $this->actingAs($user)
            ->get(route('events.documents.view', [$event, $doc]))
            ->assertNotFound();
    }

    public function test_an_svg_can_still_be_downloaded(): void
    {
        // Blocking the render must not block the file. Content-Disposition:
        // attachment means the browser saves it rather than executing it.
        [$event, $user] = $this->ctx();
        $doc = $this->document($event, $user, 'image/svg+xml', 'logo.svg', self::SVG_PAYLOAD);

        $res = $this->actingAs($user)->get(route('events.documents.download', [$event, $doc]));

        $res->assertOk();
        $this->assertStringContainsString('attachment', (string) $res->headers->get('content-disposition'));
    }

    public function test_other_script_carrying_types_are_blocked_too(): void
    {
        [$event, $user] = $this->ctx();

        foreach ([
            'text/html' => 'page.html',
            'image/svg' => 'old.svg',
            'application/xhtml+xml' => 'page.xhtml',
            'text/xml' => 'data.xml',
        ] as $mime => $name) {
            $doc = $this->document($event, $user, $mime, $name, '<html><script>1</script></html>');

            $this->assertFalse($doc->isViewable(), "{$mime} must not be inline-viewable");
        }
    }

    public function test_real_images_and_pdfs_still_open_in_a_tab(): void
    {
        // The fix is an allowlist, so the regression risk is over-blocking.
        [$event, $user] = $this->ctx();

        foreach (['image/jpeg' => 'a.jpg', 'image/png' => 'b.png', 'application/pdf' => 'c.pdf'] as $mime => $name) {
            $doc = $this->document($event, $user, $mime, $name, 'binary-bytes');

            $this->assertTrue($doc->isViewable(), "{$mime} should still be viewable");

            $this->actingAs($user)
                ->get(route('events.documents.view', [$event, $doc]))
                ->assertOk();
        }
    }

    public function test_the_inline_response_is_sandboxed(): void
    {
        // Defence in depth: even for an allowed type, the response forbids every
        // source and refuses mime sniffing, so widening the allowlist by mistake
        // cannot on its own turn into script execution.
        [$event, $user] = $this->ctx();
        $doc = $this->document($event, $user, 'image/png', 'b.png', 'binary-bytes');

        $res = $this->actingAs($user)->get(route('events.documents.view', [$event, $doc]));

        $res->assertOk();
        $csp = (string) $res->headers->get('content-security-policy');
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringContainsString('sandbox', $csp);
        $this->assertSame('nosniff', $res->headers->get('x-content-type-options'));
    }
}
