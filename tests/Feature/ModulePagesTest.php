<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModulePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_module_page_renders_for_an_authenticated_user(): void
    {
        $user = User::factory()->create(['name' => 'Emran Ahmed']);

        foreach (config('modules.nav') as $module) {
            $this->actingAs($user)
                ->get($module['path'])
                ->assertOk()
                ->assertSee($module['label']);
        }
    }

    public function test_every_module_page_requires_auth(): void
    {
        foreach (config('modules.nav') as $module) {
            $this->get($module['path'])->assertRedirect(route('login'));
        }
    }

    public function test_the_dashboard_greets_the_user_by_first_name(): void
    {
        $user = User::factory()->create(['name' => 'Emran Ahmed']);

        // The greeting reads the clock, so any of the three is correct.
        $body = $this->actingAs($user)->get('/')->assertOk()->assertSee('Emran')->getContent();

        $this->assertTrue(
            (bool) preg_match('/Good (morning|afternoon|evening), Emran/', $body),
            'the dashboard greets by first name, with a greeting that fits the hour',
        );
    }
}
