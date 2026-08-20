@props(['status'])

@php
    // V7: same status→tone mapping, now rendered through the eo-pill-*
    // vocabulary instead of one-off Tailwind color utilities, so this and
    // <x-eo.status-pill>-style badges share one tone language.
    $tone = match ($status) {
        'on_track', 'completed', 'done', 'active', 'approved', 'paid', 'delivered',
        'final', 'live', 'mitigated', 'confirmed', 'contracted' => 'eo-pill-ok',

        'in_progress', 'doing', 'todo', 'review', 'planning', 'pending', 'on_hold', 'production', 'partial',
        'quoted', 'monitoring', 'waiting_speaker', 'needs_review', 'proposal',
        'requested', 'in_production' => 'eo-pill-warn',

        'at_risk', 'behind', 'urgent', 'rejected', 'escalated', 'issue',
        'cancelled', 'blocked', 'needs_revision' => 'eo-pill-risk',

        default => 'eo-pill-pending', // draft, closed, …
    };
@endphp

<span {{ $attributes->merge(['class' => $tone]) }}>
    {{ str($status)->replace('_', ' ')->title() }}
</span>
