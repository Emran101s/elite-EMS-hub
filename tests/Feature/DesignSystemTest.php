<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guards the design system against drift.
 *
 * The audit found 566 hardcoded hex values, 31 arbitrary font sizes and 12
 * hand-rolled modal shells. Unifying them is only worth doing if they cannot
 * quietly come back, so each rule below is scoped to the files already
 * converted and the allow-list shrinks as more modules are migrated.
 */
class DesignSystemTest extends TestCase
{
    /** Views converted to the design system. Add to this list as modules migrate. */
    private const CONVERTED = [
        'livewire/hub/transportation-tab.blade.php',
        'livewire/hub/accommodation-tab.blade.php',
        'livewire/hub/venue-tab.blade.php',
        'livewire/hub/budget-tab.blade.php',
        'livewire/hub/tasks-tab.blade.php',
        'livewire/command-center.blade.php',
        'livewire/events-index.blade.php',
        'livewire/partials/event-panel.blade.php',
        'livewire/avatar-library.blade.php',
        'livewire/clients-manager.blade.php',
        'livewire/team-roster.blade.php',
        'livewire/requirements-catalog.blade.php',
        'livewire/hub/sponsors-tab.blade.php',
        'livewire/hub/speakers-tab.blade.php',
        'livewire/hub/exhibition-tab.blade.php',
        'livewire/hub/attendees-tab.blade.php',
        'livewire/hub/agenda-tab.blade.php',
        'livewire/company-settings.blade.php',
        'livewire/room-layout-builder.blade.php',
        'livewire/defaults-settings.blade.php',
        'livewire/sponsor-packages-settings.blade.php',
        'livewire/transport-settings.blade.php',
        'livewire/exhibition-floor-plan.blade.php',
        'livewire/event-create.blade.php',
        'livewire/command-palette.blade.php',
        'livewire/hub/settings-tab.blade.php',
        'livewire/hub/brief-tab.blade.php',
        'livewire/hub/approvals-tab.blade.php',
        'livewire/hub/contract-tab.blade.php',
        'livewire/hub/risks-tab.blade.php',
        'livewire/hub/plan-studio.blade.php',
        'livewire/hub/partials/brief-section.blade.php',
        'livewire/hub/partials/plan-studio/timeline.blade.php',
        'livewire/hub/partials/plan-studio/gallery.blade.php',
        'livewire/hub/partials/plan-studio/tracks.blade.php',
        'livewire/hub/partials/plan-studio/actions.blade.php',
        'livewire/hub/partials/plan-studio/drawer.blade.php',
        'livewire/hub/partials/plan-studio/card.blade.php',
        'livewire/hub/partials/plan-studio/board.blade.php',
        'livewire/hub/partials/plan-studio/list.blade.php',
        'livewire/hub/partials/tasks-studio/timeline.blade.php',
        'livewire/hub/partials/tasks-studio/gallery.blade.php',
        'livewire/hub/partials/tasks-studio/actions.blade.php',
        'livewire/hub/partials/tasks-studio/drawer.blade.php',
        'livewire/hub/partials/tasks-studio/card.blade.php',
        'livewire/hub/partials/tasks-studio/board.blade.php',
        'livewire/hub/partials/tasks-studio/list.blade.php',
        'livewire/hub/module-documents.blade.php',
        'livewire/hub/partials/document-drawer.blade.php',
        'components/dock.blade.php',
        'components/modal.blade.php',
        'components/page-head.blade.php',
        'components/empty.blade.php',
        'components/field.blade.php',
    ];

    /**
     * SVG artwork (avatars, crests, charts) legitimately needs raw colour, and
     * PDF views are rendered by headless Chrome without the app stylesheet.
     */
    private const COLOUR_EXEMPT = ['components/avatars/', 'components/event-crest', 'events/'];

    private function converted(): array
    {
        return array_map(fn ($p) => [$p, file_get_contents(resource_path('views/'.$p))], self::CONVERTED);
    }

    public function test_converted_views_use_the_type_scale_not_arbitrary_sizes(): void
    {
        foreach ($this->converted() as [$path, $source]) {
            preg_match_all('/text-\[[0-9.]+rem\]/', $source, $m);

            $this->assertSame([], array_unique($m[0]),
                "{$path} uses arbitrary font sizes; use eyebrow/micro/xs/sm/base/h2/h1/display instead");
        }
    }

    public function test_converted_views_do_not_hardcode_brand_colours(): void
    {
        // Values that duplicate an existing token — the ones worth policing.
        $tokenised = ['#d4af37', '#0b1f3a', '#e2e8f0', '#64748b', '#eff3f8', '#061426',
            '#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#22c55e',
            '#f87171', '#34d399', '#60a5fa', '#fbbf24'];

        foreach ($this->converted() as [$path, $source]) {
            if (str_contains($path, 'avatars/')) {
                continue;
            }

            foreach ($tokenised as $hex) {
                $this->assertStringNotContainsStringIgnoringCase($hex, $source,
                    "{$path} hardcodes {$hex}; use the matching token");
            }
        }
    }

    public function test_only_the_modal_component_builds_a_modal_shell(): void
    {
        foreach ($this->converted() as [$path, $source]) {
            if ($path === 'components/modal.blade.php') {
                continue;
            }

            $this->assertStringNotContainsString('fixed inset-0 z-50', $source,
                "{$path} hand-rolls a modal; use <x-modal> so every dialog shares one backdrop and radius");
        }
    }

    public function test_the_shared_components_exist_and_expose_their_slots(): void
    {
        $expected = [
            'modal' => ['title', 'close', 'footer'],
            'page-head' => ['title', 'subtitle', 'actions'],
            'empty' => ['title', 'hint', 'actions'],
            'field' => ['label', 'error'],
        ];

        foreach ($expected as $component => $slots) {
            $source = file_get_contents(resource_path("views/components/{$component}.blade.php"));

            foreach ($slots as $slot) {
                $this->assertStringContainsString($slot, $source,
                    "<x-{$component}> should support a {$slot} slot/prop");
            }
        }
    }

    public function test_the_token_layer_defines_the_system(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        foreach ([
            '--color-success', '--color-warning', '--color-danger', '--color-info',
            '--text-eyebrow', '--text-micro', '--text-h1', '--text-display',
            '--radius-sm', '--radius-md', '--radius-lg',
            '--shadow-raise', '--shadow-float', '--shadow-overlay',
        ] as $token) {
            $this->assertStringContainsString($token, $css, "missing design token {$token}");
        }
    }
}
