@php
    $pending = $event->approvals->where('status', 'pending');
    $decided = $event->approvals->where('status', '!=', 'pending');
@endphp

<div class="grid gap-6 lg:grid-cols-2">
    <div class="card p-6">
        <h3 class="mb-4 text-xs font-bold uppercase tracking-wide text-navy-900">Pending Approval ({{ $pending->count() }})</h3>
        <ul class="space-y-3">
            @forelse ($pending as $approval)
                <li class="rounded-xl border border-[#3B82F6]/30 bg-[#3B82F6]/5 px-4 py-3">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-navy-900">{{ $approval->title }}</p>
                        <span class="rounded-full bg-[#3B82F6]/10 px-2 py-0.5 text-[0.6rem] font-bold uppercase text-blue-700">{{ $approval->type }}</span>
                    </div>
                    <p class="mt-1 text-xs text-muted">Requested by {{ $approval->requester?->name ?? '—' }} · {{ $approval->created_at->diffForHumans() }}</p>
                </li>
            @empty
                <li class="text-xs text-muted">Nothing awaiting approval.</li>
            @endforelse
        </ul>
    </div>

    <div class="card p-6">
        <h3 class="mb-4 text-xs font-bold uppercase tracking-wide text-navy-900">History</h3>
        <ul class="space-y-3">
            @forelse ($decided as $approval)
                <li class="flex items-center justify-between gap-3 border-b border-line pb-3 last:border-0 last:pb-0">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-navy-900">{{ $approval->title }}</p>
                        <p class="text-xs text-muted">{{ str($approval->type)->title() }} · decided by {{ $approval->decider?->name ?? '—' }} {{ $approval->decided_at?->diffForHumans() }}</p>
                    </div>
                    <x-status-badge :status="$approval->status" />
                </li>
            @empty
                <li class="text-xs text-muted">No decisions yet.</li>
            @endforelse
        </ul>
    </div>
</div>
<p class="mt-4 text-xs text-muted">Approve / reject actions with notifications land in the approvals workflow iteration. Types: budget · supplier · design · venue · agenda · client · payment · report.</p>
