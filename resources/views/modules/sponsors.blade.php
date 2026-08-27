@php
    // Local to this page only — EventSponsor::paymentStatusMeta() is shared
    // with the per-event Sponsors hub tab (Event Hub, out of scope this
    // phase), so its classes stay old-token there. Only the label text is
    // reused from it; the tone below is this page's own.
    $packageTone = [
        'platinum' => 'bg-navy-900 text-white',
        'gold' => 'bg-gold-500 text-navy-900',
        'silver' => 'bg-page text-ink',
        'bronze' => 'bg-warning-soft text-warning-ink',
        'strategic' => 'bg-info-soft text-info-ink',
        'supporting' => 'bg-page text-muted',
    ];
    $paymentTone = [
        'pending' => 'bg-page text-muted',
        'partial' => 'bg-warning-soft text-warning-ink',
        'paid' => 'bg-success-soft text-success-ink',
    ];
    $stMeta = \App\Models\EventSponsor::paymentStatusMeta();
@endphp

<x-layouts.app title="Sponsors" subtitle="Every sponsorship across your events — packages, value and payment status." :hide-title-row="true">
    <x-cc.header eyebrow="Commercial Command" title="Sponsors" subtitle="Every sponsorship across your events — packages, value and payment status." />

    <div class="mb-5 mt-4 grid grid-cols-2 gap-3 sm:max-w-md">
        <x-cc.kpi-tile label="Total sponsorship" :value="'$'.\Illuminate\Support\Number::abbreviate($total / 100, 2)" hint="Across the portfolio" />
        <x-cc.kpi-tile label="Sponsored events" :value="$events->count()" hint="Carrying a sponsor" />
    </div>

    <div class="space-y-5">
        @forelse ($events as $event)
            <div class="rounded-lg border border-line bg-white p-5">
                <div class="mb-4 flex items-center gap-3">
                    <x-event-avatar :event="$event" :ring="false" size="sm" />
                    <div>
                        <a href="{{ route('events.hub', [$event, 'tab' => 'sponsors']) }}" class="text-[13.5px] font-bold text-ink transition hover:text-gold-700">{{ $event->name }}</a>
                        <p class="text-[11.5px] text-muted">{{ $event->sponsors->count() }} {{ str('sponsor')->plural($event->sponsors->count()) }} · ${{ number_format($event->sponsors->sum('amount_cents') / 100) }}</p>
                    </div>
                </div>
                <div class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
                    @foreach ($event->sponsors->sortByDesc('amount_cents') as $sponsor)
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-line px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-page text-[12px] font-bold text-ink ring-1 ring-line">{{ str($sponsor->name)->substr(0, 1) }}</span>
                                <div>
                                    <p class="text-[12px] font-bold text-ink">{{ $sponsor->name }}</p>
                                    <p class="text-[10.5px] text-muted">${{ number_format($sponsor->amount_cents / 100) }}</p>
                                </div>
                            </div>
                            @php $stLabel = $stMeta[$sponsor->payment_status][0] ?? $stMeta['pending'][0]; @endphp
                            <div class="flex flex-col items-end gap-1">
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $packageTone[$sponsor->package] ?? 'bg-page text-muted' }}">{{ $sponsor->package }}</span>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $paymentTone[$sponsor->payment_status] ?? $paymentTone['pending'] }}">{{ $stLabel }}</span>
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
