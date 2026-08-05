<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventSpeaker;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfirmDialogTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_app_layout_hosts_the_shared_confirm_dialog(): void
    {
        $this->seed(DemoDataSeeder::class);
        $user = User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('dusk="confirm-dialog"', false)
            ->assertSee('ebhConfirmHost', false);
    }

    public function test_speakers_delete_uses_the_shared_confirm(): void
    {
        $this->seed(DemoDataSeeder::class);
        $user = User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
        $event = Event::where('name', 'ICFT 2026')->firstOrFail();

        EventSpeaker::create([
            'event_id' => $event->id,
            'name' => 'Confirm Dialog Speaker',
            'status' => 'invited',
        ]);

        $html = $this->actingAs($user)
            ->get(route('events.hub', [$event, 'tab' => 'speakers']))
            ->assertOk()
            ->assertSee('Confirm Dialog Speaker')
            ->getContent();

        $this->assertStringContainsString('window.ebhConfirm', $html);
        $this->assertStringContainsString('Remove Confirm Dialog Speaker?', $html);
    }
}
