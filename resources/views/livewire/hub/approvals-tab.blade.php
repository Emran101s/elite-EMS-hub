<div class="cx-canvas">
    @php
        $moduleHex = \App\Models\Event::moduleColor('approvals');
    @endphp

    {{-- Pending / Approved / Rejected duplicated the Universal Module
         Header exactly; "Needs revision" is still visible per-decision in
         History below, so nothing is lost by dropping the strip. --}}
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <p class="text-eyebrow text-muted">Budget · supplier · design · venue · agenda · client · payment · report</p>
        <button type="button" wire:click="$toggle('showForm')" class="cx-btn cx-btn-accent" style="height:34px">＋ Request Approval</button>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="cx-lcard mb-3 grid gap-3 p-3.5 sm:grid-cols-2 xl:grid-cols-4">
            <div class="sm:col-span-2">
                <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="a-title">What needs approval?</label>
                <input id="a-title" type="text" wire:model="title" class="h-9 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none" placeholder="e.g. Revised catering budget">
                @error('title') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="a-type">Type</label>
                <select id="a-type" wire:model="type" class="h-9 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none">
                    @foreach (\App\Support\Taxonomy::options('approval_type') as $tk => $tl)<option value="{{ $tk }}">{{ $tl }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="a-notes">Notes</label>
                <input id="a-notes" type="text" wire:model="notes" class="h-9 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none" placeholder="Optional context">
            </div>
            <div>
                <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="a-amount">Amount</label>
                <input id="a-amount" type="number" min="0" step="0.01" wire:model="amount" class="h-9 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none" placeholder="Optional">
                @error('amount') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
                @if ($threshold && in_array($type, \App\Models\EventApproval::AMOUNT_GATED_TYPES, true))
                    <p class="mt-1 text-eyebrow text-muted">Over {{ $event->money($threshold) }} adds a required Admin sign-off step automatically.</p>
                @endif
            </div>

            <div class="sm:col-span-2 xl:col-span-4">
                <p class="mb-1 text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Who signs off, in order</p>
                <p class="mb-2 text-eyebrow text-muted">Leave a step's approver blank for "any manager" — the platform's default. Add a step to require a second, later decision.</p>
                <div class="space-y-1.5">
                    @foreach ($steps as $i => $step)
                        <div class="flex items-center gap-1.5">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-page text-eyebrow font-bold text-muted">{{ $i + 1 }}</span>
                            <input type="text" wire:model="steps.{{ $i }}.label" class="h-9 flex-1 rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none" placeholder="Step name (optional) — e.g. Finance">
                            <select wire:model="steps.{{ $i }}.approver_id" class="h-9 w-40 rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none">
                                <option value="">Any manager</option>
                                @foreach ($managers as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
                            </select>
                            @if (count($steps) > 1)
                                <button type="button" wire:click="removeStep({{ $i }})" class="shrink-0 rounded-lg px-2 py-1.5 text-eyebrow font-bold text-muted hover:bg-danger-soft hover:text-danger-ink">✕</button>
                            @endif
                        </div>
                        @error('steps.'.$i.'.approver_id') <p class="ms-8 text-xs text-danger-ink">{{ $message }}</p> @enderror
                    @endforeach
                </div>
                @error('steps') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
                <button type="button" wire:click="addStep" class="mt-1.5 text-eyebrow font-semibold text-gold-700 hover:underline">＋ Add another step</button>
            </div>

            <div class="flex items-end justify-end gap-2 sm:col-span-2 xl:col-span-4">
                <button type="button" wire:click="$set('showForm', false)" class="h-9 rounded-full px-4 text-xs font-semibold text-muted hover:text-ink">Cancel</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="h-9 rounded-full bg-navy-900 px-5 text-xs font-bold text-white transition hover:bg-navy-800">
                    <x-busy target="save" busy="Submitting…">Submit Request</x-busy>
                </button>
            </div>
        </form>
    @endif

    <div class="grid gap-3 lg:grid-cols-2">
        {{-- Pending queue --}}
        <div class="cx-lcard">
            <div class="cx-lcard-head">
              <span class="flex items-center gap-2.5">
                <span class="cx-cathex" style="width:24px;height:26px;background: {{ $moduleHex }}">
                    <x-icon name="identification" class="h-3 w-3" />
                </span>
                <h3 class="text-[13px] font-bold text-ink">Pending</h3>
                <span class="rounded-full bg-warning-soft px-2 py-0.5 text-eyebrow font-bold tabular-nums text-warning-ink">{{ $pending->count() }}</span>
              </span>
            </div>

            @if ($pending->isEmpty())
                <div class="px-4 py-8 text-center"><p class="text-[13px] font-semibold text-ink">Queue is clear</p><p class="mt-1 text-xs text-muted">Nothing awaiting a decision.</p></div>
            @else
                <ul class="divide-y divide-line">
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
                                    <p class="text-sm font-semibold text-ink">{{ $approval->title }}</p>
                                    <p class="mt-0.5 text-eyebrow text-muted">
                                        {{ str($approval->type)->title() }}
                                        · {{ $approval->requester?->name ?? '—' }}
                                        · {{ $approval->created_at->diffForHumans() }}
                                    </p>
                                    @if ($isChain && $current)
                                        <p class="mt-1 text-eyebrow font-semibold text-ink">Step {{ $currentIndex + 1 }} of {{ $steps->count() }} — awaiting {{ $current->assigneeLabel() }}</p>
                                        @php $done = $steps->whereIn('status', ['approved', 'rejected', 'needs_revision']); @endphp
                                        @if ($done->isNotEmpty())
                                            <p class="mt-0.5 text-eyebrow text-muted">
                                                {{ $done->map(fn ($s) => $s->assigneeLabel().' ✓')->join(' · ') }}
                                            </p>
                                        @endif
                                    @endif
                                    @if ($approval->notes)
                                        <p class="mt-1 truncate text-eyebrow text-ink">{{ $approval->notes }}</p>
                                    @endif
                                </div>
                                <span class="shrink-0 rounded-full bg-warning-soft px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide text-warning-ink">{{ $approval->type }}</span>
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
                                    <p class="mt-2 text-eyebrow font-semibold text-muted">You raised this — a different manager decides it.</p>
                                @elseif ($assignedElsewhere && ! $canDelegate)
                                    <p class="mt-2 text-eyebrow font-semibold text-muted">
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
                                                    class="rounded-md bg-white px-2 py-1 text-eyebrow font-bold text-success-ink ring-1 ring-line hover:ring-success">✓ Approve</button>
                                            <button type="button" wire:click="decide({{ $approval->id }}, 'rejected')"
                                                    class="rounded-md bg-white px-2 py-1 text-eyebrow font-bold text-danger-ink ring-1 ring-line hover:ring-danger">✕ Reject</button>
                                            <button type="button" wire:click="decide({{ $approval->id }}, 'needs_revision')"
                                                    class="rounded-md bg-white px-2 py-1 text-eyebrow font-bold text-warning-ink ring-1 ring-line hover:ring-warning">↺ Revise</button>
                                        </div>
                                    @elseif ($assignedElsewhere)
                                        <p class="mt-2 text-eyebrow font-semibold text-muted">
                                            @if ($current->approver_id)
                                                Assigned to {{ $current->assigneeLabel() }} — not yours to decide.
                                            @else
                                                Needs {{ $current->min_role }} rank or above — not yours to decide.
                                            @endif
                                        </p>
                                    @elseif ($approval->requested_by === auth()->id())
                                        <p class="mt-2 text-eyebrow font-semibold text-muted">You raised this — a different manager decides it.</p>
                                    @endif

                                    @if ($canDelegate && $delegateTargets->isNotEmpty())
                                        <div class="mt-2">
                                            @if ($delegatingId === $approval->id)
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    <select wire:model="delegateTo" class="h-8 min-w-[10rem] flex-1 rounded-lg border border-line bg-white px-2 text-eyebrow text-ink focus:border-navy-300 focus:outline-none">
                                                        <option value="">Hand off to…</option>
                                                        @foreach ($delegateTargets as $m)
                                                            <option value="{{ $m->id }}">{{ $m->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button type="button" wire:click="delegate({{ $approval->id }})"
                                                            class="rounded-md bg-navy-900 px-2 py-1 text-eyebrow font-bold text-white hover:bg-navy-800">Hand off</button>
                                                    <button type="button" wire:click="cancelDelegate"
                                                            class="rounded-md px-2 py-1 text-eyebrow font-semibold text-muted hover:text-ink">Cancel</button>
                                                </div>
                                                @error('delegateTo') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
                                            @else
                                                <button type="button" wire:click="startDelegate({{ $approval->id }})"
                                                        class="text-eyebrow font-semibold text-muted underline-offset-2 hover:text-ink hover:underline">
                                                    Hand off to someone else
                                                </button>
                                            @endif
                                        </div>
                                    @endif
                                @endif
                            @else
                                <p class="mt-2 text-eyebrow text-muted">Awaiting a manager's decision.</p>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- History --}}
        <div class="cx-lcard">
            <div class="cx-lcard-head">
              <span class="flex items-center gap-2.5">
                <span class="cx-cathex" style="width:24px;height:26px;background: var(--cx-surface-3); color: var(--cx-muted)">
                    <x-icon name="archive" class="h-3 w-3" />
                </span>
                <h3 class="text-[13px] font-bold text-ink">History</h3>
                <span class="text-eyebrow font-bold tabular-nums text-muted">{{ $decided->count() }}</span>
              </span>
            </div>

            @if ($decided->isEmpty())
                <div class="px-4 py-8 text-center"><p class="text-[13px] font-semibold text-ink">No decisions yet</p><p class="mt-1 text-xs text-muted">Approved, rejected and revised requests will show up here.</p></div>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($decided as $approval)
                        @php $isChain = $approval->steps->count() > 1; @endphp
                        <li class="px-4 py-2.5">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-ink">{{ $approval->title }}</p>
                                    <p class="truncate text-eyebrow text-muted">
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
                                                'approved' => 'text-success-ink',
                                                'rejected' => 'text-danger-ink',
                                                'needs_revision' => 'text-warning-ink',
                                                'skipped' => 'text-line',
                                                default => 'text-muted',
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
                                                    <span class="text-muted">· {{ $step->decider->name }}</span>
                                                @endif
                                                <span class="text-muted">· {{ str($step->status)->replace('_', ' ') }}</span>
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
