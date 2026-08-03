<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * One spelling per kind of date — the same fix as Money, for the 38 different
 * ->format() strings doing five or six jobs between them.
 */
class DateComponentTest extends TestCase
{
    public function test_short_style_is_the_everyday_reading(): void
    {
        $html = view('components.date', ['value' => Carbon::parse('2026-09-06'), 'style' => 'short'])->render();
        $this->assertStringContainsString('6 Sep 2026', $html);
    }

    public function test_document_style_spells_out_the_month_with_no_weekday(): void
    {
        $html = view('components.date', ['value' => '2026-09-06', 'style' => 'document'])->render();
        $this->assertStringContainsString('6 September 2026', $html);
    }

    public function test_long_style_includes_the_weekday(): void
    {
        $html = view('components.date', ['value' => '2026-09-06', 'style' => 'long'])->render();
        $this->assertStringContainsString('Sunday, 6 September 2026', $html);
    }

    public function test_with_time_style_uses_the_app_wide_middle_dot(): void
    {
        $html = view('components.date', ['value' => '2026-09-06 14:30:00', 'style' => 'withTime'])->render();
        $this->assertStringContainsString('6 Sep 2026 · 14:30', $html);
    }

    public function test_a_null_value_renders_the_empty_placeholder(): void
    {
        $html = view('components.date', ['value' => null])->render();
        $this->assertStringContainsString('—', $html);

        $html = view('components.date', ['value' => null, 'empty' => 'No expiry'])->render();
        $this->assertStringContainsString('No expiry', $html);
    }

    public function test_a_plain_string_is_parsed_the_same_as_a_carbon_instance(): void
    {
        $fromString = view('components.date', ['value' => '2026-09-06', 'style' => 'short'])->render();
        $fromCarbon = view('components.date', ['value' => Carbon::parse('2026-09-06'), 'style' => 'short'])->render();
        $this->assertSame(trim(strip_tags($fromString)), trim(strip_tags($fromCarbon)));
    }
}
