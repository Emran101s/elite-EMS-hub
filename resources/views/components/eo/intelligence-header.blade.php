@props(['active', 'title', 'subtitle', 'attentionCount' => null, 'attentionHint' => 'Rule-based, worst first'])

@php
    use App\Models\Event;
    use Illuminate\Support\Facades\Route;

    $eventsInBook = Event::whereNull('archived_at')->count();

    $tabs = [
        'reports' => ['label' => 'Reports', 'route' => 'reports.index'],
        'briefing' => ['label' => 'Command Briefing', 'route' => 'ai.index'],
    ];
@endphp

{{-- One shared header for Reports and Command Briefing — same domain
     identity and the same one-click reach between them, so Intelligence
     reads as a workspace rather than two unrelated links. --}}
<div class="mb-6 space-y-3">
    <x-eo.page-header eyebrow="Intelligence" :title="$title" :subtitle="$subtitle">
        <x-slot:actions>
            @foreach ($tabs as $key => $tab)
                @continue ($key === $active)
                @continue (! Route::has($tab['route']))
                <x-eo.button variant="ghost" size="sm" href="{{ route($tab['route']) }}">{{ $tab['label'] }} →</x-eo.button>
            @endforeach
            {{ $actions ?? '' }}
        </x-slot:actions>
    </x-eo.page-header>

    <div class="grid grid-cols-2 gap-3 {{ $attentionCount === null ? 'sm:max-w-xs' : 'sm:grid-cols-2 sm:max-w-md' }}">
        <x-eo.metric-pill label="Events in book" :value="number_format($eventsInBook)" hint="Active portfolio" />
        @if ($attentionCount !== null)
            {{-- The hint says what the figure counts, because the briefing
                 below it lists MORE than this: informational notes are in the
                 list and not in this number, so "10" sat above "14 of 14
                 shown" with nothing to explain the gap. --}}
            <x-eo.metric-pill label="Needs action" :value="number_format($attentionCount)" :hint="$attentionHint" :tone="$attentionCount > 0 ? 'warn' : 'ok'" />
        @endif
    </div>

    <p class="text-[11px] font-semibold text-muted">
        Command Briefing is a rule-based advisor, not a model — every flagged item names the record it came from.
    </p>
</div>
