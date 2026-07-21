@props(['status'])

@php
    $tone = match ($status) {
        'on_track', 'completed', 'done', 'active', 'approved', 'paid', 'delivered',
        'final', 'live', 'mitigated', 'confirmed', 'contracted' => 'bg-track/10 text-emerald-700 ring-track/30',

        'in_progress', 'doing', 'todo', 'review', 'planning', 'pending', 'on_hold', 'production', 'partial',
        'quoted', 'monitoring', 'waiting_speaker', 'needs_review', 'proposal',
        'requested', 'in_production' => 'bg-warn/10 text-amber-700 ring-warn/30',

        'at_risk', 'behind', 'urgent', 'rejected', 'escalated', 'issue',
        'cancelled', 'blocked', 'needs_revision' => 'bg-risk/10 text-red-700 ring-risk/30',

        default => 'bg-navy-50 text-navy-600 ring-navy-200', // draft, closed, …
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {$tone}"]) }}>
    {{ str($status)->replace('_', ' ')->title() }}
</span>
