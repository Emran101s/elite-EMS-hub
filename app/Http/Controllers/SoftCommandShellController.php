<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Phase 2 Soft Command shell preview.
 * Demo only — does not replace the live product layout yet.
 */
class SoftCommandShellController extends Controller
{
    public function __invoke(): View
    {
        return view('design.soft-command-shell');
    }
}
