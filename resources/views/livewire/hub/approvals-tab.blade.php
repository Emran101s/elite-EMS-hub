<div>
    @php
        $moduleHex = \App\Models\Event::moduleColor('approvals');
    @endphp

    {{-- Pending / Approved / Rejected duplicated the Universal Module
         Header exactly; "Needs revision" is still visible per-decision in
         History below, so nothing is lost by dropping the strip. --}}
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <p class="text-eyebrow text-eo-muted">Budget · supplier · design · venue · agenda · client · payment · report</p>
        <x-eo.button size="sm" wire:click="$toggle('showForm')">＋ Request Approval</x-eo.button>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="eo-soft-card mb-4 grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="sm:col-span-2">
                <label class="eo-label !mb-1" for="a-title">What needs approval?</label>
                <input id="a-title" type="text" wire:model="title" class="eo-input h-9 text-sm" placeholder="e.g. Revised catering budget">
                @error('title') <p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="eo-label !mb-1" for="a-type">Type</label>
                <select id="a-type" wire:model="type" class="eo-select h-9 text-sm">
                    @foreach (\App\Support\Taxonomy::options('approval_type') as $tk => $tl)<option value="{{ $tk }}">{{ $tl }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="eo-label !mb-1" for="a-notes">Notes</label>
                <input id="a-notes" type="text" wire:model="notes" class="eo-input h-9 text-sm" placeholder="Optional context">
            </div>
            <div>
                <label class="eo-label !mb-1" for="a-amount">Amount</label>
                <input id="a-amount" type="number" min="0" step="0.01" wire:model="amount" class="eo-input h-9 text-sm" placeholder="Optional">
                @error('amount') <p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p> @enderror
                @if ($threshold && in_array($type, \App\Models\EventApproval::AMOUNT_GATED_TYPES, true))
                    <p class="mt-1 text-eyebrow text-eo-muted">Over {{ $event->money($threshold) }} adds a required Admin sign-off step automatically.</p>
                @endif
            </div>

            <div class="sm:col-span-2 xl:col-span-4">
                <p class="eo-label !mb-1">Who signs off, in order</p>
                <p class="mb-2 text-eyebrow text-eo-muted">Leave a step's approver blank for "any manager" — the platform's default. Add a step to require a second, later decision.</p>
                <div class="space-y-1.5">
                    @foreach ($steps as $i => $step)
                        <div class="flex items-center gap-1.5">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-eo-bg text-eyebrow font-bold text-eo-muted">{{ $i + 1 }}</span>
                            <input type="text" wire:model="steps.{{ $i }}.label" class="eo-input h-9 flex-1 text-sm" placeholder="Step name (optional) — e.g. Finance">
                            <select wire:model="steps.{{ $i }}.approver_id" class="eo-select h-9 w-40 text-sm">
                                <option value="">Any manager</option>
                                @foreach ($managers as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
                            </select>
                            @if (count($steps) > 1)
                                <button type="button" wire:click="removeStep({{ $i }})" class="shrink-0 rounded-lg px-2 py-1.5 text-eyebrow font-bold text-eo-muted hover:bg-eo-risk-soft hover:text-eo-risk">✕</button>
                            @endif
                        </div>
                        @error('steps.'.$i.'.approver_id') <p class="ms-8 text-xs text-eo-risk-ink">{{ $message }}</p> @enderror
                    @endforeach
                </div>
                @error('steps') <p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p> @enderror
                <button type="button" wire:click="addStep" class="mt-1.5 text-eyebrow font-semibold text-eo-teal-ink hover:underline">＋ Add another step</button>
            </div>

            <div class="flex items-end justify-end gap-2 sm:col-span-2 xl:col-span-4">
                <button type="button" wire:click="$set('showForm', false)" class="h-9 rounded-xl px-4 text-xs font-semibold text-eo-muted hover:text-eo-text">Cancel</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="eo-btn-navy h-9 px-5 text-xs">
                    <x-busy target="save" busy="Submitting…">Submit Request</x-busy>
                </button>
            </div>
        </form>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- Pending queue --}}
        <div class="eo-soft-card overflow-hidden">
            <div class="flex items-center gap-2.5 border-b border-eo-line px-4 py-2.5">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg text-white shadow-sm" style="background: {{ $moduleHex }}">
                    <x-icon name="identification" class="h-3.5 w-3.5" />
                </span>
                <h3 class="text-sm font-bold text-eo-text">Pending</h3>
                <span class="eo-pill-warn rounded-full px-2 py-0.5 text-eyebrow font-bold tabular-nums">{{ $pending->count() }}</span>
            </div>

            @if ($pending->isEmpty())
                <x-empty icon="check" title="Queue is clear" hint="Nothing awaiting a decision." class="!rounded-none !border-0 !shadow-none" />
            @else
                <ul class="divide-y divide-eo-line">
                    @foreach ($pending as $approval)
                        @php
                            $steps = $approval->steps;
                            $current = $steps->firstWhere('status', 'pending');
                            $currentIndex = $current ? $steps->search(fn ($s) => $s->id === $current->id) : null;
                            $isChain = $steps->count() > 1;
                            $assignedElsewhere = $current && ! $current->decidableBy(auth()->user());
                        @endphp
                        <li class="px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-eo-text">{{ $approval->title }}</p>
                                    <p class="mt-0.5 text-eyebrow text-eo-muted">
                                        {{ str($approval->type)->title() }}
                                        · {{ $approval->requester?->name ?? '—' }}
                                        · {{ $approval->created_at->diffForHumans() }}
                                    </p>
                                    @if ($isChain && $current)
                                        <p class="mt-1 text-eyebrow font-semibold text-eo-text">Step {{ $currentIndex + 1 }} of {{ $steps->count() }} — awaiting {{ $current->assigneeLabel() }}</p>
                                        @php $done = $steps->whereIn('status', ['approved', 'rejected', 'needs_revision']); @endphp
                                        @if ($done->isNotEmpty())
                                            <p class="mt-0.5 text-eyebrow text-eo-muted">
                                                {{ $done->map(fn ($s) => $s->assigneeLabel().' ✓')->join(' · ') }}
                                            </p>
                                        @endif
                                    @endif
                                    @if ($approval->notes)
                                        <p class="mt-1 truncate text-eyebrow text-eo-text">{{ $approval->notes }}</p>
                                    @endif
                                </div>
                                <span class="eo-pill-warn shrink-0 rounded-full px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide">{{ $approval->type }}</span>
                            </div>

                            @can('decide-approvals')
                                @php
                                    $canDecide = $approval->requested_by !== auth()->id()
                                        && $current
                                        && $current->decidableBy(auth()->user());
                                    $canDelegate = $current && $current->delegatableBy(auth()->user(), $approval);
                                    $delegateTargets = $canDelegate
                                        ? $managers->filter(fn ($m) => $m->id !== auth()->id()
                                            && $current->canReceiveDelegation($m, $approval)
                                            && $m->id !== $current->approver_id)
                                        : collect();
                                @endphp
                                @if ($approval->requested_by === auth()->id() && ! $canDelegate)
                                    <p class="mt-2 text-eyebrow font-semibold text-eo-muted">You raised this — a different manager decides it.</p>
                                @elseif ($assignedElsewhere && ! $canDelegate)
                                    <p class="mt-2 text-eyebrow font-semibold text-eo-muted">
                                        @if ($current->approver_id)
                                            Assigned to {{ $current->assigneeLabel() }} — not yours to decide.
                                        @else
                                            Needs {{ $current->min_role }} rank or above — not yours to decide.
                                        @endif
                                    </p>
                                @else
                                    @if ($canDecide)
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            <button type="button" wire:click="decide({{ $approval->id }}, 'approved')"
                                                    class="rounded-md bg-white px-2 py-1 text-eyebrow font-bold text-eo-ok-ink ring-1 ring-eo-line hover:ring-eo-ok">✓ Approve</button>
                                            <button type="button" wire:click="decide({{ $approval->id }}, 'rejected')"
                                                    class="rounded-md bg-white px-2 py-1 text-eyebrow font-bold text-eo-risk-ink ring-1 ring-eo-line hover:ring-eo-risk">✕ Reject</button>
                                            <button type="button" wire:click="decide({{ $approval->id }}, 'needs_revision')"
                                                    class="rounded-md bg-white px-2 py-1 text-eyebrow font-bold text-eo-warn-ink ring-1 ring-eo-line hover:ring-eo-warn">↺ Revise</button>
                                        </div>
                                    @elseif ($assignedElsewhere)
                                        <p class="mt-2 text-eyebrow font-semibold text-eo-muted">
                                            @if ($current->approver_id)
                                                Assigned to {{ $current->assigneeLabel() }} — not yours to decide.
                                            @else
                                                Needs {{ $current->min_role }} rank or above — not yours to decide.
                                            @endif
                                        </p>
                                    @elseif ($approval->requested_by === auth()->id())
                                        <p class="mt-2 text-eyebrow font-semibold text-eo-muted">You raised this — a different manager decides it.</p>
                                    @endif

                                    @if ($canDelegate && $delegateTargets->isNotEmpty())
                                        <div class="mt-2">
                                            @if ($delegatingId === $approval->id)
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    <select wire:model="delegateTo" class="eo-select h-8 min-w-[10rem] flex-1 text-eyebrow">
                                                        <option value="">Hand off to…</option>
                                                        @foreach ($delegateTargets as $m)
                                                            <option value="{{ $m->id }}">{{ $m->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button type="button" wire:click="delegate({{ $approval->id }})"
                                                            class="rounded-md bg-eo-navy px-2 py-1 text-eyebrow font-bold text-white hover:bg-eo-navy-mid">Hand off</button>
                                                    <button type="button" wire:click="cancelDelegate"
                                                            class="rounded-md px-2 py-1 text-eyebrow font-semibold text-eo-muted hover:text-eo-text">Cancel</button>
                                                </div>
                                                @error('delegateTo') <p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p> @enderror
                                            @else
                                                <button type="button" wire:click="startDelegate({{ $approval->id }})"
                                                        class="text-eyebrow font-semibold text-eo-muted underline-offset-2 hover:text-eo-text hover:underline">
                                                    Hand off to someone else
                                                </button>
                                            @endif
                                        </div>
                                    @endif
                                @endif
                            @else
                                <p class="mt-2 text-eyebrow text-eo-muted">Awaiting a manager’s decision.</p>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- History --}}
        <div class="eo-soft-card overflow-hidden">
            <div class="flex items-center gap-2.5 border-b border-eo-line px-4 py-2.5">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-eo-bg text-eo-muted">
                    <x-icon name="archive" class="h-3.5 w-3.5" />
                </span>
                <h3 class="text-sm font-bold text-eo-text">History</h3>
                <span class="text-eyebrow font-bold tabular-nums text-eo-muted">{{ $decided->count() }}</span>
            </div>

            @if ($decided->isEmpty())
                <x-empty icon="archive" title="No decisions yet" hint="Approved, rejected and revised requests will show up here." class="!rounded-none !border-0 !shadow-none" />
            @else
                <ul class="divide-y divide-eo-line">
                    @foreach ($decided as $approval)
                        @php $isChain = $approval->steps->count() > 1; @endphp
                        <li class="px-4 py-2.5">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-eo-text">{{ $approval->title }}</p>
                                    <p class="truncate text-eyebrow text-eo-muted">
                                        {{ str($approval->type)->title() }}
                                        · {{ $approval->decider?->name ?? '—' }}
                                        · {{ $approval->decided_at?->diffForHumans() }}
                                        @if ($isChain) · {{ $approval->steps->count() }}-step chain @endif
                                    </p>
                                </div>
                                <x-status-badge :status="$approval->status" />
                            </div>
                            @if ($isChain)
                                <ol class="mt-1.5 space-y-0.5">
                                    @foreach ($approval->steps as $step)
                                        @php
                                            $stepTone = match ($step->status) {
                                                'approved' => 'text-eo-ok-ink',
                                                'rejected' => 'text-eo-risk-ink',
                                                'needs_revision' => 'text-eo-warn-ink',
                                                'skipped' => 'text-eo-line',
                                                default => 'text-eo-muted',
                                            };
                                            $stepMark = match ($step->status) {
                                                'approved' => '✓',
                                                'rejected' => '✕',
                                                'needs_revision' => '↺',
                                                'skipped' => '—',
                                                default => '·',
                                            };
                                        @endphp
                                        <li class="flex items-center gap-1.5 text-eyebrow {{ $stepTone }}">
                                            <span class="w-3 shrink-0 font-bold">{{ $stepMark }}</span>
                                            <span class="min-w-0 truncate">
                                                {{ $step->assigneeLabel() }}
                                                @if ($step->decider && $step->status !== 'skipped')
                                                    <span class="text-eo-muted">· {{ $step->decider->name }}</span>
                                                @endif
                                                <span class="text-eo-muted">· {{ str($step->status)->replace('_', ' ') }}</span>
                                            </span>
                                        </li>
                                    @endforeach
                                </ol>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
