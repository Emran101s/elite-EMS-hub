@props(['selected', 'offer', 'mayOffer'])

<div class="space-y-2 rounded-lg border border-line bg-white p-4">
    <p class="mb-1 text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Deal actions</p>

    @if ($mayOffer && ! $offer)
        <button type="button" wire:click="draftProposal({{ $selected->id }})"
                class="flex w-full items-center justify-center rounded-full bg-gold-500 px-3.5 py-2 text-[12px] font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">＋ Draft proposal</button>
    @elseif ($offer)
        <a href="{{ route('proposals.edit', $offer) }}" wire:navigate
           class="flex w-full items-center justify-center rounded-full bg-gold-500 px-3.5 py-2 text-[12px] font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">Open offer</a>
    @endif

    @if ($selected->event)
        <a href="{{ route('events.hub', $selected->event) }}" wire:navigate
           class="flex w-full items-center justify-center rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:border-navy-300">Open the event →</a>
    @endif

    <button type="button" wire:click="$toggle('showActivity')"
            class="flex w-full items-center justify-center rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:border-navy-300">＋ Log activity</button>
</div>
