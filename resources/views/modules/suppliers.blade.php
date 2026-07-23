<x-layouts.app title="Suppliers" subtitle="Your supplier network, rated and categorized.">
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($suppliers as $supplier)
            <div class="group op-card">
                <div class="flex flex-1 flex-col p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-sm font-bold text-gold-400" style="background: linear-gradient(135deg, #1E3352, #14315a);">{{ str($supplier->name)->substr(0, 1) }}</span>
                            <div class="min-w-0">
                                <p class="pf truncate text-sm font-bold text-navy-900">{{ $supplier->name }}</p>
                                <p class="mt-0.5 truncate text-xs text-muted">
                                    {{ str($supplier->category)->replace('_', ' & ')->title() }}
                                    @if ($supplier->city) · {{ $supplier->city }}, {{ $supplier->country }} @endif
                                </p>
                            </div>
                        </div>
                        <span class="pill shrink-0 bg-gold-50 text-gold-700 ring-1 ring-gold-200">★ {{ number_format($supplier->rating, 1) }}</span>
                    </div>
                </div>
                <div class="op-card-foot">
                    <x-icon name="truck" class="h-3 w-3 shrink-0 text-gold-400" />
                    <span class="truncate text-eyebrow font-semibold text-white/80">Working on {{ $supplier->events_count }} {{ str('event')->plural($supplier->events_count) }}</span>
                    <span class="ml-auto shrink-0 text-eyebrow font-bold uppercase tracking-wide text-white/45">{{ str($supplier->category)->replace('_', ' ')->title() }}</span>
                </div>
            </div>
        @empty
            <p class="col-span-full py-12 text-center text-sm text-muted">No suppliers yet.</p>
        @endforelse
    </div>
</x-layouts.app>
