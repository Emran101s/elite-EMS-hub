<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A blocked/rejected action used to share the same 'status' session key as a
 * saved one, so the global layout banner rendered every "you can't do that"
 * in the same green "success" styling as a real save. See
 * docs/10-current-codebase-assessment.md for the full audit — this covers
 * the shared layout mechanism the fix depends on, not each individual call
 * site (those are covered where they already had test coverage, e.g.
 * TeamRosterTest).
 */
class FlashMessageSeverityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_saved_message_renders_in_the_green_status_banner(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($user)
            ->withSession(['status' => 'Company profile saved.'])
            ->get(route('company.index'))
            ->assertOk()
            ->assertSee('Company profile saved.')
            ->assertSee('bg-success-soft', false);
    }

    public function test_a_blocked_action_renders_in_its_own_red_banner_not_the_green_one(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($user)
            ->withSession(['error' => "You can't remove your own account."])
            ->get(route('company.index'))
            ->assertOk()
            ->assertSee("You can't remove your own account.")
            ->assertSee('bg-danger-soft', false);
    }

    public function test_the_two_banners_can_appear_together_without_one_swallowing_the_other(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($user)
            ->withSession(['status' => 'Saved.', 'error' => 'But this part was blocked.'])
            ->get(route('company.index'))
            ->assertOk()
            ->assertSee('Saved.')
            ->assertSee('But this part was blocked.');
    }

    /**
     * Four settings screens rendered session('status') a second time in
     * their own markup, on top of the layout's global banner that already
     * shows it — a real "saved" message appeared twice on screen.
     */
    public function test_a_saved_message_appears_only_once_not_twice(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);

        $html = $this->actingAs($user)
            ->withSession(['status' => 'Company profile saved.'])
            ->get(route('company.index'))
            ->getContent();

        $this->assertSame(1, substr_count($html, 'Company profile saved.'));
    }
}
