@php
    use App\Support\NavPanel;
    use App\Models\Deal;
    use App\Models\Event;
    use App\Models\EventApproval;
    use App\Models\EventContract;
    use App\Models\EventContractPayment;
    use App\Models\Invoice;
    use App\Models\Proposal;
    use App\Models\Task;

    $area = NavPanel::currentArea();
    $sections = NavPanel::panel();

    if (! in_array($area, NavPanel::PANEL_COVERS, true)) {
        $extra = NavPanel::sections($area);
        if ($extra->isNotEmpty()) {
            $sections = $extra;
        }
    }

    $onPortfolio = request()->routeIs('events.index');
    $onHub = request()->routeIs('events.hub', 'events.show');
    $onCommand = request()->routeIs('home', 'operations-room');
    $onCrm = $area === 'crm' || request()->routeIs('crm.*', 'clients.*', 'proposals.*', 'contracts.*');
    $onFinance = $area === 'finance' || request()->routeIs('finance.*', 'invoices.*', 'payments.*');
    $showEventViews = in_array($area, ['workspace', 'events'], true) || $onPortfolio || $onHub;
    $showCommercialViews = $onCrm || $onFinance;
    $showSmartViews = $showEventViews || $showCommercialViews;

    $queue = request('queue');
    $stage = request('stage');
    $tab = request('tab', 'all');
    $state = request('state', 'all');
    $status = request('status', 'all');

    $liveCount = Event::whereNull('archived_at')->where('stage', 'live')->count();
    $weekCount = Event::whereNull('archived_at')
        ->whereBetween('starts_at', [now()->startOfWeek(), now()->endOfWeek()])
        ->count();
    $approvalCount = EventApproval::where('status', 'pending')->count();
    $paymentCount = EventContractPayment::query()
        ->whereColumn('paid_cents', '<', 'amount_cents')
        ->count();
    $vipCount = Event::whereNull('archived_at')
        ->whereIn('type', ['vip_reception', 'embassy_event', 'private_dinner'])
        ->count();
    $highValueCount = Event::whereNull('archived_at')->where('budget_cents', '>=', 10000000)->count();
    $openTasks = Task::whereNotIn('status', ['done', 'cancelled'])->count();
    $openDeals = Deal::whereIn('stage', Deal::OPEN)->count();
    $staleDeals = Deal::whereIn('stage', Deal::OPEN)
        ->whereNotNull('expected_close_on')
        ->where('expected_close_on', '<', now()->toDateString())
        ->count();
    $liveOffers = Proposal::where('status', 'sent')->count();
    $awaitingSig = EventContract::whereIn('status', ['sent', 'partially_signed'])->count();
    $overdueInvoices = Invoice::query()->get()->filter(fn ($i) => $i->state() === 'overdue')->count();
    $overduePays = EventContractPayment::query()->get()->filter(fn ($p) => $p->status() === 'overdue')->count();

    if ($showCommercialViews && $onFinance) {
        $smartQueues = [
            ['label' => 'Overdue Invoices', 'href' => route('invoices.index', ['state' => 'overdue']), 'count' => $overdueInvoices ?: null, 'active' => request()->routeIs('invoices.*') && $state === 'overdue', 'tone' => $overdueInvoices ? 'risk' : null],
            ['label' => 'Partial Payments', 'href' => route('invoices.index', ['state' => 'partial']), 'count' => null, 'active' => request()->routeIs('invoices.*') && $state === 'partial', 'tone' => 'warn'],
            ['label' => 'Draft Invoices', 'href' => route('invoices.index', ['state' => 'draft']), 'count' => null, 'active' => request()->routeIs('invoices.*') && $state === 'draft', 'tone' => null],
            ['label' => 'Overdue Installments', 'href' => route('payments.index', ['status' => 'overdue']), 'count' => $overduePays ?: null, 'active' => request()->routeIs('payments.*') && $status === 'overdue', 'tone' => $overduePays ? 'risk' : null],
            ['label' => 'Pending Collection', 'href' => route('payments.index', ['status' => 'pending']), 'count' => null, 'active' => request()->routeIs('payments.*') && $status === 'pending', 'tone' => 'warn'],
            ['label' => 'Portfolio P&L', 'href' => route('finance.index'), 'count' => null, 'active' => request()->routeIs('finance.index'), 'tone' => null],
        ];
        $dnaViews = [
            ['label' => 'All Invoices', 'href' => route('invoices.index'), 'active' => request()->routeIs('invoices.index') && $state === 'all'],
            ['label' => 'All Payments', 'href' => route('payments.index'), 'active' => request()->routeIs('payments.index') && $status === 'all'],
        ];
        $contextActions = [
            ['label' => 'Raise Invoice', 'href' => route('invoices.index'), 'active' => false],
            ['label' => 'Reconcile Payments', 'href' => route('payments.index', ['status' => 'overdue']), 'active' => false],
            ['label' => 'Deal Pipeline', 'href' => route('crm.index'), 'active' => false],
        ];
        $intelTitle = 'Finance pulse';
        $intelLine1 = $overdueInvoices.' overdue invoices · '.$overduePays.' overdue pays';
        $intelLine2 = $paymentCount.' outstanding installments';
    } elseif ($showCommercialViews) {
        $smartQueues = [
            ['label' => 'Open Deals', 'href' => route('crm.index'), 'count' => $openDeals, 'active' => request()->routeIs('crm.index'), 'tone' => null],
            ['label' => 'Stale Deals', 'href' => route('crm.index'), 'count' => $staleDeals ?: null, 'active' => false, 'tone' => $staleDeals ? 'risk' : null],
            ['label' => 'Offers Out', 'href' => route('proposals.index', ['state' => 'sent']), 'count' => $liveOffers ?: null, 'active' => request()->routeIs('proposals.*') && $state === 'sent', 'tone' => 'warn'],
            ['label' => 'Expiring Soon', 'href' => route('proposals.index', ['state' => 'expired']), 'count' => null, 'active' => request()->routeIs('proposals.*') && $state === 'expired', 'tone' => 'risk'],
            ['label' => 'Awaiting Signature', 'href' => route('contracts.index', ['status' => 'sent']), 'count' => $awaitingSig ?: null, 'active' => request()->routeIs('contracts.*') && $status === 'sent', 'tone' => 'warn'],
            ['label' => 'Accepted Offers', 'href' => route('proposals.index', ['state' => 'accepted']), 'count' => null, 'active' => request()->routeIs('proposals.*') && $state === 'accepted', 'tone' => null],
        ];
        $dnaViews = [
            ['label' => 'Clients', 'href' => route('clients.index'), 'active' => request()->routeIs('clients.*', 'crm.client')],
            ['label' => 'Proposals', 'href' => route('proposals.index'), 'active' => request()->routeIs('proposals.*') && $state === 'all'],
            ['label' => 'Contracts', 'href' => route('contracts.index'), 'active' => request()->routeIs('contracts.*') && $status === 'all'],
        ];
        $contextActions = [
            ['label' => 'New Deal', 'href' => route('crm.index'), 'active' => false],
            ['label' => 'New Proposal', 'href' => route('proposals.index'), 'active' => false],
            ['label' => 'Finance Command', 'href' => route('finance.index'), 'active' => false],
        ];
        $intelTitle = 'Commercial pulse';
        $intelLine1 = $openDeals.' open deals · '.$staleDeals.' stale';
        $intelLine2 = $liveOffers.' offers out · '.$awaitingSig.' awaiting signature';
    } else {
        $smartQueues = [
            ['label' => 'Live Events', 'href' => route('events.index', ['stage' => 'live']), 'count' => $liveCount, 'active' => $onPortfolio && $stage === 'live', 'tone' => null],
            ['label' => 'At Risk', 'href' => route('events.index', ['queue' => 'at_risk', 'sort' => 'health']), 'count' => null, 'active' => $onPortfolio && $queue === 'at_risk', 'tone' => 'risk'],
            ['label' => 'Awaiting Approval', 'href' => route('events.index', ['queue' => 'awaiting_approval']), 'count' => $approvalCount, 'active' => $onPortfolio && $queue === 'awaiting_approval', 'tone' => $approvalCount ? 'warn' : null],
            ['label' => 'Payment Required', 'href' => route('events.index', ['queue' => 'payment']), 'count' => $paymentCount ?: null, 'active' => $onPortfolio && $queue === 'payment', 'tone' => $paymentCount ? 'warn' : null],
            ['label' => 'This Week', 'href' => route('events.index', ['queue' => 'this_week']), 'count' => $weekCount, 'active' => $onPortfolio && $queue === 'this_week', 'tone' => null],
            ['label' => 'VIP Events', 'href' => route('events.index', ['tab' => 'vip']), 'count' => $vipCount ?: null, 'active' => $onPortfolio && $tab === 'vip' && ! $queue, 'tone' => null],
            ['label' => 'High Value Events', 'href' => route('events.index', ['queue' => 'high_value', 'sort' => 'budget']), 'count' => $highValueCount ?: null, 'active' => $onPortfolio && $queue === 'high_value', 'tone' => null],
        ];
        $dnaViews = [
            ['label' => 'Conferences', 'href' => route('events.index', ['tab' => 'conference']), 'active' => $onPortfolio && $tab === 'conference' && ! $queue],
            ['label' => 'Exhibitions', 'href' => route('events.index', ['tab' => 'exhibition']), 'active' => $onPortfolio && $tab === 'exhibition' && ! $queue],
            ['label' => 'Workshops', 'href' => route('events.index', ['tab' => 'workshop']), 'active' => $onPortfolio && $tab === 'workshop' && ! $queue],
            ['label' => 'Galas', 'href' => route('events.index', ['tab' => 'gala']), 'active' => $onPortfolio && $tab === 'gala' && ! $queue],
        ];
        $contextActions = [
            ['label' => 'All Events', 'href' => route('events.index'), 'active' => $onPortfolio && ! $queue && ! $stage && ($tab === 'all' || $tab === null)],
            ['label' => 'New Mission', 'href' => route('events.create'), 'active' => request()->routeIs('events.create')],
            ['label' => 'Mission Radar', 'href' => route('events.index').'#mission-radar', 'active' => false],
            ['label' => 'Planning Board', 'href' => \Illuminate\Support\Facades\Route::has('planning.index') ? route('planning.index') : route('tasks.index'), 'active' => request()->routeIs('planning.*', 'tasks.*')],
        ];
        $intelTitle = 'Ops pulse';
        $intelLine1 = $liveCount.' live · '.$weekCount.' this week';
        $intelLine2 = $openTasks.' open tasks · '.$approvalCount.' awaiting approval';
    }
@endphp

{{-- Soft Command ContextSidebar — Smart Views · queues · domain intelligence. --}}
<aside {{ $attributes->class(['eo-context-sidebar']) }} aria-label="Context">
    <div class="border-b border-white/8 px-5 py-5">
        <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-eo-gold-soft/90">Elite Orbit</p>
        <p class="mt-1 text-[17px] font-semibold tracking-tight text-white">{{ NavPanel::areaLabel($area) }}</p>
        <p class="mt-1 text-[12px] text-white/45">
            @if ($showCommercialViews && $onFinance)
                Finance Command · Collection · Reconciliation
            @elseif ($showCommercialViews)
                Commercial Command · Deals · Offers · Contracts
            @else
                Summits · Forums · Exhibitions · VIP
            @endif
        </p>
    </div>

    <div class="flex-1 space-y-5 overflow-y-auto px-3 py-4">
        @if ($showSmartViews)
            <div>
                <p class="mb-2 px-2 text-[10px] font-bold uppercase tracking-[0.16em] text-white/35">Smart Views</p>
                <div class="space-y-0.5">
                    @foreach ($smartQueues as $item)
                        <a href="{{ $item['href'] }}" @class(['eo-context-queue', 'is-active' => $item['active']])>
                            <span class="truncate">{{ $item['label'] }}</span>
                            @if ($item['count'] !== null)
                                <span @class([
                                    'eo-context-queue-count',
                                    'is-risk' => ($item['tone'] ?? null) === 'risk',
                                    'is-warn' => ($item['tone'] ?? null) === 'warn',
                                ])>{{ $item['count'] }}</span>
                            @elseif (($item['tone'] ?? null) === 'risk')
                                <span class="eo-context-queue-count is-risk">!</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>

            <div>
                <p class="mb-2 px-2 text-[10px] font-bold uppercase tracking-[0.16em] text-white/35">
                    {{ $showCommercialViews ? 'Domain Map' : 'Event DNA' }}
                </p>
                <div class="space-y-0.5">
                    @foreach ($dnaViews as $item)
                        <a href="{{ $item['href'] }}" @class(['eo-context-queue', 'is-active' => $item['active']])>
                            <span class="truncate">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div>
                <p class="mb-2 px-2 text-[10px] font-bold uppercase tracking-[0.16em] text-white/35">Context Actions</p>
                <div class="space-y-0.5">
                    @foreach ($contextActions as $item)
                        @if ($item['href'])
                            <a href="{{ $item['href'] }}" @class(['eo-context-queue', 'is-active' => $item['active']])>
                                <span class="truncate">{{ $item['label'] }}</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>

            <div>
                <p class="mb-2 px-2 text-[10px] font-bold uppercase tracking-[0.16em] text-white/35">Domain Intelligence</p>
                <div class="mx-1 rounded-2xl bg-white/5 px-3 py-3 shadow-[inset_0_0_0_1px_rgba(255,255,255,0.06)]">
                    <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-eo-teal-lit/80">{{ $intelTitle }}</p>
                    <p class="mt-2 text-[13px] font-semibold text-white/85">{{ $intelLine1 }}</p>
                    <p class="mt-1 text-[12px] text-white/45">{{ $intelLine2 }}</p>
                </div>
            </div>
        @endif

        @if ($showCommercialViews || (! $showSmartViews))
            @foreach ($sections as $section)
                <div>
                    <p class="mb-2 px-2 text-[10px] font-bold uppercase tracking-[0.16em] text-white/35">{{ $section['label'] }}</p>
                    <div class="space-y-1">
                        @foreach ($section['items'] as $item)
                            <a href="{{ $item['href'] }}" @class(['eo-context-link', 'is-active' => $item['active']])>
                                <x-icon :name="$item['icon']" class="h-4 w-4 shrink-0 opacity-80" />
                                <span class="truncate">{{ $item['label'] }}</span>
                                @if (! empty($item['count']))
                                    <span class="ml-auto rounded-full bg-white/10 px-1.5 py-0.5 text-[10px] font-bold text-white/70">{{ $item['count'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @elseif ($showEventViews)
            <div>
                <p class="mb-2 px-2 text-[10px] font-bold uppercase tracking-[0.16em] text-white/35">Platform Map</p>
                <div class="space-y-1">
                    @foreach ($sections->take(2) as $section)
                        @foreach ($section['items'] as $item)
                            <a href="{{ $item['href'] }}" @class(['eo-context-link', 'is-active' => $item['active']])>
                                <x-icon :name="$item['icon']" class="h-4 w-4 shrink-0 opacity-80" />
                                <span class="truncate">{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="border-t border-white/8 px-4 py-4">
        @if (\Illuminate\Support\Facades\Route::has('settings.index'))
            <a href="{{ route('settings.index') }}" @class(['eo-context-link', 'is-active' => request()->routeIs('settings.*')])>
                <x-icon name="cog" class="h-4 w-4" />
                <span>Settings</span>
            </a>
        @endif
    </div>
</aside>
