<?php

namespace App\Http\Controllers;

use App\Services\CommandCenterService;
use Illuminate\View\View;

class CommandCenterController extends Controller
{
    public function __invoke(CommandCenterService $pulse): View
    {
        return view('modules.command-center', [
            'stats' => $pulse->stats(),
            'islands' => $pulse->islands(),
            'alerts' => $pulse->alerts(),
            'utilization' => $pulse->utilization(),
            'budget' => $pulse->budgetByHealth(),
            'spend' => $pulse->portfolioSpend(),
            'taskCounts' => $pulse->taskCounts(),
            'deadlines' => $pulse->deadlines(),
            'topSuppliers' => $pulse->topSuppliers(),
            'statusBars' => $pulse->statusBars(),
        ]);
    }
}
