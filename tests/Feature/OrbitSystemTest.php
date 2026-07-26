<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guards ORBIT against drift.
 *
 * The old DesignSystemTest policed the navy/gold system it replaced. These
 * rules police the one we actually ship now, and each of them encodes a
 * mistake that was made at least once during the migration:
 *
 *  - a colour literal creeping back into a live view
 *  - --gold-lit used as a text colour, where it reads at 1.69:1
 *  - a component built but never added to /design/gallery
 *  - orbit.css imported unlayered, which silently overrides Tailwind utilities
 *  - the generated token/component files hand-edited instead of regenerated
 *
 * PDF documents and SVG avatar artwork are deliberately exempt — see
 * docs/orbit-migration-plan.md §5 for the reasoning.
 */
class OrbitSystemTest extends TestCase
{
    /** Live UI: everything a user clicks, excluding documents and artwork. */
    private function liveViews(): array
    {
        $out = [];
        $roots = [resource_path('views/livewire'), resource_path('views/components')];

        foreach ($roots as $root) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
            foreach ($it as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $path = str_replace(resource_path('views').'/', '', $file->getPathname());

                // Documents render standalone through headless Chrome, and the
                // event-type avatars are illustration, not interface.
                if (str_contains($path, 'avatars/') || str_contains($path, 'pdf')) {
                    continue;
                }

                $out[$path] = file_get_contents($file->getPathname());
            }
        }

        return $out;
    }

    public function test_no_live_view_contains_a_colour_literal(): void
    {
        foreach ($this->liveViews() as $path => $source) {
            preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $source, $m);

            $this->assertSame([], array_values(array_unique($m[0])),
                "{$path} hardcodes a colour. Status colour comes from App\\Support\\Tone; "
                .'everything else is an ORBIT token.');
        }
    }

    public function test_the_fill_gold_is_never_used_as_a_text_colour(): void
    {
        // --gold-lit on a light surface is 1.69:1. Same shape for every signal:
        // the base value reads, the -lit value fills.
        foreach ($this->liveViews() as $path => $source) {
            // On a --chrome surface the -lit value is the one that reads, which is
            // exactly what the dock does. Those uses opt out explicitly with a
            // trailing /* on-chrome */ so the intent is stated at the call site
            // rather than the rule being softened for everyone.
            $source = preg_replace('/color:\s*var\(--[a-z]+-lit\);\s*\/\* on-chrome \*\//i', '', $source);

            foreach (['gold', 'vital', 'ion', 'plasma', 'flare', 'critical'] as $tone) {
                $this->assertDoesNotMatchRegularExpression(
                    '/color:\s*var\(--'.$tone.'-lit\)/i',
                    $source,
                    "{$path} uses --{$tone}-lit as a text colour. The -lit values are fills; "
                    ."use var(--{$tone}) or Tone::var() to read."
                );
            }
        }
    }

    public function test_every_orbit_component_appears_in_the_design_gallery(): void
    {
        $gallery = file_get_contents(resource_path('views/orbit-gallery.blade.php'));

        // Components that are structural rather than showable on a gallery page.
        $exempt = ['sprite', 'icon', 'list', 'feed', 'chips', 'event-chip', 'topbar'];

        foreach (glob(resource_path('views/components/orbit/*.blade.php')) as $file) {
            $name = basename($file, '.blade.php');
            if (in_array($name, $exempt, true)) {
                continue;
            }

            $this->assertStringContainsString("<x-orbit.{$name}", $gallery,
                "<x-orbit.{$name}> is not in /design/gallery. Without the gallery the system "
                .'drifts within a quarter — add it in the same commit that creates it.');
        }
    }

    public function test_orbit_css_is_imported_into_a_cascade_layer(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        // orbit.css carries a global reset (button/a { color: inherit }). Tailwind v4
        // puts utilities in a layer, and unlayered CSS beats layered CSS regardless
        // of specificity — so an unlayered import silently kills text-* everywhere.
        $this->assertStringContainsString("@import './orbit.css' layer(", $css,
            'orbit.css must be imported into a cascade layer, or its reset overrides every Tailwind utility.');
    }

    public function test_the_generated_token_files_are_not_hand_edited(): void
    {
        foreach (['orbit-tokens.css', 'orbit.css'] as $file) {
            $source = file_get_contents(resource_path('css/'.$file));

            $this->assertStringContainsString('GENERATED', $source,
                "resources/css/{$file} lost its generated header — it is built from "
                .'orbit-system.html by setup-orbit.sh and must not be edited by hand.');
        }
    }

    public function test_the_two_golds_keep_their_contract(): void
    {
        $tokens = file_get_contents(resource_path('css/orbit-tokens.css'));

        // The read/fill pair is the rule most likely to be broken, so assert the
        // values the contrast work was done against rather than trusting a comment.
        $this->assertStringContainsString('--gold:      #8A6209', $tokens,
            'the reading gold changed; its 5.48:1 on white is what the system is built on');
        $this->assertStringContainsString('--gold-lit:  #E8B84B', $tokens,
            'the fill gold changed; dark text sits on it at 9.16:1');
    }

    public function test_tone_maps_status_to_colour_in_one_place(): void
    {
        $tone = file_get_contents(app_path('Support/Tone.php'));

        foreach (['forHealth', 'forTask', 'forVariance', 'forEventStage', 'forUsage'] as $method) {
            $this->assertStringContainsString("function {$method}", $tone,
                "Tone::{$method}() is the single place that decision is made");
        }

        $this->assertStringContainsString('function var(', $tone, 'Tone::var() returns the READ colour');
        $this->assertStringContainsString('function lit(', $tone, 'Tone::lit() returns the FILL colour');
    }
}
