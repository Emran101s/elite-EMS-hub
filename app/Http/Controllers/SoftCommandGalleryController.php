<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Phase 1 Soft Command design-system gallery.
 * Demo only — does not redesign live product pages.
 */
class SoftCommandGalleryController extends Controller
{
    public function __invoke(): View
    {
        return view('design.soft-command-gallery');
    }
}
