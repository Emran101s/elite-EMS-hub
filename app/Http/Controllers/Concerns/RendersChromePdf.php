<?php

namespace App\Http\Controllers\Concerns;

use Spatie\Browsershot\Browsershot;

/**
 * Renders a Blade view to PDF with headless Chrome, inlining the app's compiled
 * Tailwind CSS so the export is pixel-identical to what the browser shows.
 */
trait RendersChromePdf
{
    protected function chrome(string $html): Browsershot
    {
        $shot = Browsershot::html($html)
            ->showBackground()            // keep gradients, cards and bar fills
            ->margins(0, 0, 0, 0)
            ->waitUntilNetworkIdle()      // let the webfont land before printing
            ->timeout(180)
            ->noSandbox();

        if ($node = config('browsershot.node_binary')) {
            $shot->setNodeBinary($node);
        }
        if ($npm = config('browsershot.npm_binary')) {
            $shot->setNpmBinary($npm);
        }

        return $shot;
    }

    /** The app's built Tailwind CSS, inlined so Chrome renders the exact same design. */
    protected function compiledCss(): string
    {
        $manifest = public_path('build/manifest.json');
        if (! is_file($manifest)) {
            return '';
        }

        $entries = json_decode(file_get_contents($manifest), true) ?: [];
        $css = '';

        foreach ($entries as $entry) {
            foreach ((array) ($entry['css'] ?? []) as $file) {
                $css .= @file_get_contents(public_path('build/'.$file)) ?: '';
            }
            if (str_ends_with($entry['file'] ?? '', '.css')) {
                $css .= @file_get_contents(public_path('build/'.$entry['file'])) ?: '';
            }
        }

        return $css;
    }
}
