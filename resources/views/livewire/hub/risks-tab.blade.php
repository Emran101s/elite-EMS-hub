<div>
    {{-- ══ Stat strip ══
         Every other module opens with a number before a table — this one
         used to open straight into the register with nothing to scan first.
         Uses the same x-stat-strip as Attendees now, instead of hand-rolling
         three bordered cards for it — one card, divided, says the same thing
         in a third of the height, and a shared component means the day this
         strip needs a fourth tile, both modules get it for free. --}}
    @php
        $openRisks = $risks->filter->isOpen();
        $criticalOpen = $openRisks->filter(fn ($r) => $r->severity() >= 15);
        $resolved = $risks->whereIn('status', ['mitigated', 'closed']);
        $total = max($risks->count(), 1);
    @endphp
    <x-stat-strip class="mb-4" :stats="[
        ['Open', $openRisks->count(), 'flag', round($openRisks->count() / $total * 100), 'bg-warn', $risks->count().' total'],
        ['Critical', $criticalOpen->count(), 'flag', $openRisks->count() ? round($criticalOpen->count() / max($openRisks->count(), 1) * 100) : 0, 'bg-risk', 'severity ≥ 15', $criticalOpen->count() ? 'text-danger-ink' : 'text-navy-900'],
        ['Resolved', $resolved->count(), 'check', round($resolved->count() / $total * 100), 'bg-track', 'mitigated or closed', 'text-emerald-600'],
    ]" />

    <div class="mb-4 flex items-center justify-between">
        <p class="text-xs text-muted">Open risks with severity ≥ 20 cap the Event Health Score at "At Risk".</p>
        <button type="button" wire:click="$toggle('showForm')" class="btn-gold h-10 px-4 text-xs">＋ Register Risk</button>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="card mb-5 grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-4">
            <div class="sm:col-span-2">
                <label class="field-label !mb-1 !text-eyebrow" for="r-title">Risk</label>
                <input id="r-title" type="text" wire:model="title" class="input h-10 text-sm" placeholder="e.g. Venue contract pending signature">
                @error('title') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="field-label !mb-1 !text-eyebrow" for="r-cat">Category</label>
                <select id="r-cat" wire:model="category" class="input h-10 text-sm">
                    @foreach (\App\Support\Taxonomy::options('risk_category') as $cv => $cl)<option value="{{ $cv }}">{{ $cl }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="field-label !mb-1 !text-eyebrow" for="r-owner">Owner</label>
                <select id="r-owner" wire:model="owner_id" class="input h-10 text-sm">
                    <option value="">—</option>
                    @foreach ($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="field-label !mb-1 !text-eyebrow" for="r-prob">Probability (1–5)</label>
                <select id="r-prob" wire:model="probability" class="input h-10 text-sm">
                    @foreach (range(1, 5) as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="field-label !mb-1 !text-eyebrow" for="r-imp">Impact (1–5)</label>
                <select id="r-imp" wire:model="impact" class="input h-10 text-sm">
                    @foreach (range(1, 5) as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="field-label !mb-1 !text-eyebrow" for="r-mit">Mitigation plan</label>
                <input id="r-mit" type="text" wire:model="mitigation" class="input h-10 text-sm" placeholder="What are we doing about it?">
            </div>
            <div class="flex items-end justify-end gap-2 sm:col-span-2 xl:col-span-4">
                <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="btn-navy h-10 px-5 text-xs">
                    <span wire:loading.remove wire:target="save">Save Risk</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
            </div>
        </form>
    @endif

    <div class="card divide-y divide-line">
        <div class="hidden grid-cols-12 gap-3 px-6 py-3 text-eyebrow font-semibold uppercase tracking-wide text-muted md:grid">
            <span class="col-span-4">Risk</span>
            <span class="col-span-2">Category</span>
            <span class="col-span-2 text-center">Severity (P×I)</span>
            <span class="col-span-1">Owner</span>
            <span class="col-span-3 text-right">Status · Actions</span>
        </div>
        @forelse ($risks as $risk)
            <div class="group/risk grid grid-cols-2 items-center gap-3 px-6 py-4 md:grid-cols-12">
                <div class="col-span-2 md:col-span-4">
                    <p class="text-sm font-semibold text-navy-900">{{ $risk->title }}</p>
                    @if ($risk->mitigation)<p class="mt-0.5 text-xs text-muted">Mitigation: {{ $risk->mitigation }}</p>@endif
                </div>
                <p class="text-xs text-muted md:col-span-2">{{ str($risk->category)->replace('_', ' ')->title() }}</p>
                <div class="md:col-span-2 md:text-center">
                    <span @class([
                            'inline-flex rounded-full px-2.5 py-1 text-xs font-bold',
                            'bg-risk/10 text-red-700' => $risk->severity() >= 15,
                            'bg-warn/10 text-amber-700' => $risk->severity() >= 8 && $risk->severity() < 15,
                            'bg-navy-50 text-navy-600' => $risk->severity() < 8,
                        ])>{{ $risk->probability }}×{{ $risk->impact }} = {{ $risk->severity() }}</span>
                </div>
                <p class="truncate text-xs text-muted md:col-span-1">{{ $risk->owner?->name ? str($risk->owner->name)->before(' ') : '—' }}</p>
                <div class="flex items-center justify-end gap-1.5 md:col-span-3">
                    <x-status-badge :status="$risk->status" />
                    @if ($risk->isOpen())
                        <span class="flex gap-1 opacity-0 transition group-hover/risk:opacity-100">
                            <button type="button" wire:click="setStatus({{ $risk->id }}, 'mitigated')" class="rounded-lg bg-track/10 px-2 py-1 text-eyebrow font-bold text-emerald-700 hover:bg-track/20" title="Mark mitigated">✓</button>
                            <button type="button" wire:click="setStatus({{ $risk->id }}, 'escalated')" class="rounded-lg bg-risk/10 px-2 py-1 text-eyebrow font-bold text-red-700 hover:bg-risk/20" title="Escalate">▲</button>
                            <button type="button" wire:click="setStatus({{ $risk->id }}, 'closed')" class="rounded-lg bg-navy-50 px-2 py-1 text-eyebrow font-bold text-navy-600 hover:bg-navy-100" title="Close">✕</button>
                        </span>
                    @else
                        <button type="button" wire:click="setStatus({{ $risk->id }}, 'open')" class="rounded-lg bg-navy-50 px-2 py-1 text-eyebrow font-bold text-navy-600 opacity-0 transition hover:bg-navy-100 group-hover/risk:opacity-100" title="Reopen">↺</button>
                    @endif
                </div>
            </div>
        @empty
            <p class="px-6 py-12 text-center text-sm text-muted">Risk register is empty — register the first risk to feed the health engine.</p>
        @endforelse
    </div>
</div>
