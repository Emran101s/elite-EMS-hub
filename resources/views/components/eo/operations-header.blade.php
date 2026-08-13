@props(['active', 'title', 'subtitle'])

@php
    use App\Models\Event;
    use App\Models\Requirement;
    use App\Models\Supplier;
    use App\Models\Venue;
    use Illuminate\Support\Facades\Route;

    // The same event_supplier pivot status ('issue') PortfolioAdvisor,
    // EventHealthService and the Priority Area already read per event —
    // summed across every event, not invented for this header.
    $openSupplierIssues = Event::query()
        // Qualified pivot column, not wherePivot(). Inside withCount() the
        // closure is handed a plain Builder, not the BelongsToMany, so
        // wherePivot('status', 'issue') falls through to Laravel's dynamic
        // where{Attribute} handler and compiles to `where "pivot" = 'status'`.
        // SQLite reads an unknown double-quoted identifier as a string literal,
        // so that silently evaluates false and the count always read 0;
        // Postgres correctly errors with 42703 and the page 500s.
        ->withCount(['suppliers as issues_count' => fn ($q) => $q->where('event_supplier.status', 'issue')])
        ->get()
        ->sum('issues_count');

    $tabs = [
        'suppliers' => ['label' => 'Suppliers', 'route' => 'suppliers.index'],
        'venues' => ['label' => 'Venues', 'route' => 'venues.index'],
        'requirements' => ['label' => 'Equipment', 'route' => 'requirements.index'],
    ];
@endphp

{{-- One shared header for the three Operations pages — same KPI row and the
     same two-click reach between them wherever you are, so the domain reads
     as Operations Command rather than three unrelated directories. --}}
<div class="mb-6 space-y-5">
    <x-eo.page-header eyebrow="Operations Command" :title="$title" :subtitle="$subtitle">
        <x-slot:actions>
            @foreach ($tabs as $key => $tab)
                @continue ($key === $active)
                @continue (! Route::has($tab['route']))
                <x-eo.button variant="ghost" size="sm" href="{{ route($tab['route']) }}">{{ $tab['label'] }} →</x-eo.button>
            @endforeach
            {{ $actions ?? '' }}
        </x-slot:actions>
    </x-eo.page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-eo.metric-pill label="Suppliers" :value="number_format(Supplier::count())" hint="Vendor directory" :tone="$active === 'suppliers' ? 'live' : null" />
        <x-eo.metric-pill label="Venues" :value="number_format(Venue::count())" hint="Locations on file" :tone="$active === 'venues' ? 'live' : null" />
        <x-eo.metric-pill label="Equipment" :value="number_format(Requirement::count())" hint="Catalog items" :tone="$active === 'requirements' ? 'live' : null" />
        <x-eo.metric-pill label="Open supplier issues" :value="number_format($openSupplierIssues)" hint="Flagged across live events" :tone="$openSupplierIssues > 0 ? 'warn' : 'ok'" />
    </div>
</div>
