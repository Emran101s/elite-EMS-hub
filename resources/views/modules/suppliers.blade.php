<x-layouts.app title="Suppliers" subtitle="Your supplier network, rated and categorized.">
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($suppliers as $supplier)
            <div class="card p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold text-navy-900">{{ $supplier->name }}</p>
                        <p class="mt-0.5 text-xs text-muted">
                            {{ str($supplier->category)->replace('_', ' & ')->title() }}
                            @if ($supplier->city) · {{ $supplier->city }}, {{ $supplier->country }} @endif
                        </p>
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full bg-gold-50 px-2.5 py-1 text-xs font-bold text-gold-700 ring-1 ring-gold-200">
                        ★ {{ number_format($supplier->rating, 1) }}
                    </span>
                </div>
                <p class="mt-4 border-t border-line pt-3 text-xs text-muted">
                    Working on <span class="font-semibold text-navy-900">{{ $supplier->events_count }}</span>
                    {{ str('event')->plural($supplier->events_count) }}
                </p>
            </div>
        @empty
            <p class="col-span-full py-12 text-center text-sm text-muted">No suppliers yet.</p>
        @endforelse
    </div>
</x-layouts.app>
