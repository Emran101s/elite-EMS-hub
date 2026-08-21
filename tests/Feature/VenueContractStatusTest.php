<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenueContractStatusTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::create(['name' => 'Ops', 'email' => 'ops@ebh.test',
            'password' => bcrypt('x'), 'role' => 'admin']);
    }

    private function contractDoc(Venue $venue, User $user, string $status, ?\Carbon\Carbon $createdAt = null): void
    {
        $doc = $venue->documents()->create([
            'category' => 'contract', 'status' => $status,
            'name' => "Contract ({$status})", 'original_name' => 'contract.pdf',
            'path' => "venue-documents/{$venue->id}/{$status}.pdf", 'disk' => 'local',
            'mime' => 'application/pdf', 'size' => 100, 'uploaded_by' => $user->id,
        ]);

        if ($createdAt) {
            $doc->forceFill(['created_at' => $createdAt])->save();
        }
    }

    public function test_the_header_shows_no_contract_pill_when_none_exists(): void
    {
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);

        $this->actingAs($this->actor())->get(route('venues.show', $venue))
            ->assertOk()
            ->assertDontSee('Contract Draft')
            ->assertDontSee('Contract Signed');
    }

    public function test_the_header_shows_the_most_recent_contract_documents_status(): void
    {
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);
        $user = $this->actor();

        $this->contractDoc($venue, $user, 'draft', now()->subDays(10));
        $this->contractDoc($venue, $user, 'signed', now()->subDay());

        $this->actingAs($user)->get(route('venues.show', $venue))
            ->assertOk()
            ->assertSee('Contract Signed')
            ->assertDontSee('Contract Draft');
    }

    public function test_a_non_contract_document_never_drives_the_header_pill(): void
    {
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);
        $user = $this->actor();

        $venue->documents()->create([
            'category' => 'floor_plan', 'status' => null,
            'name' => 'Floor Plan', 'original_name' => 'plan.pdf',
            'path' => "venue-documents/{$venue->id}/plan.pdf", 'disk' => 'local',
            'mime' => 'application/pdf', 'size' => 100, 'uploaded_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('venues.show', $venue))
            ->assertOk()
            ->assertDontSee('Contract Draft')
            ->assertDontSee('Contract Signed');
    }
}
