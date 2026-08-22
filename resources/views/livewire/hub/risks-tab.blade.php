<div>
    {{-- The Open/Critical/Resolved/Total figures already live in the
         Universal Module Header above this — repeating them here as a
         second stat strip was the exact "duplicated status information"
         the shell audit flags, so this opens straight into the register
         instead. --}}
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <p class="text-eyebrow text-muted">Open risks with severity ≥ 20 cap Event Health at "At Risk".</p>
        <button type="button" wire:click="$toggle('showForm')" class="h-9 rounded-full bg-gold-500 px-3.5 text-[12px] font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">＋ Register Risk</button>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mb-4 grid gap-3 rounded-lg border border-line bg-white p-5 sm:grid-cols-2 xl:grid-cols-4">
            <div class="sm:col-span-2">
                <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="r-title">Risk</label>
                <input id="r-title" type="text" wire:model="title" class="h-10 w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none" placeholder="e.g. Venue contract pending signature">
                @error('title') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="r-cat">Category</label>
                <select id="r-cat" wire:model="category" class="h-10 w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink focus:border-navy-300 focus:outline-none">
                    @foreach (\App\Support\Taxonomy::options('risk_category') as $cv => $cl)<option value="{{ $cv }}">{{ $cl }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="r-owner">Owner</label>
                <select id="r-owner" wire:model="owner_id" class="h-10 w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink focus:border-navy-300 focus:outline-none">
                    <option value="">—</option>
                    @foreach ($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="r-prob">Probability (1–5)</label>
                <select id="r-prob" wire:model="probability" class="h-10 w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink focus:border-navy-300 focus:outline-none">
                    @foreach (range(1, 5) as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="r-imp">Impact (1–5)</label>
                <select id="r-imp" wire:model="impact" class="h-10 w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink focus:border-navy-300 focus:outline-none">
                    @foreach (range(1, 5) as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="r-mit">Mitigation plan</label>
                <input id="r-mit" type="text" wire:model="mitigation" class="h-10 w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none" placeholder="What are we doing about it?">
            </div>
            <div class="flex items-end justify-end gap-2 sm:col-span-2 xl:col-span-4">
                <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-lg px-4 text-xs font-semibold text-muted hover:text-ink">Cancel</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="h-10 rounded-full bg-gold-500 px-5 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">
                    <span wire:loading.remove wire:target="save">Save Risk</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
            </div>
        </form>
    @endif

    @if ($risks->isEmpty())
        <x-empty icon="flag" title="Risk register is empty"
                 hint="Register the first risk so severity feeds Event Health — open risks with severity ≥ 20 cap the score at &quot;At Risk&quot;.">
            <x-slot:actions>
                <x-eo.button size="sm" wire:click="$set('showForm', true)">＋ Register the first risk</x-eo.button>
            </x-slot:actions>
        </x-empty>
    @else
        @php $selected = $risks->firstWhere('id', $selectedRiskId); @endphp
        <x-hub-content.split>
            <x-hub-content.split-list>
                <x-slot:columns>
                    <span class="col-span-5">Risk</span>
                    <span class="col-span-2">Category</span>
                    <span class="col-span-2 text-center">Severity (P×I)</span>
                    <span class="col-span-2">Owner</span>
                    <span class="col-span-1 text-right">Status</span>
                </x-slot:columns>

                @foreach ($risks as $risk)
                    <button type="button" wire:click="selectRisk({{ $risk->id }})" class="w-full text-start">
                        <x-hub-content.split-row :active="$selectedRiskId === $risk->id" class="grid grid-cols-2 items-center gap-2 md:grid-cols-12 md:gap-3">
                            <div class="col-span-2 flex items-start gap-2 md:col-span-5">
                                <span @class([
                                    'mt-1.5 h-2 w-2 shrink-0 rounded-full',
                                    'bg-danger' => $risk->severity() >= 15,
                                    'bg-warning' => $risk->severity() >= 8 && $risk->severity() < 15,
                                    'bg-line' => $risk->severity() < 8,
                                ])></span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-ink">{{ $risk->title }}</p>
                                    @if ($risk->mitigation)<p class="mt-0.5 truncate text-eyebrow text-muted">{{ $risk->mitigation }}</p>@endif
                                </div>
                            </div>
                            <p class="text-xs text-muted md:col-span-2">{{ str($risk->category)->replace('_', ' ')->title() }}</p>
                            <div class="md:col-span-2 md:text-center">
                                <span @class([
                                        'inline-flex rounded-full px-2 py-0.5 text-eyebrow font-bold',
                                        'bg-danger-soft text-danger-ink' => $risk->severity() >= 15,
                                        'bg-warning-soft text-warning-ink' => $risk->severity() >= 8 && $risk->severity() < 15,
                                        'bg-page text-muted' => $risk->severity() < 8,
                                    ])>{{ $risk->probability }}×{{ $risk->impact }} = {{ $risk->severity() }}</span>
                            </div>
                            <p class="truncate text-xs text-muted md:col-span-2">{{ $risk->owner?->name ?? '—' }}</p>
                            <div class="flex justify-start md:col-span-1 md:justify-end">
                                <x-status-badge :status="$risk->status" />
                            </div>
                        </x-hub-content.split-row>
                    </button>
                @endforeach
            </x-hub-content.split-list>

            <x-hub-content.split-detail
                :selected="$selected"
                label="Selected Risk"
                :title="$selected?->title"
                :close-action="$selected ? 'selectRisk('.$selected->id.')' : null"
                empty-icon="flag"
                empty-title="No risk selected"
                empty-hint="Select a risk from the register to see its full detail and take action."
            >
                @if ($selected)
                    @if ($selected->mitigation)
                        <p class="mt-1.5 text-[11.5px] leading-relaxed text-white/60">{{ $selected->mitigation }}</p>
                    @endif

                    <div class="mt-3.5 space-y-2">
                        <div class="ehc-detail-stat"><span class="text-white/50">Category</span><span class="font-bold text-white">{{ str($selected->category)->replace('_', ' ')->title() }}</span></div>
                        <div class="ehc-detail-stat"><span class="text-white/50">Severity</span><span class="font-bold text-white">{{ $selected->probability }}×{{ $selected->impact }} = {{ $selected->severity() }}</span></div>
                        <div class="ehc-detail-stat"><span class="text-white/50">Owner</span><span class="font-bold text-white">{{ $selected->owner?->name ?? 'Unassigned' }}</span></div>
                        <div class="ehc-detail-stat"><span class="text-white/50">Status</span><span class="font-bold text-white"><x-status-badge :status="$selected->status" /></span></div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-1.5">
                        @if ($selected->isOpen())
                            <button type="button" wire:click="setStatus({{ $selected->id }}, 'mitigated')" class="ehc-detail-action is-gold">✓ Mitigated</button>
                            <button type="button" wire:click="setStatus({{ $selected->id }}, 'escalated')" class="ehc-detail-action is-danger">▲ Escalate</button>
                            <button type="button" wire:click="setStatus({{ $selected->id }}, 'closed')" class="ehc-detail-action">✕ Close</button>
                        @else
                            <button type="button" wire:click="setStatus({{ $selected->id }}, 'open')" class="ehc-detail-action is-gold">↺ Reopen</button>
                        @endif
                    </div>
                @endif
            </x-hub-content.split-detail>
        </x-hub-content.split>
    @endif
</div>
