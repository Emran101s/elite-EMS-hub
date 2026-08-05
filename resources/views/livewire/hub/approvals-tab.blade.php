<div>
    @php
        $approvedCount = $decided->where('status', 'approved')->count();
        $rejectedCount = $decided->where('status', 'rejected')->count();
        $revisionCount = $decided->where('status', 'needs_revision')->count();
        $moduleHex = \App\Models\Event::moduleColor('approvals');
    @endphp

    <x-stat-strip class="mb-3" :stats="[
        ['Pending', $pending->count(), 'clock', null, null, null, $pending->isNotEmpty() ? 'text-amber-700' : 'text-navy-900'],
        ['Approved', $approvedCount, 'check', null, null, null, 'text-emerald-600'],
        ['Rejected', $rejectedCount, 'flag', null, null, null, $rejectedCount ? 'text-red-700' : 'text-navy-900'],
        ['Needs revision', $revisionCount, 'clipboard', null, null, null, $revisionCount ? 'text-amber-700' : 'text-navy-900'],
    ]" />

    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <p class="text-eyebrow text-muted">Budget · supplier · design · venue · agenda · client · payment · report</p>
        <button type="button" wire:click="$toggle('showForm')" class="btn-gold h-9 px-3.5 text-xs">＋ Request Approval</button>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="sm:col-span-2">
                <label class="field-label !mb-1 !text-eyebrow" for="a-title">What needs approval?</label>
                <input id="a-title" type="text" wire:model="title" class="input h-9 text-sm" placeholder="e.g. Revised catering budget">
                @error('title') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="field-label !mb-1 !text-eyebrow" for="a-type">Type</label>
                <select id="a-type" wire:model="type" class="input h-9 text-sm">
                    @foreach (\App\Support\Taxonomy::options('approval_type') as $tk => $tl)<option value="{{ $tk }}">{{ $tl }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="field-label !mb-1 !text-eyebrow" for="a-notes">Notes</label>
                <input id="a-notes" type="text" wire:model="notes" class="input h-9 text-sm" placeholder="Optional context">
            </div>
            <div class="flex items-end justify-end gap-2 sm:col-span-2 xl:col-span-4">
                <button type="button" wire:click="$set('showForm', false)" class="h-9 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="btn-navy h-9 px-5 text-xs">
                    <span wire:loading.remove wire:target="save">Submit Request</span>
                    <span wire:loading wire:target="save">Submitting…</span>
                </button>
            </div>
        </form>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- Pending queue --}}
        <div class="card overflow-hidden">
            <div class="flex items-center gap-2.5 border-b border-line px-4 py-2.5">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg text-white shadow-sm" style="background: {{ $moduleHex }}">
                    <x-icon name="identification" class="h-3.5 w-3.5" />
                </span>
                <h3 class="pf text-sm font-bold text-navy-900">Pending</h3>
                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-eyebrow font-bold tabular-nums text-amber-800 ring-1 ring-amber-200">{{ $pending->count() }}</span>
            </div>

            @if ($pending->isEmpty())
                <div class="flex flex-col items-center gap-1.5 px-4 py-10 text-center">
                    <x-icon name="check" class="h-4 w-4 text-emerald-500" />
                    <p class="text-xs text-muted">Queue is clear — nothing awaiting a decision.</p>
                </div>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($pending as $approval)
                        <li class="px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-navy-900">{{ $approval->title }}</p>
                                    <p class="mt-0.5 text-eyebrow text-muted">
                                        {{ str($approval->type)->title() }}
                                        · {{ $approval->requester?->name ?? '—' }}
                                        · {{ $approval->created_at->diffForHumans() }}
                                    </p>
                                    @if ($approval->notes)
                                        <p class="mt-1 truncate text-eyebrow text-navy-600">{{ $approval->notes }}</p>
                                    @endif
                                </div>
                                <span class="shrink-0 rounded-full bg-amber-50 px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide text-amber-800 ring-1 ring-amber-200">{{ $approval->type }}</span>
                            </div>

                            @can('decide-approvals')
                                @if ($approval->requested_by === auth()->id())
                                    <p class="mt-2 text-eyebrow font-semibold text-muted">You raised this — a different manager decides it.</p>
                                @else
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        <button type="button" wire:click="decide({{ $approval->id }}, 'approved')"
                                                class="rounded-md bg-white px-2 py-1 text-eyebrow font-bold text-emerald-700 ring-1 ring-line hover:ring-emerald-300">✓ Approve</button>
                                        <button type="button" wire:click="decide({{ $approval->id }}, 'rejected')"
                                                class="rounded-md bg-white px-2 py-1 text-eyebrow font-bold text-red-700 ring-1 ring-line hover:ring-red-300">✕ Reject</button>
                                        <button type="button" wire:click="decide({{ $approval->id }}, 'needs_revision')"
                                                class="rounded-md bg-white px-2 py-1 text-eyebrow font-bold text-amber-700 ring-1 ring-line hover:ring-amber-300">↺ Revise</button>
                                    </div>
                                @endif
                            @else
                                <p class="mt-2 text-eyebrow text-muted">Awaiting a manager’s decision.</p>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- History --}}
        <div class="card overflow-hidden">
            <div class="flex items-center gap-2.5 border-b border-line px-4 py-2.5">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-navy-100 text-navy-600">
                    <x-icon name="archive" class="h-3.5 w-3.5" />
                </span>
                <h3 class="pf text-sm font-bold text-navy-900">History</h3>
                <span class="text-eyebrow font-bold tabular-nums text-navy-400">{{ $decided->count() }}</span>
            </div>

            @if ($decided->isEmpty())
                <p class="px-4 py-10 text-center text-xs text-muted">No decisions yet.</p>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($decided as $approval)
                        <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-navy-900">{{ $approval->title }}</p>
                                <p class="truncate text-eyebrow text-muted">
                                    {{ str($approval->type)->title() }}
                                    · {{ $approval->decider?->name ?? '—' }}
                                    · {{ $approval->decided_at?->diffForHumans() }}
                                </p>
                            </div>
                            <x-status-badge :status="$approval->status" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
