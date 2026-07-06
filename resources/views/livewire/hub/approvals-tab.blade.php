<div>
    <div class="mb-4 flex items-center justify-between">
        <p class="text-xs text-muted">Types: budget · supplier · design · venue · agenda · client · payment · report</p>
        <button type="button" wire:click="$toggle('showForm')" class="btn-gold h-10 px-4 text-xs">＋ Request Approval</button>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="card mb-5 grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-4">
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-medium text-navy-800" for="a-title">What needs approval?</label>
                <input id="a-title" type="text" wire:model="title" class="input h-10 text-sm" placeholder="e.g. Revised catering budget">
                @error('title') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-navy-800" for="a-type">Type</label>
                <select id="a-type" wire:model="type" class="input h-10 text-sm">
                    @foreach (\App\Models\EventApproval::TYPES as $typeOption)<option value="{{ $typeOption }}">{{ str($typeOption)->title() }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-navy-800" for="a-notes">Notes</label>
                <input id="a-notes" type="text" wire:model="notes" class="input h-10 text-sm" placeholder="Optional context">
            </div>
            <div class="flex items-end justify-end gap-2 sm:col-span-2 xl:col-span-4">
                <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
                <button type="submit" class="btn-navy h-10 px-5 text-xs">Submit Request</button>
            </div>
        </form>
    @endif

    <div class="grid gap-5 lg:grid-cols-2">
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
                        @if ($approval->notes)<p class="mt-1 text-xs text-muted">{{ $approval->notes }}</p>@endif
                        <div class="mt-2.5 flex gap-2">
                            <button type="button" wire:click="decide({{ $approval->id }}, 'approved')"
                                    class="rounded-lg bg-track/10 px-3 py-1.5 text-[0.65rem] font-bold text-emerald-700 transition hover:bg-track/20">✓ Approve</button>
                            <button type="button" wire:click="decide({{ $approval->id }}, 'rejected')"
                                    class="rounded-lg bg-risk/10 px-3 py-1.5 text-[0.65rem] font-bold text-red-700 transition hover:bg-risk/20">✕ Reject</button>
                            <button type="button" wire:click="decide({{ $approval->id }}, 'needs_revision')"
                                    class="rounded-lg bg-warn/10 px-3 py-1.5 text-[0.65rem] font-bold text-amber-700 transition hover:bg-warn/20">↺ Needs Revision</button>
                        </div>
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
</div>
