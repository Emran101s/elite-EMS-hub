@props(['activities', 'showActivity', 'aType'])

<div class="overflow-hidden rounded-lg border border-line bg-white">
    <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
        <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Activity</p>
        <button type="button" wire:click="$toggle('showActivity')" class="rounded-full border border-line bg-white px-2.5 py-1 text-[11px] font-bold text-ink transition hover:border-navy-300">＋ Log</button>
    </div>

    @if ($showActivity)
        <div class="space-y-2 border-b border-line bg-page p-4">
            <div class="flex gap-1">
                @foreach (\App\Support\Taxonomy::options('activity_type') as $tv => $tl)
                    <button type="button" wire:click="$set('a_type', '{{ $tv }}')"
                            @class(['flex-1 rounded-md border py-1.5 text-[10.5px] font-bold transition',
                                    'border-navy-900 bg-navy-900 text-white' => $aType === $tv,
                                    'border-line bg-white text-muted hover:border-gold-300' => $aType !== $tv])>{{ $tl }}</button>
                @endforeach
            </div>
            <input type="text" wire:model="a_subject" placeholder="What happened?"
                   class="h-9 w-full rounded-md border border-line bg-white px-3 text-xs outline-none focus:border-gold-400">
            @error('a_subject')<p class="text-[10.5px] text-danger-ink">{{ $message }}</p>@enderror
            <textarea wire:model="a_body" rows="2" placeholder="Detail (optional)"
                      class="w-full rounded-md border border-line bg-white px-3 py-2 text-xs outline-none focus:border-gold-400"></textarea>
            <label class="block">
                <span class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Follow up on</span>
                <input type="date" wire:model="a_follow_up_on"
                       class="mt-1 h-9 w-full rounded-md border border-line bg-white px-3 text-xs outline-none focus:border-gold-400">
            </label>
            <button type="button" wire:click="logActivity"
                    class="flex h-9 w-full items-center justify-center rounded-full bg-gold-500 text-[12px] font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">Save</button>
        </div>
    @endif

    <div class="divide-y divide-line">
        @forelse ($activities as $a)
            <div class="px-4 py-2.5">
                <div class="flex items-baseline gap-2">
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold bg-info-soft text-info-ink">{{ $a->typeLabel() }}</span>
                    <span class="min-w-0 flex-1 truncate text-[12px] font-semibold text-ink">{{ $a->subject }}</span>
                    <span class="shrink-0 text-[10px] tabular-nums text-muted">{{ $a->happened_at->diffForHumans(short: true) }}</span>
                </div>
                @if ($a->body)
                    <p class="mt-1 text-[11px] leading-relaxed text-muted">{{ $a->body }}</p>
                @endif
                @if ($a->follow_up_on && ! $a->follow_up_done)
                    <button type="button" wire:click="completeFollowUp({{ $a->id }})"
                            class="mt-1.5 inline-flex items-center gap-1.5 rounded-md bg-warning-soft px-2 py-1 text-[10px] font-bold text-warning-ink transition hover:brightness-95">
                        ↻ Follow up {{ $a->follow_up_on->format('j M') }} · mark done
                    </button>
                @endif
            </div>
        @empty
            <p class="px-4 py-5 text-center text-[11.5px] text-muted">Nothing logged yet.</p>
        @endforelse
    </div>
</div>
