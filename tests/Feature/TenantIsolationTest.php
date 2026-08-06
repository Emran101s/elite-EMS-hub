<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveTenant;
use App\Models\Event;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The guarantee itself, not just the plumbing around it.
 *
 * Every other test in this slice — the guard, the coverage check, the full
 * suite staying green — proves the retrofit did not break anything. None of
 * them prove isolation actually works, because no tenant is ever bound under
 * RefreshDatabase: the migration's backfill only runs against a populated
 * database, so a fresh test database has no tenant, no user.tenant_id, and
 * Tenancy::id() returns null throughout — meaning the global scope's `if
 * ($id = Tenancy::id())` never once filters a row in the rest of the suite.
 *
 * A passing suite that never exercises the thing it is meant to test is worse
 * than no suite: it reads as coverage. These tests bind a tenant explicitly,
 * the way ResolveTenant will in a real request.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenancy::forget();   // static state must never leak into the next test
        parent::tearDown();
    }

    public function test_a_tenant_only_sees_its_own_rows(): void
    {
        $a = Tenant::create(['name' => 'Agency A', 'slug' => 'agency-a']);
        $b = Tenant::create(['name' => 'Agency B', 'slug' => 'agency-b']);

        $eventA = Tenancy::actingAs($a, fn () => Event::factory()->create(['tenant_id' => $a->id]));
        $eventB = Tenancy::actingAs($b, fn () => Event::factory()->create(['tenant_id' => $b->id]));

        $seenByA = Tenancy::actingAs($a, fn () => Event::pluck('id')->all());
        $seenByB = Tenancy::actingAs($b, fn () => Event::pluck('id')->all());

        $this->assertSame([$eventA->id], $seenByA);
        $this->assertSame([$eventB->id], $seenByB);
    }

    public function test_a_direct_lookup_by_id_across_tenants_finds_nothing(): void
    {
        // The sharpest form of the IDOR the audit flagged: guessing or being
        // handed another customer's numeric ID. find() must return null, not
        // the other tenant's row, however the ID was obtained.
        $a = Tenant::create(['name' => 'Agency A', 'slug' => 'agency-a']);
        $b = Tenant::create(['name' => 'Agency B', 'slug' => 'agency-b']);

        $event = Tenancy::actingAs($a, fn () => Event::factory()->create(['tenant_id' => $a->id]));

        $this->assertNull(Tenancy::actingAs($b, fn () => Event::find($event->id)));
    }

    public function test_new_rows_are_stamped_with_the_acting_tenant_automatically(): void
    {
        // Nobody writing a controller or a Livewire component should have to
        // remember to set tenant_id — that is precisely the kind of thing that
        // gets forgotten once, on the one form that matters.
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $event = Event::factory()->create(['tenant_id' => $tenant->id]);

        $task = Tenancy::actingAs($tenant, fn () => Task::factory()->create([
            'event_id' => $event->id, 'title' => 'Confirm the venue',
        ]));

        $this->assertSame($tenant->id, $task->fresh()->tenant_id);
    }

    public function test_an_explicit_tenant_id_is_not_overwritten(): void
    {
        // A seeder or an admin tool moving a row between tenants must be able
        // to set tenant_id explicitly. The stamp only fills a blank.
        $a = Tenant::create(['name' => 'A', 'slug' => 'a']);
        $b = Tenant::create(['name' => 'B', 'slug' => 'b']);
        $event = Event::factory()->create(['tenant_id' => $a->id]);

        $task = Tenancy::actingAs($a, fn () => Task::factory()->create([
            'event_id' => $event->id, 'title' => 'Cross-tenant', 'tenant_id' => $b->id,
        ]));

        $this->assertSame($b->id, $task->tenant_id);
    }

    public function test_no_tenant_bound_means_no_filtering_not_no_rows(): void
    {
        // The deliberate transitional gap, made explicit: console commands,
        // seeders, and anything reached before a tenant resolves must keep
        // working exactly as the single-tenant platform always has.
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        Tenancy::actingAs($tenant, fn () => Event::factory()->create(['tenant_id' => $tenant->id]));

        $this->assertFalse(Tenancy::check());
        $this->assertSame(1, Event::count());
    }

    public function test_across_tenants_deliberately_bypasses_the_scope(): void
    {
        $a = Tenant::create(['name' => 'A', 'slug' => 'a']);
        $b = Tenant::create(['name' => 'B', 'slug' => 'b']);
        Tenancy::actingAs($a, fn () => Event::factory()->create(['tenant_id' => $a->id]));
        Tenancy::actingAs($b, fn () => Event::factory()->create(['tenant_id' => $b->id]));

        $all = Tenancy::actingAs($a, fn () => Event::acrossTenants()->count());

        $this->assertSame(2, $all);
    }

    public function test_within_scoping_suspends_and_then_restores_the_active_tenant(): void
    {
        $a = Tenant::create(['name' => 'A', 'slug' => 'a']);
        $b = Tenant::create(['name' => 'B', 'slug' => 'b']);
        Tenancy::actingAs($a, fn () => Event::factory()->create(['tenant_id' => $a->id]));
        Tenancy::actingAs($b, fn () => Event::factory()->create(['tenant_id' => $b->id]));

        Tenancy::use($a);
        $duringSuspension = Tenancy::withoutScoping(fn () => Event::count());
        $afterSuspension = Event::count();

        $this->assertSame(2, $duringSuspension, 'a super-admin task must see every tenant');
        $this->assertSame(1, $afterSuspension, 'and normal scoping must resume the moment it ends');
    }

    public function test_resolve_tenant_middleware_binds_from_the_authenticated_user_and_unbinds_after(): void
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        Tenancy::actingAs($tenant, fn () => Event::factory()->create(['name' => 'Acme Conference', 'tenant_id' => $tenant->id]));

        $other = Tenant::create(['name' => 'Other', 'slug' => 'other']);
        Tenancy::actingAs($other, fn () => Event::factory()->create(['name' => 'Other Conference', 'tenant_id' => $other->id]));

        // Calls handle() directly — proves the bind/forget logic is correct in
        // isolation, but NOT that it fires at the right point in a real request.
        // See the test below for why that distinction is load-bearing.
        $seen = null;
        (new ResolveTenant)->handle(
            tap(request(), fn ($r) => $r->setUserResolver(fn () => $user)),
            function () use (&$seen) {
                $seen = Event::pluck('name')->all();

                return response('ok');
            }
        );

        $this->assertSame(['Acme Conference'], $seen);
        $this->assertFalse(Tenancy::check(), 'the binding must not survive past the request');
    }

    public function test_the_tenant_is_actually_bound_on_a_real_authenticated_request(): void
    {
        // The test above calls handle() directly and would stay green even if
        // ResolveTenant were registered in the wrong place in the middleware
        // stack. It was: the first version of this registered it on the GLOBAL
        // middleware stack (bootstrap/app.php's append()), which runs BEFORE
        // the 'web' group's session/auth middleware resolves $request->user() —
        // so on a real request the binding never happened, Tenancy::id() was
        // always null, and every model in the app was silently unscoped in
        // production while this exact suite stayed green throughout.
        //
        // Caught by routing a real GET through the actual kernel and checking
        // what was bound partway through — not by calling the middleware
        // in isolation. Fixed by registering it on the 'web' group instead
        // (Middleware::web(append: ...)), which runs after auth resolves.
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

        Route::middleware('web')
            ->get('/__tenancy_pipeline_probe', fn () => response()->json(['bound' => Tenancy::id()]));

        $response = $this->actingAs($user)->get('/__tenancy_pipeline_probe');

        $response->assertOk();
        $this->assertSame($tenant->id, $response->json('bound'));
    }
}
