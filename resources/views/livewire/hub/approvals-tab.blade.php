<div>
    {{-- ══ Stat strip ══
         This module opened straight into two lists with no number to scan
         first — the one thing every other module now leads with. --}}
    @php
        $approvedCount = $decided->where('status', 'approved')->count();
        $rejectedCount = $decided->where('status', 'rejected')->count();
        $revisionCount = $decided->where('status', 'needs_revision')->count();
        $moduleHex = \App\Models\Event::moduleColor('approvals');
    @endphp
    <x-stat-strip class="mb-4" :stats="[
        ['Pending', $pending->count(), 'clock', null, null, null, 'text-info-ink'],
        ['Approved', $approvedCount, 'check', null, null, null, 'text-emerald-600'],
        ['Rejected', $rejectedCount, 'flag', null, null, null, $rejectedCount ? 'text-red-700' : 'text-navy-900'],
        ['Needs revision', $revisionCount, 'clipboard', null, null, null, 'text-amber-700'],
    ]" />

    <div class="mb-4 flex items-center justify-between">
        <p class="text-xs text-muted">Types: budget · supplier · design · venue · agenda · client · payment · report</p>
        <button type="button" wire:click="$toggle('showForm')" class="btn-gold h-10 px-4 text-xs">＋ Request Approval</button>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="card mb-5 grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-4">
            <div class="sm:col-span-2">
                <label class="field-label !mb-1 !text-eyebrow" for="a-title">What needs approval?</label>
                <input id="a-title" type="text" wire:model="title" class="input h-10 text-sm" placeholder="e.g. Revised catering budget">
                @error('title') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="field-label !mb-1 !text-eyebrow" for="a-type">Type</label>
                <select id="a-type" wire:model="type" class="input h-10 text-sm">
                    @foreach (\App\Support\Taxonomy::options('approval_type') as $tk => $tl)<option value="{{ $tk }}">{{ $tl }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="field-label !mb-1 !text-eyebrow" for="a-notes">Notes</label>
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
            <h3 class="mb-4 flex items-center gap-2.5 pf text-base font-bold text-navy-900">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl" style="color: {{ $moduleHex }}; background: {{ $moduleHex }}15">
                    <x-icon name="identification" class="h-4 w-4" />
                </span>
                Pending Approval ({{ $pending->count() }})
            </h3>
            <ul class="space-y-3">
                @forelse ($pending as $approval)
                    <li class="rounded-xl border border-info/30 bg-info/5 px-4 py-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-navy-900">{{ $approval->title }}</p>
                            <span class="rounded-full bg-info/10 px-2 py-0.5 text-eyebrow font-bold uppercase text-info-ink">{{ $approval->type }}</span>
                        </div>
                        <p class="mt-1 text-xs text-muted">Requested by {{ $approval->requester?->name ?? '—' }} · {{ $approval->created_at->diffForHumans() }}</p>
                        @if ($approval->notes)<p class="mt-1 text-xs text-muted">{{ $approval->notes }}</p>@endif
                        @can('decide-approvals')
                            @if ($approval->requested_by === auth()->id())
                                <p class="mt-2.5 text-eyebrow font-semibold text-muted">You raised this — a different manager decides it.</p>
                            @else
                                <div class="mt-2.5 flex gap-2">
                                    <button type="button" wire:click="decide({{ $approval->id }}, 'approved')"
                                            class="rounded-lg bg-track/10 px-3 py-1.5 text-eyebrow font-bold text-emerald-700 transition hover:bg-track/20">✓ Approve</button>
                                    <button type="button" wire:click="decide({{ $approval->id }}, 'rejected')"
                                            class="rounded-lg bg-risk/10 px-3 py-1.5 text-eyebrow font-bold text-red-700 transition hover:bg-risk/20">✕ Reject</button>
                                    <button type="button" wire:click="decide({{ $approval->id }}, 'needs_revision')"
                                            class="rounded-lg bg-warn/10 px-3 py-1.5 text-eyebrow font-bold text-amber-700 transition hover:bg-warn/20">↺ Needs Revision</button>
                                </div>
                            @endif
                        @else
                            <p class="mt-2.5 text-eyebrow text-muted">Awaiting a manager's decision.</p>
                        @endcan
                    </li>
                @empty
                    <li class="flex items-center gap-2.5 rounded-xl bg-page/60 px-4 py-6 text-center">
                        <span class="mx-auto flex flex-col items-center gap-1.5 text-xs text-muted">
                            <x-icon name="check" class="h-4 w-4 text-emerald-500" />
                            Nothing awaiting approval — the queue is clear.
                        </span>
                    </li>
                @endforelse
            </ul>
        </div>

        <div class="card p-6">
            <h3 class="mb-4 flex items-center gap-2.5 pf text-base font-bold text-navy-900">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-navy-50 text-navy-500">
                    <x-icon name="archive" class="h-4 w-4" />
                </span>
                History
            </h3>
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
                    <li class="rounded-xl bg-page/60 px-4 py-6 text-center text-xs text-muted">No decisions yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
