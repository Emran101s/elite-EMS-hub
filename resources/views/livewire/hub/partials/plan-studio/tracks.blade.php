<div class="fixed inset-0 z-40 flex justify-end" wire:key="tracks-panel">
    <div class="absolute inset-0 bg-navy-950/40" wire:click="$set('showTracks', false)"></div>
    <aside class="relative flex h-full w-full max-w-[420px] flex-col bg-white shadow-[0_0_80px_-10px_rgba(11,31,58,0.6)]">
        <div class="relative shrink-0 overflow-hidden bg-gradient-to-br from-navy-900 to-[var(--color-navy-950)] px-5 pb-4 pt-4 text-white">
            <div class="pointer-events-none absolute -right-10 -top-12 h-36 w-36 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.3),transparent_70%)]"></div>
            <div class="relative flex items-start justify-between">
                <div>
                    <p class="text-eyebrow font-bold uppercase tracking-[0.24em] text-gold-400">Manage</p>
                    <p class="pf text-lg font-black text-white" style="font-family:'Spectral',Georgia,serif">Tracks &amp; goals</p>
                </div>
                <button type="button" wire:click="$set('showTracks', false)" class="text-white/40 transition hover:text-white">✕</button>
            </div>
        </div>

        <div class="min-h-0 flex-1 space-y-2.5 overflow-y-auto p-4">
            @foreach ($tracks as $t)
                <div wire:key="trk-edit-{{ $t->id }}" class="rounded-xl border border-line bg-white p-3 shadow-sm">
                    <div class="flex items-center gap-2">
                        <input type="color" value="{{ $t->color ?? 'var(--color-info)' }}" @change="$wire.updateTrack({{ $t->id }}, 'color', $event.target.value)"
                               class="h-7 w-7 shrink-0 cursor-pointer rounded-md border border-line bg-white p-0.5" title="Track colour">
                        <input type="text" value="{{ $t->name }}" @change="$wire.updateTrack({{ $t->id }}, 'name', $event.target.value)"
                               class="input h-8 flex-1 text-sm font-bold">
                        <span class="shrink-0 rounded-full bg-navy-50 px-1.5 text-eyebrow font-bold text-navy-400" title="items">{{ $t->items()->count() }}</span>
                        <button type="button" wire:click="deleteTrack({{ $t->id }})" wire:confirm="Delete track “{{ $t->name }}”? Its items move to another track."
                                class="shrink-0 rounded-md px-1.5 py-1 text-micro text-navy-300 transition hover:bg-risk/10 hover:text-risk" title="Delete track">✕</button>
                    </div>
                    <input type="text" value="{{ $t->goal }}" @change="$wire.updateTrack({{ $t->id }}, 'goal', $event.target.value)"
                           placeholder="Goal — what this phase is meant to achieve…"
                           class="input mt-2 h-8 w-full text-micro">
                </div>
            @endforeach

            <div class="flex items-center gap-2 rounded-xl border border-dashed border-line p-3">
                <input type="text" wire:model="newTrackName" wire:keydown.enter="addTrack" placeholder="New track name…" class="input h-8 flex-1 text-xs">
                <button type="button" wire:click="addTrack" class="rounded-lg bg-navy-900 px-3 py-1.5 text-micro font-bold text-white transition hover:bg-navy-800">＋ Add track</button>
            </div>
        </div>

        <div class="flex shrink-0 items-center justify-end border-t border-line bg-page/40 px-5 py-3">
            <button type="button" wire:click="$set('showTracks', false)" class="btn-navy h-8 px-4 text-micro">Done</button>
        </div>
    </aside>
</div>
