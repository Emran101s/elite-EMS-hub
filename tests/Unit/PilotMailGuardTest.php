<?php

namespace Tests\Unit;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase 2's pilot-week mail safeguard, tested directly rather than through
 * Mail::fake() — boot() already ran before any test method's fake() call
 * takes effect, so a fake swaps in too late to observe what boot() did. This
 * calls the guard's own logic in isolation instead, which is what actually
 * needs proving: it must stay silent everywhere except a deliberately
 * configured production host.
 */
class PilotMailGuardTest extends TestCase
{
    private function guard(): void
    {
        (new \ReflectionMethod(AppServiceProvider::class, 'guardPilotMail'))
            ->invoke(new AppServiceProvider($this->app));
    }

    public function test_stays_silent_in_testing_even_if_a_redirect_is_configured(): void
    {
        config(['mail.pilot_redirect' => 'safety@elitebhub.com']);

        Mail::shouldReceive('alwaysTo')->never();

        $this->guard();
    }

    public function test_stays_silent_in_production_if_no_redirect_is_configured(): void
    {
        config(['mail.pilot_redirect' => null]);
        $this->app->detectEnvironment(fn () => 'production');

        Mail::shouldReceive('alwaysTo')->never();

        $this->guard();
    }

    public function test_activates_only_when_both_conditions_are_true(): void
    {
        config(['mail.pilot_redirect' => 'safety@elitebhub.com']);
        $this->app->detectEnvironment(fn () => 'production');

        Mail::shouldReceive('alwaysTo')->once()->with('safety@elitebhub.com');

        $this->guard();
    }

    public function test_pilot_redirect_is_unset_by_default(): void
    {
        $this->assertArrayHasKey('pilot_redirect', config('mail'));
        $this->assertNull(config('mail.pilot_redirect'));
    }
}
