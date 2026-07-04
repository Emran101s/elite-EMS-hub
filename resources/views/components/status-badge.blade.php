@props(['status'])

@php
    $tone = match ($status) {
        'on_track', 'completed', 'active' => 'bg-track/10 text-emerald-700 ring-track/30',
        'in_progress', 'planning', 'pending', 'on_hold' => 'bg-warn/10 text-amber-700 ring-warn/30',
        'at_risk', 'behind', 'urgent' => 'bg-risk/10 text-red-700 ring-risk/30',
        default => 'bg-navy-50 text-navy-600 ring-navy-200',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {$tone}"]) }}>
    {{ str($status)->replace('_', ' ')->title() }}
</span>
