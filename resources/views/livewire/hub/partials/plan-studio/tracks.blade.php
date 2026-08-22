<div class="fixed inset-0 z-40 flex justify-end" wire:key="tracks-panel">
    <div class="absolute inset-0 bg-navy-900/40" wire:click="$set('showTracks', false)"></div>
    <aside class="relative flex h-full w-full max-w-[420px] flex-col bg-white shadow-overlay">
        <div class="relative shrink-0 overflow-hidden bg-navy-900 px-5 pb-4 pt-4 text-white">
            <div class="relative flex items-start justify-between">
                <div>
                    <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-white/60">Manage</p>
                    <p class="text-lg font-black text-white">Tracks &amp; goals</p>
                </div>
                <button type="button" wire:click="$set('showTracks', false)" class="text-white/40 transition hover:text-white">✕</button>
            </div>
        </div>

        <div class="min-h-0 flex-1 space-y-2.5 overflow-y-auto p-4">
            @foreach ($tracks as $t)
                <div wire:key="trk-edit-{{ $t->id }}" class="rounded-lg border border-line bg-white p-3 shadow-sm">
                    <div class="flex items-center gap-2">
                        <input type="color" value="{{ $t->color ?? 'var(--color-info)' }}" @change="$wire.updateTrack({{ $t->id }}, 'color', $event.target.value)"
                               class="h-7 w-7 shrink-0 cursor-pointer rounded-md border border-line bg-white p-0.5" title="Track colour">
                        <input type="text" value="{{ $t->name }}" @change="$wire.updateTrack({{ $t->id }}, 'name', $event.target.value)"
                               class="h-8 flex-1 rounded-lg border border-line bg-white px-2.5 text-sm font-bold text-ink focus:border-navy-300 focus:outline-none">
                        <span class="shrink-0 rounded-full bg-page px-1.5 text-eyebrow font-bold text-muted" title="items">{{ $t->items()->count() }}</span>
                        <x-confirm title="Delete track “{{ $t->name }}”?"
                                body="Its items move to another track."
                                confirm="Delete"
                                run="$wire.deleteTrack({{ $t->id }})"
                                class="shrink-0 rounded-md px-1.5 py-1 text-micro text-muted transition hover:bg-danger-soft hover:text-danger-ink">✕</x-confirm>
                    </div>
                    <input type="text" value="{{ $t->goal }}" @change="$wire.updateTrack({{ $t->id }}, 'goal', $event.target.value)"
                           placeholder="Goal — what this phase is meant to achieve…"
                           class="mt-2 h-8 w-full rounded-lg border border-line bg-white px-2.5 text-micro text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none">
                </div>
            @endforeach

            <div class="flex items-center gap-2 rounded-lg border border-dashed border-line p-3">
                <input type="text" wire:model="newTrackName" wire:keydown.enter="addTrack" placeholder="New track name…" class="h-8 flex-1 rounded-lg border border-line bg-white px-2.5 text-xs text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none">
                <button type="button" wire:click="addTrack" class="h-8 rounded-full bg-navy-900 px-3 text-micro font-bold text-white transition hover:bg-navy-800">＋ Add track</button>
            </div>
        </div>

        <div class="flex shrink-0 items-center justify-end border-t border-line bg-page px-5 py-3">
            <button type="button" wire:click="$set('showTracks', false)" class="h-8 rounded-full bg-navy-900 px-4 text-micro font-bold text-white transition hover:bg-navy-800">Done</button>
        </div>
    </aside>
</div>
