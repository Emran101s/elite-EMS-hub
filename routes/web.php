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

    Route::get('/', \App\Livewire\CommandCenter::class)->name('home');

    Route::get('/events', \App\Livewire\EventsIndex::class)->name('events.index');

    /*
     * Three agenda documents, one job each — all rendered by headless Chrome
     * from the app's own design system:
     *   Programme       → what's on, for delegates
     *   Master Schedule → every session incl. crew, for the team
     *   Run of Show     → the cue sheet for show day
     */
    Route::get('/events/{event}/programme.pdf', \App\Http\Controllers\AgendaProgramPdfController::class)
        ->whereNumber('event')->name('events.agenda.program.pdf');

    Route::get('/events/{event}/master-schedule.pdf', \App\Http\Controllers\MasterSchedulePdfController::class)
        ->whereNumber('event')->name('events.agenda.master.pdf');

    Route::get('/events/{event}/run-of-show.pdf', \App\Http\Controllers\RunOfShowPdfController::class)
        ->whereNumber('event')->name('events.run-of-show.pdf');

    Route::get('/events/{event}/run-of-show', \App\Http\Controllers\RunOfShowController::class)
        ->whereNumber('event')->name('events.run-of-show');

    Route::get('/events/{event}/budget.pdf', \App\Http\Controllers\BudgetPdfController::class)
        ->whereNumber('event')->name('events.budget.pdf');

    Route::get('/events/{event}/exhibition-floor', \App\Livewire\ExhibitionFloorPlan::class)
        ->whereNumber('event')->name('events.exhibition-floor');
    Route::get('/events/{event}/exhibition-floor.pdf', \App\Http\Controllers\ExhibitionFloorPdfController::class)
        ->whereNumber('event')->name('events.exhibition-floor.pdf');

    Route::get('/events/{event}/sponsorship', [\App\Http\Controllers\SponsorshipController::class, 'show'])
        ->whereNumber('event')->name('events.sponsorship');
    Route::get('/events/{event}/sponsorship.pdf', [\App\Http\Controllers\SponsorshipController::class, 'pdf'])
        ->whereNumber('event')->name('events.sponsorship.pdf');

    Route::get('/events/{event}/plan.pdf', \App\Http\Controllers\PlanStudioPdfController::class)
        ->whereNumber('event')->name('events.planning.pdf');

    Route::get('/events/{event}/rooming/{block}.pdf', \App\Http\Controllers\RoomingListPdfController::class)
        ->whereNumber('event')->whereNumber('block')->name('events.rooming.pdf');

    Route::get('/events/{event}/transport.pdf', \App\Http\Controllers\TransportManifestPdfController::class)
        ->whereNumber('event')->name('events.transport.pdf');

    Route::get('/events/{event}/documents/{document}/download', [\App\Http\Controllers\EventDocumentController::class, 'download'])
        ->whereNumber('event')->whereNumber('document')->name('events.documents.download');
    Route::get('/events/{event}/documents/{document}/view', [\App\Http\Controllers\EventDocumentController::class, 'view'])
        ->whereNumber('event')->whereNumber('document')->name('events.documents.view');

    Route::get('/events/{event}/brief.pdf', \App\Http\Controllers\EventBriefPdfController::class)
        ->whereNumber('event')->name('events.brief.pdf');

    Route::get('/events/{event}/contract.pdf', \App\Http\Controllers\EventContractPdfController::class)
        ->whereNumber('event')->name('events.contract.pdf');

    Route::get('/events/{event}/rooms/{room}/layout', \App\Livewire\RoomLayoutBuilder::class)
        ->whereNumber('event')->whereNumber('room')->name('events.room-layout');

    Route::get('/events/{event}/rooms/{room}/layout.pdf', \App\Http\Controllers\RoomLayoutPdfController::class)
        ->whereNumber('event')->whereNumber('room')->name('events.room-layout.pdf');

    Route::get('/events/{event}/rooms/{room}/equipment.pdf', \App\Http\Controllers\RoomEquipmentPdfController::class)
        ->whereNumber('event')->whereNumber('room')->name('events.room-equipment.pdf');

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
        'projects' => Project::withCount('events')->orderBy('name')->get(),
    ]))->name('projects.index');

    Route::get('/tasks', fn () => view('modules.tasks', [
        'statusFilter' => request('status'),
        'tasks' => Task::with(['event', 'assignee'])
            ->when(request('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByRaw("status = 'done'")->orderBy('due_on')->paginate(25)->withQueryString(),
        // Keyed by the model's own stages, so a new status can never vanish here.
        'counts' => Task::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')
            ->pipe(fn ($by) => collect(Task::statuses())->mapWithKeys(fn ($s) => [$s => (int) ($by[$s] ?? 0)])),
    ]))->name('tasks.index');

    Route::get('/suppliers', fn () => view('modules.suppliers', [
        'suppliers' => Supplier::withCount('events')->orderByDesc('rating')->get(),
    ]))->name('suppliers.index');

    Route::get('/venues', \App\Livewire\VenuesManager::class)->name('venues.index');

    Route::get('/requirements', \App\Livewire\RequirementsCatalog::class)->name('requirements.index');
    Route::get('/requirements/pdf', \App\Http\Controllers\EquipmentPdfController::class)->name('requirements.pdf');

    Route::get('/team', \App\Livewire\TeamRoster::class)->name('team.index');

    Route::get('/sponsors', fn () => view('modules.sponsors', [
        'events' => Event::whereNull('archived_at')->whereHas('sponsors')
            ->with(['sponsors', 'avatar'])->orderBy('starts_at')->get(),
        'total' => \App\Models\EventSponsor::sum('amount_cents'),
    ]))->name('sponsors.index');

    // Workspace Settings — hub + live master-data sections.
    Route::view('/settings', 'modules.settings')->name('settings.index');
    Route::get('/settings/avatars', \App\Livewire\AvatarLibrary::class)->name('settings.avatars');
    Route::get('/settings/clients', \App\Livewire\ClientsManager::class)->name('clients.index');
    Route::get('/settings/company', \App\Livewire\CompanySettings::class)->name('company.index');
    Route::get('/settings/defaults', \App\Livewire\DefaultsSettings::class)->name('defaults.index');
    Route::get('/settings/transport', \App\Livewire\TransportSettings::class)->name('transport-settings.index');
    Route::get('/settings/sponsor-packages', \App\Livewire\SponsorPackagesSettings::class)->name('sponsor-packages.index');

    // Modules still awaiting their build phase render the generic stub.
    foreach (['crm', 'finance', 'assets', 'reports', 'ai-assistant'] as $key) {
        $module = config("modules.nav.{$key}");

        Route::get($module['path'], fn () => view('modules.stub', ['module' => $module + ['key' => $key]]))
            ->name($module['route']);
    }
});
