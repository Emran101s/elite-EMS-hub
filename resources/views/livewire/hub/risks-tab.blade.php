<div>
    {{-- The Open/Critical/Resolved/Total figures already live in the
         Universal Module Header above this — repeating them here as a
         second stat strip was the exact "duplicated status information"
         the shell audit flags, so this opens straight into the register
         instead. --}}
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <p class="text-eyebrow text-eo-muted">Open risks with severity ≥ 20 cap Event Health at “At Risk”.</p>
        <x-eo.button size="sm" wire:click="$toggle('showForm')" class="h-9 px-3.5 text-xs">＋ Register Risk</x-eo.button>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="hubx-ws-main mb-4 grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-4">
            <div class="sm:col-span-2">
                <label class="eo-label !mb-1 !text-eyebrow" for="r-title">Risk</label>
                <input id="r-title" type="text" wire:model="title" class="eo-input h-10 text-sm" placeholder="e.g. Venue contract pending signature">
                @error('title') <p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="eo-label !mb-1 !text-eyebrow" for="r-cat">Category</label>
                <select id="r-cat" wire:model="category" class="eo-input h-10 text-sm">
                    @foreach (\App\Support\Taxonomy::options('risk_category') as $cv => $cl)<option value="{{ $cv }}">{{ $cl }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="eo-label !mb-1 !text-eyebrow" for="r-owner">Owner</label>
                <select id="r-owner" wire:model="owner_id" class="eo-input h-10 text-sm">
                    <option value="">—</option>
                    @foreach ($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="eo-label !mb-1 !text-eyebrow" for="r-prob">Probability (1–5)</label>
                <select id="r-prob" wire:model="probability" class="eo-input h-10 text-sm">
                    @foreach (range(1, 5) as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="eo-label !mb-1 !text-eyebrow" for="r-imp">Impact (1–5)</label>
                <select id="r-imp" wire:model="impact" class="eo-input h-10 text-sm">
                    @foreach (range(1, 5) as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="eo-label !mb-1 !text-eyebrow" for="r-mit">Mitigation plan</label>
                <input id="r-mit" type="text" wire:model="mitigation" class="eo-input h-10 text-sm" placeholder="What are we doing about it?">
            </div>
            <div class="flex items-end justify-end gap-2 sm:col-span-2 xl:col-span-4">
                <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-eo-muted hover:text-eo-text">Cancel</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="eo-btn-navy h-10 px-5 text-xs">
                    <span wire:loading.remove wire:target="save">Save Risk</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
            </div>
        </form>
    @endif

    @if ($risks->isEmpty())
        <x-empty icon="flag" title="Risk register is empty"
                 hint="Register the first risk so severity feeds Event Health — open risks with severity ≥ 20 cap the score at “At Risk”.">
            <x-slot:actions>
                <x-eo.button size="sm" wire:click="$set('showForm', true)">＋ Register the first risk</x-eo.button>
            </x-slot:actions>
        </x-empty>
    @else
        @php $selected = $risks->firstWhere('id', $selectedRiskId); @endphp
        <div class="hubx-ws">
            <div class="hubx-ws-main">
                <div class="hidden grid-cols-12 gap-3 bg-eo-workspace/40 px-4 py-2 text-eyebrow font-semibold uppercase tracking-wide text-eo-muted md:grid">
                    <span class="col-span-5">Risk</span>
                    <span class="col-span-2">Category</span>
                    <span class="col-span-2 text-center">Severity (P×I)</span>
                    <span class="col-span-2">Owner</span>
                    <span class="col-span-1 text-right">Status</span>
                </div>
                @foreach ($risks as $risk)
                    <button type="button" wire:click="selectRisk({{ $risk->id }})"
                            class="hubx-ws-row grid-cols-2 items-center gap-2 px-4 py-2.5 md:grid-cols-12 md:gap-3 {{ $selectedRiskId === $risk->id ? 'is-selected' : '' }}">
                        <div class="col-span-2 flex items-start gap-2 md:col-span-5">
                            <span class="hubx-ws-severity-dot mt-1.5 {{ $risk->severity() >= 15 ? 'is-critical' : ($risk->severity() >= 8 ? 'is-warning' : 'is-quiet') }}"></span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-eo-text">{{ $risk->title }}</p>
                                @if ($risk->mitigation)<p class="mt-0.5 truncate text-eyebrow text-eo-muted">{{ $risk->mitigation }}</p>@endif
                            </div>
                        </div>
                        <p class="text-xs text-eo-muted md:col-span-2">{{ str($risk->category)->replace('_', ' ')->title() }}</p>
                        <div class="md:col-span-2 md:text-center">
                            <span @class([
                                    'inline-flex rounded-full px-2 py-0.5 text-eyebrow font-bold',
                                    'bg-eo-risk-soft text-eo-risk-ink' => $risk->severity() >= 15,
                                    'bg-eo-warn-soft text-eo-warn-ink' => $risk->severity() >= 8 && $risk->severity() < 15,
                                    'bg-eo-bg text-eo-muted' => $risk->severity() < 8,
                                ])>{{ $risk->probability }}×{{ $risk->impact }} = {{ $risk->severity() }}</span>
                        </div>
                        <p class="truncate text-xs text-eo-muted md:col-span-2">{{ $risk->owner?->name ?? '—' }}</p>
                        <div class="flex justify-start md:col-span-1 md:justify-end">
                            <x-status-badge :status="$risk->status" />
                        </div>
                    </button>
                @endforeach
            </div>

            <div class="hubx-ws-side">
                @if ($selected)
                    <div class="hubx-ws-side-head">
                        <div class="min-w-0">
                            <p class="hubx-ws-side-label">Selected Risk</p>
                            <p class="hubx-ws-side-title">{{ $selected->title }}</p>
                        </div>
                        <button type="button" wire:click="selectRisk({{ $selected->id }})" class="hubx-ws-side-close" title="Close">
                            <span aria-hidden="true">✕</span>
                        </button>
                    </div>

                    @if ($selected->mitigation)
                        <p class="hubx-ws-side-note">{{ $selected->mitigation }}</p>
                    @endif

                    <div class="hubx-ws-side-meta">
                        <div class="hubx-ws-side-stat">
                            <span class="hubx-ws-side-stat-label">Category</span>
                            <span class="hubx-ws-side-stat-value">{{ str($selected->category)->replace('_', ' ')->title() }}</span>
                        </div>
                        <div class="hubx-ws-side-stat">
                            <span class="hubx-ws-side-stat-label">Severity</span>
                            <span class="hubx-ws-side-stat-value">{{ $selected->probability }}×{{ $selected->impact }} = {{ $selected->severity() }}</span>
                        </div>
                        <div class="hubx-ws-side-stat">
                            <span class="hubx-ws-side-stat-label">Owner</span>
                            <span class="hubx-ws-side-stat-value">{{ $selected->owner?->name ?? 'Unassigned' }}</span>
                        </div>
                        <div class="hubx-ws-side-stat">
                            <span class="hubx-ws-side-stat-label">Status</span>
                            <span class="hubx-ws-side-stat-value"><x-status-badge :status="$selected->status" /></span>
                        </div>
                    </div>

                    <div class="hubx-ws-side-actions">
                        @if ($selected->isOpen())
                            <button type="button" wire:click="setStatus({{ $selected->id }}, 'mitigated')" class="hubx-ws-side-action is-teal">✓ Mitigated</button>
                            <button type="button" wire:click="setStatus({{ $selected->id }}, 'escalated')" class="hubx-ws-side-action is-risk">▲ Escalate</button>
                            <button type="button" wire:click="setStatus({{ $selected->id }}, 'closed')" class="hubx-ws-side-action">✕ Close</button>
                        @else
                            <button type="button" wire:click="setStatus({{ $selected->id }}, 'open')" class="hubx-ws-side-action is-teal">↺ Reopen</button>
                        @endif
                    </div>
                @else
                    <div class="hubx-ws-side-empty">
                        <span class="hubx-ws-side-empty-icon"><x-icon name="flag" class="h-4 w-4" /></span>
                        <p class="text-[12px] font-semibold text-white/80">No risk selected</p>
                        <p class="text-[11px]">Select a risk from the register to see its full detail and take action.</p>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
