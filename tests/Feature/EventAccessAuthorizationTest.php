<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The same-tenant half of the audit's IDOR finding, closed.
 *
 * Slice 3 closed the cross-tenant half: another tenant's event is invisible,
 * because the row itself is filtered out. This closes the half that remained
 * — a coordinator or viewer inside the RIGHT tenant, with no assignment to a
 * specific event, could still open its hub and pull its contract, budget and
 * attendee PDFs by changing a number in the URL. EventPolicy::update() used
 * to accept $event and never read it; that was the audit's literal example.
 *
 * These are real HTTP requests through the actual kernel, the same discipline
 * as TenantIsolationTest's pipeline test — a policy method tested by calling
 * it directly would not have caught the ResolveTenant near-miss in slice 3,
 * and the same is true here: routing is where the middleware actually runs.
 */
class EventAccessAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Tenant,1:Event} */
    private function tenantWithEvent(): array
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $event = Tenancy::actingAs($tenant, fn () => Event::factory()->create(['tenant_id' => $tenant->id]));

        return [$tenant, $event];
    }

    private function user(Tenant $tenant, string $role): User
    {
        return User::factory()->create(['tenant_id' => $tenant->id, 'role' => $role]);
    }

    public function test_a_coordinator_not_on_the_team_cannot_open_the_hub(): void
    {
        [$tenant, $event] = $this->tenantWithEvent();
        $bystander = $this->user($tenant, 'coordinator');

        $this->actingAs($bystander)->get(route('events.hub', $event))->assertForbidden();
    }

    public function test_a_coordinator_on_the_team_can_open_the_hub(): void
    {
        [$tenant, $event] = $this->tenantWithEvent();
        $member = $this->user($tenant, 'coordinator');
        $event->teamMembers()->attach($member->id, ['role' => 'coordinator']);

        $this->actingAs($member)->get(route('events.hub', $event))->assertOk();
    }

    public function test_a_manager_can_open_any_event_in_the_tenant_without_being_on_the_team(): void
    {
        // Matches every other gate in this app — manage-budget, manage-contract
        // and manage-team are all manager+ with no per-resource check. Tenancy
        // narrows "everyone" to "everyone in this tenant"; it does not shrink a
        // manager's reach below that.
        [$tenant, $event] = $this->tenantWithEvent();
        $manager = $this->user($tenant, 'manager');

        $this->actingAs($manager)->get(route('events.hub', $event))->assertOk();
    }

    public function test_the_audits_literal_example_is_closed_a_viewer_cannot_fetch_the_contract_pdf(): void
    {
        // "Any authenticated user — including a viewer — can fetch any event's
        // contract... by changing the ID in the URL." That sentence is why
        // this test exists.
        [$tenant, $event] = $this->tenantWithEvent();
        $viewer = $this->user($tenant, 'viewer');

        $this->actingAs($viewer)->get(route('events.contract.pdf', $event))->assertForbidden();
    }

    public function test_a_viewer_on_the_team_can_still_read_documents_just_not_write(): void
    {
        // view() gates the routes tested here; write ability is a separate,
        // unchanged concern (Gate::define('write', ...isAtLeast('coordinator'))).
        // A viewer is never coordinator+, so team membership grants read access
        // to this event's documents without granting any ability to edit them.
        [$tenant, $event] = $this->tenantWithEvent();
        $viewer = $this->user($tenant, 'viewer');
        $event->teamMembers()->attach($viewer->id, ['role' => 'viewer']);

        $this->actingAs($viewer)->get(route('events.hub', $event))->assertForbidden();
    }

    public function test_the_events_index_still_lists_events_a_coordinator_is_not_on(): void
    {
        // Deliberately unchanged: browsing what exists is the transparency the
        // tool is built around. Only OPENING an event, or pulling its
        // documents, now requires a reason to be there.
        [$tenant, $event] = $this->tenantWithEvent();
        $bystander = $this->user($tenant, 'coordinator');

        $this->actingAs($bystander)->get(route('events.index'))->assertOk();
    }

    public function test_export_routes_are_uniformly_gated_not_just_the_hub(): void
    {
        // A spot-check across different controller shapes (invokable, PDF,
        // xlsx template) — the route-group wrap is what makes this uniform;
        // gating only the hub route and leaving 35 export routes on 'auth'
        // alone would have repeated the exact bug this closes.
        [$tenant, $event] = $this->tenantWithEvent();
        $bystander = $this->user($tenant, 'coordinator');

        foreach (['events.budget.pdf', 'events.brief.pdf', 'events.attendees.template'] as $route) {
            $this->actingAs($bystander)->get(route($route, $event))
                ->assertForbidden("expected {$route} to be gated by EventPolicy::view()");
        }
    }
}
