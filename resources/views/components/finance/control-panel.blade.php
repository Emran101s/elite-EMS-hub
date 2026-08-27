@props(['event'])

<div class="space-y-2 rounded-lg border border-line bg-white p-4">
    <p class="mb-1 text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Finance Control</p>

    <a href="{{ route('events.hub', [$event, 'tab' => 'budget']) }}"
       class="flex w-full items-center justify-center rounded-full bg-gold-500 px-3.5 py-2 text-[12px] font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">Budget desk →</a>
    <a href="{{ route('invoices.index', ['q' => $event->name]) }}"
       class="flex w-full items-center justify-center rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:border-navy-300">Invoices</a>
    <a href="{{ route('payments.index', ['q' => $event->name]) }}"
       class="flex w-full items-center justify-center rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:border-navy-300">Payments</a>
</div>
