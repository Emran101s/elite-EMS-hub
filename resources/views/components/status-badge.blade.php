@props(['status'])

@php
    // Same status→tone mapping, now on the shared .pill shape + brand tone
    // colours (navy/gold), so this and <x-eo.status-pill> share one tone
    // language without eo-soft-command.css's eo-pill-* classes.
    $tone = match ($status) {
        'on_track', 'completed', 'done', 'active', 'approved', 'paid', 'delivered',
        'final', 'live', 'mitigated', 'confirmed', 'contracted' => 'bg-success-soft text-success-ink',

        'in_progress', 'doing', 'todo', 'review', 'planning', 'pending', 'on_hold', 'production', 'partial',
        'quoted', 'monitoring', 'waiting_speaker', 'needs_review', 'proposal',
        'requested', 'in_production' => 'bg-warning-soft text-warning-ink',

        'at_risk', 'behind', 'urgent', 'rejected', 'escalated', 'issue',
        'cancelled', 'blocked', 'needs_revision' => 'bg-danger-soft text-danger-ink',

        default => 'bg-page text-muted', // draft, closed, …
    };
@endphp

<span {{ $attributes->class(['pill', $tone]) }}>
    {{ str($status)->replace('_', ' ')->title() }}
</span>
