<div class="card divide-y divide-line">
    <div class="hidden grid-cols-12 gap-3 px-6 py-3 text-[0.65rem] font-semibold uppercase tracking-wide text-muted md:grid">
        <span class="col-span-5">Risk</span>
        <span class="col-span-2">Category</span>
        <span class="col-span-2 text-center">Severity (P×I)</span>
        <span class="col-span-1">Owner</span>
        <span class="col-span-2 text-right">Status</span>
    </div>
    @forelse ($event->risks->sortByDesc->severity() as $risk)
        <div class="grid grid-cols-2 items-center gap-3 px-6 py-4 md:grid-cols-12">
            <div class="col-span-2 md:col-span-5">
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
            <div class="md:col-span-2 md:text-right"><x-status-badge :status="$risk->status" /></div>
        </div>
    @empty
        <p class="px-6 py-12 text-center text-sm text-muted">Risk register is empty. Categories: venue, supplier, budget, client approval, speaker, logistics, production, weather, attendance, technical.</p>
    @endforelse
</div>
<p class="mt-4 text-xs text-muted">Open risks with severity ≥ 20 automatically cap the Event Health Score at "At Risk".</p>
