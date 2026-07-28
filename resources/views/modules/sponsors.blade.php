@php
    $packageTone = ['platinum' => 'bg-navy-900 text-white', 'gold' => 'bg-gold-500 text-navy-900', 'silver' => 'bg-navy-100 text-navy-700', 'bronze' => 'bg-amber-100 text-amber-800', 'strategic' => 'bg-navy-50 text-navy-700', 'supporting' => 'bg-page text-muted'];
@endphp

<x-layouts.app title="Sponsors" subtitle="Every sponsorship across your events — packages, value and payment status.">
    <div class="mb-5 flex flex-wrap gap-3">
        <div class="flex h-[90px] w-[180px] flex-col justify-center rounded-[18px] border border-line bg-white px-4 shadow-[0_10px_30px_rgba(15,23,42,0.05)]">
            <p class="text-[11px] font-semibold text-muted">Total Sponsorship</p>
            <p class="mt-1 text-2xl font-bold text-navy-900">${{ \Illuminate\Support\Number::abbreviate($total / 100, 2) }}</p>
        </div>
        <div class="flex h-[90px] w-[155px] flex-col justify-center rounded-[18px] border border-line bg-white px-4 shadow-[0_10px_30px_rgba(15,23,42,0.05)]">
            <p class="text-[11px] font-semibold text-muted">Sponsored Events</p>
            <p class="mt-1 text-2xl font-bold text-navy-900">{{ $events->count() }}</p>
        </div>
    </div>

    <div class="space-y-5">
        @forelse ($events as $event)
            <div class="card p-5">
                <div class="mb-4 flex items-center gap-3">
                    <x-event-avatar :event="$event" :ring="false" size="sm" />
                    <div>
                        <a href="{{ route('events.hub', [$event, 'tab' => 'sponsors']) }}" class="text-sm font-bold text-navy-900 hover:text-gold-700">{{ $event->name }}</a>
                        <p class="text-xs text-muted">{{ $event->sponsors->count() }} {{ str('sponsor')->plural($event->sponsors->count()) }} · ${{ number_format($event->sponsors->sum('amount_cents') / 100) }}</p>
                    </div>
                </div>
                <div class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
                    @foreach ($event->sponsors->sortByDesc('amount_cents') as $sponsor)
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-line px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-page text-xs font-bold text-navy-900 ring-1 ring-line">{{ str($sponsor->name)->substr(0, 1) }}</span>
                                <div>
                                    <p class="text-xs font-bold text-navy-900">{{ $sponsor->name }}</p>
                                    <p class="text-[0.65rem] text-muted">${{ number_format($sponsor->amount_cents / 100) }}</p>
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <span class="rounded-full px-2 py-0.5 text-[0.55rem] font-bold uppercase {{ $packageTone[$sponsor->package] ?? 'bg-page text-muted' }}">{{ $sponsor->package }}</span>
                                <x-status-badge :status="$sponsor->payment_status" class="!text-[0.55rem]" />
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="col-span-full py-12 text-center text-sm text-muted">No sponsors yet — add them from an event's Sponsors tab.</p>
        @endforelse
    </div>
</x-layouts.app>
