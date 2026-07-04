@php
    $packageTone = ['platinum' => 'bg-navy-900 text-white', 'gold' => 'bg-gold-500 text-navy-900', 'silver' => 'bg-navy-100 text-navy-700', 'bronze' => 'bg-amber-100 text-amber-800', 'strategic' => 'bg-navy-50 text-navy-700', 'supporting' => 'bg-page text-muted'];
@endphp

<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    @forelse ($event->sponsors as $sponsor)
        <div class="card p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-page text-sm font-bold text-navy-900 ring-1 ring-line">{{ str($sponsor->name)->substr(0, 1) }}</span>
                    <div>
                        <p class="text-sm font-bold text-navy-900">{{ $sponsor->name }}</p>
                        @if ($sponsor->booth)<p class="text-xs text-muted">Booth {{ $sponsor->booth }}</p>@endif
                    </div>
                </div>
                <span class="rounded-full px-2.5 py-1 text-[0.6rem] font-bold uppercase tracking-wide {{ $packageTone[$sponsor->package] ?? 'bg-page text-muted' }}">{{ $sponsor->package }}</span>
            </div>
            <div class="mt-4 flex items-center justify-between border-t border-line pt-3">
                <span class="text-sm font-bold text-navy-900">${{ number_format($sponsor->amount_cents / 100) }}</span>
                <x-status-badge :status="$sponsor->payment_status" />
            </div>
        </div>
    @empty
        <p class="col-span-full py-12 text-center text-sm text-muted">No sponsors yet — sponsorship packages: Platinum · Gold · Silver · Bronze · Strategic · Supporting.</p>
    @endforelse
</div>
@if ($event->sponsors->isNotEmpty())
    <p class="mt-4 text-xs text-muted">Total sponsorship: <span class="font-bold text-navy-900">${{ number_format($event->sponsors->sum('amount_cents') / 100) }}</span> · Coming next: logo uploads, deliverables checklist, branding rights, contracts.</p>
@endif
