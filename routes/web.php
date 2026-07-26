<?php

use App\Http\Controllers\AgendaProgramPdfController;
use App\Http\Controllers\AgendaTimelinePdfController;
use App\Http\Controllers\AttendeeTemplateController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BudgetPdfController;
use App\Http\Controllers\ContractDocumentPdfController;
use App\Http\Controllers\DailyMovementSchedulePdfController;
use App\Http\Controllers\DriverTripSheetPdfController;
use App\Http\Controllers\EquipmentPdfController;
use App\Http\Controllers\EventBriefPdfController;
use App\Http\Controllers\EventContractPdfController;
use App\Http\Controllers\EventDocumentController;
use App\Http\Controllers\EventHubController;
use App\Http\Controllers\ExhibitionFloorPdfController;
use App\Http\Controllers\MasterSchedulePdfController;
use App\Http\Controllers\PlanStudioPdfController;
use App\Http\Controllers\RoomEquipmentPdfController;
use App\Http\Controllers\RoomingListPdfController;
use App\Http\Controllers\RoomingTemplateController;
use App\Http\Controllers\RoomLayoutPdfController;
use App\Http\Controllers\RunOfShowController;
use App\Http\Controllers\RunOfShowPdfController;
use App\Http\Controllers\SponsorshipController;
use App\Http\Controllers\SupplierOrderPdfController;
use App\Http\Controllers\TransportManifestPdfController;
use App\Http\Controllers\TransportManifestTemplateController;
use App\Http\Controllers\TransportMasterPlanPdfController;
use App\Http\Controllers\TransportPlanTemplateController;
use App\Http\Controllers\VipTransferSheetPdfController;
use App\Livewire\ClientsManager;
use App\Livewire\CommandCenter;
use App\Livewire\CompanySettings;
use App\Livewire\DefaultsSettings;
use App\Livewire\EventCreate;
use App\Livewire\EventsIndex;
use App\Livewire\ExhibitionFloorPlan;
use App\Livewire\RequirementsCatalog;
use App\Livewire\RoomLayoutBuilder;
use App\Livewire\SponsorPackagesSettings;
use App\Livewire\TeamRoster;
use App\Livewire\TransportDispatch;
use App\Livewire\TransportLive;
use App\Livewire\TransportSettings;
use App\Livewire\VenuesManager;
use App\Models\Event;
use App\Models\EventSponsor;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\Task;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
});

Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {

    Route::get('/', CommandCenter::class)->name('home');

    // ORBIT design system — the visual source of truth (light-first · gold accent).
    // Served raw (its CSS has @media/@keyframes that Blade would misparse).
    Route::get('/design', fn () => response(
        file_get_contents(base_path('orbit-system.html'))
    )->header('Content-Type', 'text/html'))->name('design');

    // The living gallery — the same components rendered from the real Blade source.
    // Any new ORBIT component must appear here in the commit that creates it.
    Route::view('/design/gallery', 'orbit-gallery')->name('design.gallery');

    // The ORBIT shell assembled around a real event, for review before the
    // sidebar is retired. ?tab=venue etc. moves the orbit's active module.
    Route::view('/design/shell', 'orbit-shell-preview')->name('design.shell');

    /*
     * Bake-off: the same screen and the same real figures, three genuinely
     * different design directions. Throwaway prototypes — they share no CSS
     * with the app or with each other, so each is a real alternative rather
     * than a reskin. Pick one, bin the other two.
     */
    Route::get('/bakeoff/{variant}', function (string $variant) {
        abort_unless(in_array($variant, ['a', 'b', 'c'], true), 404);

        return view('bakeoff.'.$variant, \App\Support\BakeoffData::get());
    })->name('bakeoff');

    // A user's manual theme choice, for this session only. ThemePolicy decides
    // the default per module; this just records that they overrode it.
    Route::post('/theme-override', function (\Illuminate\Http\Request $request) {
        \App\Support\ThemePolicy::override((string) $request->input('theme'));

        return response()->noContent();
    })->name('theme.override');

    /*
     * Elite Command Canvas — the company-wide dashboard. Static data for now
     * (App\Support\CommandCanvasData); no Livewire, no backend coupling.
     */
    Route::view('/command-canvas', 'command-canvas')->name('command-canvas');

    Route::get('/events', EventsIndex::class)->name('events.index');

    /*
     * Three agenda documents, one job each — all rendered by headless Chrome
     * from the app's own design system:
     *   Programme       → what's on, for delegates
     *   Master Schedule → every session incl. crew, for the team
     *   Run of Show     → the cue sheet for show day
     */
    Route::get('/events/{event}/programme.pdf', AgendaProgramPdfController::class)
        ->whereNumber('event')->name('events.agenda.program.pdf');

    Route::get('/events/{event}/master-schedule.pdf', MasterSchedulePdfController::class)
        ->whereNumber('event')->name('events.agenda.master.pdf');

    Route::get('/events/{event}/timeline.pdf', AgendaTimelinePdfController::class)
        ->whereNumber('event')->name('events.agenda.timeline.pdf');

    Route::get('/events/{event}/run-of-show.pdf', RunOfShowPdfController::class)
        ->whereNumber('event')->name('events.run-of-show.pdf');

    Route::get('/events/{event}/run-of-show', RunOfShowController::class)
        ->whereNumber('event')->name('events.run-of-show');

    Route::get('/events/{event}/budget.pdf', BudgetPdfController::class)
        ->whereNumber('event')->name('events.budget.pdf');

    Route::get('/events/{event}/exhibition-floor', ExhibitionFloorPlan::class)
        ->whereNumber('event')->name('events.exhibition-floor');
    Route::get('/events/{event}/exhibition-floor.pdf', ExhibitionFloorPdfController::class)
        ->whereNumber('event')->name('events.exhibition-floor.pdf');

    Route::get('/events/{event}/sponsorship', [SponsorshipController::class, 'show'])
        ->whereNumber('event')->name('events.sponsorship');
    Route::get('/events/{event}/sponsorship.pdf', [SponsorshipController::class, 'pdf'])
        ->whereNumber('event')->name('events.sponsorship.pdf');

    Route::get('/events/{event}/plan.pdf', PlanStudioPdfController::class)
        ->whereNumber('event')->name('events.planning.pdf');

    Route::get('/events/{event}/rooming/{block}.pdf', RoomingListPdfController::class)
        ->whereNumber('event')->whereNumber('block')->name('events.rooming.pdf');

    Route::get('/events/{event}/rooming/{block}/template.xlsx', RoomingTemplateController::class)
        ->whereNumber('event')->whereNumber('block')->name('events.rooming.template');

    Route::get('/events/{event}/attendees/template.xlsx', AttendeeTemplateController::class)
        ->whereNumber('event')->name('events.attendees.template');

    Route::get('/events/{event}/transport/{transport}/template.xlsx', TransportManifestTemplateController::class)
        ->whereNumber('event')->whereNumber('transport')->name('events.transport.template');

    Route::get('/events/{event}/transport/plan-template.xlsx', TransportPlanTemplateController::class)
        ->whereNumber('event')->name('events.transport.plan-template');

    Route::get('/events/{event}/transport.pdf', TransportManifestPdfController::class)
        ->whereNumber('event')->name('events.transport.pdf');

    // Event-day operations: phone-first, its own route so it can be opened
    // directly on a phone without walking the hub.
    Route::get('/events/{event}/transport/live', TransportLive::class)
        ->whereNumber('event')->name('events.transport.live');

    // The dispatch board: lanes against a time axis, for planning and conflict-
    // checking on a bigger screen.
    Route::get('/events/{event}/transport/dispatch', TransportDispatch::class)
        ->whereNumber('event')->name('events.transport.dispatch');

    // The transport document suite — each has exactly one reader.
    Route::get('/events/{event}/transport/daily-schedule.pdf', DailyMovementSchedulePdfController::class)
        ->whereNumber('event')->name('events.transport.daily-schedule.pdf');

    Route::get('/events/{event}/transport/trip-sheets.pdf', DriverTripSheetPdfController::class)
        ->whereNumber('event')->name('events.transport.trip-sheet.pdf');

    Route::get('/events/{event}/transport/vip-sheets.pdf', VipTransferSheetPdfController::class)
        ->whereNumber('event')->name('events.transport.vip-sheet.pdf');

    // Whole-event documents: the client's plan and the vendor's order.
    Route::get('/events/{event}/transport/plan.pdf', TransportMasterPlanPdfController::class)
        ->whereNumber('event')->name('events.transport.master-plan.pdf');

    Route::get('/events/{event}/transport/supplier-order.pdf', SupplierOrderPdfController::class)
        ->whereNumber('event')->name('events.transport.supplier-order.pdf');

    Route::get('/events/{event}/documents/{document}/download', [EventDocumentController::class, 'download'])
        ->whereNumber('event')->whereNumber('document')->name('events.documents.download');
    Route::get('/events/{event}/documents/{document}/view', [EventDocumentController::class, 'view'])
        ->whereNumber('event')->whereNumber('document')->name('events.documents.view');

    Route::get('/events/{event}/brief.pdf', EventBriefPdfController::class)
        ->whereNumber('event')->name('events.brief.pdf');

    Route::get('/events/{event}/contract.pdf', EventContractPdfController::class)
        ->whereNumber('event')->name('events.contract.pdf');

    // Any other document — vendor, speaker, sponsorship, letter — through the
    // same identity. The client MSA keeps its richer sheet above.
    Route::get('/events/{event}/contracts/{contract}.pdf', ContractDocumentPdfController::class)
        ->whereNumber('event')->whereNumber('contract')->name('events.contract.doc.pdf');

    Route::get('/events/{event}/rooms/{room}/layout', RoomLayoutBuilder::class)
        ->whereNumber('event')->whereNumber('room')->name('events.room-layout');

    Route::get('/events/{event}/rooms/{room}/layout.pdf', RoomLayoutPdfController::class)
        ->whereNumber('event')->whereNumber('room')->name('events.room-layout.pdf');

    Route::get('/events/{event}/rooms/{room}/equipment.pdf', RoomEquipmentPdfController::class)
        ->whereNumber('event')->whereNumber('room')->name('events.room-equipment.pdf');

    Route::get('/events/{event}', [EventHubController::class, 'show'])
        ->whereNumber('event')->name('events.hub');

    Route::get('/events/create', EventCreate::class)->name('events.create');

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

    Route::get('/venues', VenuesManager::class)->name('venues.index');

    Route::get('/requirements', RequirementsCatalog::class)->name('requirements.index');
    Route::get('/requirements/pdf', EquipmentPdfController::class)->name('requirements.pdf');

    Route::get('/team', TeamRoster::class)->name('team.index');

    Route::get('/sponsors', fn () => view('modules.sponsors', [
        'events' => Event::whereNull('archived_at')->whereHas('sponsors')
            ->with(['sponsors'])->orderBy('starts_at')->get(),
        'total' => EventSponsor::sum('amount_cents'),
    ]))->name('sponsors.index');

    // Workspace Settings — hub + live master-data sections.
    Route::view('/settings', 'modules.settings')->name('settings.index');
    Route::get('/settings/clients', ClientsManager::class)->name('clients.index');
    Route::get('/settings/company', CompanySettings::class)->name('company.index');
    Route::get('/settings/defaults', DefaultsSettings::class)->name('defaults.index');
    Route::get('/settings/transport', TransportSettings::class)->name('transport-settings.index');
    Route::get('/settings/sponsor-packages', SponsorPackagesSettings::class)->name('sponsor-packages.index');

    // Modules still awaiting their build phase render the generic stub.
    foreach (['crm', 'finance', 'assets', 'reports', 'ai-assistant'] as $key) {
        $module = config("modules.nav.{$key}");

        Route::get($module['path'], fn () => view('modules.stub', ['module' => $module + ['key' => $key]]))
            ->name($module['route']);
    }
});
