<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * A real gate now, not just a report. Counts colour values that bypass the
 * navy/gold token system in resources/css/app.css — arbitrary Tailwind
 * bracket values and named colours outside the established palette (navy,
 * gold, and the semantic status trio of emerald/amber/red plus the one
 * deliberate sky exception on room-equipment status).
 *
 * Shipped as a soft, always-passing report at 126 pre-existing violations —
 * failing on it then would have blocked unrelated work. Driven to 0 since
 * (see git log for this file), so it now actually fails if new drift
 * appears rather than just printing a number nobody has to act on.
 *
 * PDF/paper templates are excluded on purpose: the design system explicitly
 * allows documents their own visual language, separate from the screens.
 */
class DesignTokenDriftTest extends TestCase
{
    private const OFF_PALETTE_COLORS = [
        'indigo', 'purple', 'pink', 'teal', 'cyan', 'lime', 'rose',
        'violet', 'fuchsia', 'orange', 'yellow', 'blue', 'green',
        'slate', 'zinc', 'stone', 'gray', 'neutral',
    ];

    public function test_off_token_colour_usage_is_reported(): void
    {
        $files = collect(glob(base_path('resources/views/**/*.blade.php')))
            ->merge(glob(base_path('resources/views/**/**/*.blade.php')))
            ->merge(glob(base_path('resources/views/**/**/**/*.blade.php')))
            ->unique()
            ->reject(fn (string $path) => str_ends_with($path, '-pdf.blade.php')
                || str_ends_with($path, 'paper.blade.php'));

        $arbitraryPattern = '/\[(#[0-9a-fA-F]{3,8}|rgba?\([^)]*\))\]/';
        $namedPattern = '/(?:bg|text|border|ring|from|via|to|divide|outline|decoration|fill|stroke)-('
            .implode('|', self::OFF_PALETTE_COLORS).')-\d{2,3}/';

        $report = [];
        $total = 0;

        foreach ($files as $path) {
            $content = file_get_contents($path);
            $relative = str_replace(base_path().'/', '', $path);

            $arbitrary = (int) preg_match_all($arbitraryPattern, $content);
            preg_match_all($namedPattern, $content, $namedMatches);
            $named = count($namedMatches[0]);

            if ($arbitrary + $named > 0) {
                $colours = array_count_values($namedMatches[1]);
                $report[$relative] = ['arbitrary' => $arbitrary, 'named' => $named, 'colours' => $colours];
                $total += $arbitrary + $named;
            }
        }

        if ($total > 0) {
            fwrite(STDERR, "\n\n— Design token drift report — {$total} off-token colour usages across ".count($report)." files —\n");
            arsort($report);
            foreach (array_slice($report, 0, 15, true) as $file => $r) {
                $colours = collect($r['colours'])->map(fn ($n, $c) => "{$c}:{$n}")->implode(', ');
                fwrite(STDERR, sprintf("  %-4d  %s%s\n", $r['arbitrary'] + $r['named'], $file, $colours ? "  [{$colours}]" : ''));
            }
            if (count($report) > 15) {
                fwrite(STDERR, '  ... and '.(count($report) - 15)." more files\n");
            }
            fwrite(STDERR, "\n");
        }

        $this->assertSame(0, $total, "New off-token colour usage — see the report above.");
    }
}
