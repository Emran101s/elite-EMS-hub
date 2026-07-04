<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
});

Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    foreach (config('modules.nav') as $key => $module) {
        $view = $key === 'command-center' ? 'modules.command-center' : 'modules.stub';

        Route::get($module['path'], fn () => view($view, ['module' => $module + ['key' => $key]]))
            ->name($module['route']);
    }
});
