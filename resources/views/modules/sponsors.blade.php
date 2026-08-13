@php
    // Local to this page only — EventSponsor::paymentStatusMeta() is shared
    // with the per-event Sponsors hub tab (Event Hub, out of scope this
    // phase), so its classes stay old-token there. Only the label text is
    // reused from it; the eo-* tone below is this page's own.
    $packageTone = [
        'platinum' => 'bg-eo-navy text-white',
        'gold' => 'bg-eo-gold text-eo-navy',
        'silver' => 'bg-eo-bg text-eo-text',
        'bronze' => 'bg-eo-warn-soft text-eo-warn',
        'strategic' => 'bg-eo-teal-soft text-eo-teal-ink',
        'supporting' => 'bg-eo-workspace text-eo-muted',
    ];
    $paymentTone = [
        'pending' => 'bg-eo-bg text-eo-muted',
        'partial' => 'bg-eo-warn-soft text-eo-warn',
        'paid' => 'bg-eo-ok-soft text-eo-ok',
    ];
    $stMeta = \App\Models\EventSponsor::paymentStatusMeta();
@endphp

<x-layouts.app title="Sponsors" subtitle="Every sponsorship across your events — packages, value and payment status." :hide-title-row="true">
    <x-eo.page-header
        eyebrow="Commercial Command"
        title="Sponsors"
        subtitle="Every sponsorship across your events — packages, value and payment status."
    />

    <div class="mb-5 grid grid-cols-2 gap-3 sm:max-w-md">
        <x-eo.metric-pill label="Total sponsorship" :value="'$'.\Illuminate\Support\Number::abbreviate($total / 100, 2)" hint="Across the portfolio" />
        <x-eo.metric-pill label="Sponsored events" :value="$events->count()" hint="Carrying a sponsor" />
    </div>

    <div class="space-y-5">
        @forelse ($events as $event)
            <div class="eo-soft-card p-5">
                <div class="mb-4 flex items-center gap-3">
                    <x-event-avatar :event="$event" :ring="false" size="sm" />
                    <div>
                        <a href="{{ route('events.hub', [$event, 'tab' => 'sponsors']) }}" class="text-sm font-bold text-eo-text transition hover:text-eo-teal-ink">{{ $event->name }}</a>
                        <p class="text-xs text-eo-muted">{{ $event->sponsors->count() }} {{ str('sponsor')->plural($event->sponsors->count()) }} · ${{ number_format($event->sponsors->sum('amount_cents') / 100) }}</p>
                    </div>
                </div>
                <div class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
                    @foreach ($event->sponsors->sortByDesc('amount_cents') as $sponsor)
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-eo-line px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-eo-bg text-xs font-bold text-eo-text ring-1 ring-eo-line">{{ str($sponsor->name)->substr(0, 1) }}</span>
                                <div>
                                    <p class="text-xs font-bold text-eo-text">{{ $sponsor->name }}</p>
                                    <p class="text-[0.65rem] text-eo-muted">${{ number_format($sponsor->amount_cents / 100) }}</p>
                                </div>
                            </div>
                            @php $stLabel = $stMeta[$sponsor->payment_status][0] ?? $stMeta['pending'][0]; @endphp
                            <div class="flex flex-col items-end gap-1">
                                <span class="rounded-full px-2 py-0.5 text-[0.55rem] font-bold uppercase {{ $packageTone[$sponsor->package] ?? 'bg-eo-workspace text-eo-muted' }}">{{ $sponsor->package }}</span>
                                <span class="rounded-full px-2 py-0.5 text-[0.55rem] font-bold uppercase {{ $paymentTone[$sponsor->payment_status] ?? $paymentTone['pending'] }}">{{ $stLabel }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <x-eo.empty-state icon="star" title="No sponsors yet" hint="Add them from an event's Sponsors tab." />
        @endforelse
    </div>
</x-layouts.app>
