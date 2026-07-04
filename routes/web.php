<?php

use App\Http\Controllers\Auth\LoginController;
use App\Models\Event;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
});

Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {

    Route::get('/', \App\Http\Controllers\CommandCenterController::class)->name('home');

    Route::get('/events', function () {
        $filtered = Event::with(['venue', 'avatar', 'client', 'projectManager'])
            ->withCount('tasks')
            ->when(request('q'), fn ($query, $q) => $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('city', 'like', "%{$q}%")
                ->orWhere('country', 'like', "%{$q}%")))
            ->when(request('type'), fn ($query, $type) => $query->where('type', $type))
            ->when(request('stage'), fn ($query, $stage) => $query->where('stage', $stage))
            ->orderBy('starts_at')
            ->get();

        return view('modules.events', [
            'events' => $filtered,
            'view' => in_array(request('view'), ['grid', 'list']) ? request('view') : 'grid',
            'kpis' => [
                'Total Events' => Event::count(),
                'Active' => Event::whereIn('stage', ['confirmed', 'planning', 'production'])->count(),
                'Upcoming' => Event::where('starts_at', '>', now())->count(),
                'Live' => Event::where('stage', 'live')->count(),
                'Completed' => Event::whereIn('stage', ['completed', 'closed'])->count(),
                'At Risk' => Event::whereIn('status', ['at_risk', 'behind'])->count(),
            ],
        ]);
    })->name('events.index');

    Route::get('/events/{event}', [\App\Http\Controllers\EventHubController::class, 'show'])
        ->whereNumber('event')->name('events.hub');

    Route::get('/events/create', \App\Livewire\EventCreate::class)->name('events.create');

    Route::get('/events/avatars', fn () => view('modules.avatar-library', [
        'category' => request('category'),
        'avatars' => \App\Models\EventAvatar::active()
            ->when(request('category'), fn ($query, $category) => $query->where('category', $category))
            ->orderBy('sort_order')
            ->get(),
    ]))->name('events.avatars');

    Route::get('/projects', fn () => view('modules.projects', [
        'projects' => Project::withCount(['events', 'tasks'])->orderBy('name')->get(),
    ]))->name('projects.index');

    Route::get('/tasks', fn () => view('modules.tasks', [
        'tasks' => Task::with(['event', 'assignee'])->orderByRaw("status = 'completed'")->orderBy('due_on')->paginate(25),
        'counts' => [
            'completed' => Task::where('status', 'completed')->count(),
            'in_progress' => Task::where('status', 'in_progress')->count(),
            'pending' => Task::where('status', 'pending')->count(),
        ],
    ]))->name('tasks.index');

    Route::get('/suppliers', fn () => view('modules.suppliers', [
        'suppliers' => Supplier::withCount('events')->orderByDesc('rating')->get(),
    ]))->name('suppliers.index');

    Route::get('/venues', fn () => view('modules.venues', [
        'venues' => Venue::withCount('events')->orderBy('name')->get(),
    ]))->name('venues.index');

    Route::get('/team', fn () => view('modules.team', [
        'members' => User::orderBy('name')->get(),
    ]))->name('team.index');

    // Modules still awaiting their build phase render the generic stub.
    foreach (['crm', 'finance', 'assets', 'reports', 'ai-assistant', 'settings'] as $key) {
        $module = config("modules.nav.{$key}");

        Route::get($module['path'], fn () => view('modules.stub', ['module' => $module + ['key' => $key]]))
            ->name($module['route']);
    }
});
