<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * An export nobody can click may as well not exist. The room equipment prep
 * sheet shipped with a working route and no button anywhere in the app, so it
 * was invisible until someone went looking for it.
 */
class ExportButtonsTest extends TestCase
{
    /** Export routes that are reached another way and need no button of their own. */
    private const NO_BUTTON_NEEDED = [
        // The rooming list PDF is linked per block from the Accommodation tab,
        // which this scan already finds; nothing else is exempt today.
    ];

    public function test_every_pdf_export_route_has_a_button_in_the_ui(): void
    {
        $views = $this->allViewSource();
        $missing = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if (! $name || ! str_ends_with($name, '.pdf') || in_array($name, self::NO_BUTTON_NEEDED, true)) {
                continue;
            }

            // A button is any Blade view that builds a URL for the route.
            if (! str_contains($views, "route('{$name}'") && ! str_contains($views, "route(\"{$name}\"")) {
                $missing[] = $name;
            }
        }

        $this->assertSame([], $missing,
            'these export routes have no button in any view: '.implode(', ', $missing));
    }

    /** Every Blade template concatenated, so one scan covers the whole UI. */
    private function allViewSource(): string
    {
        $source = '';

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($files as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $source .= file_get_contents($file->getPathname());
            }
        }

        return $source;
    }
}
