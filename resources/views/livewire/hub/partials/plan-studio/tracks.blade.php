<div class="fixed inset-0 z-40 flex justify-end" wire:key="tracks-panel">
    <div class="absolute inset-0 bg-eo-navy-deep/40" wire:click="$set('showTracks', false)"></div>
    <aside class="relative flex h-full w-full max-w-[420px] flex-col bg-white shadow-eo-float">
        <div class="relative shrink-0 overflow-hidden bg-eo-navy px-5 pb-4 pt-4 text-white">
            <div class="relative flex items-start justify-between">
                <div>
                    <p class="eo-label !text-white/60">Manage</p>
                    <p class="text-lg font-black text-white">Tracks &amp; goals</p>
                </div>
                <button type="button" wire:click="$set('showTracks', false)" class="text-white/40 transition hover:text-white">✕</button>
            </div>
        </div>

        <div class="min-h-0 flex-1 space-y-2.5 overflow-y-auto p-4">
            @foreach ($tracks as $t)
                <div wire:key="trk-edit-{{ $t->id }}" class="rounded-xl border border-eo-line bg-white p-3 shadow-sm">
                    <div class="flex items-center gap-2">
                        <input type="color" value="{{ $t->color ?? 'var(--color-info)' }}" @change="$wire.updateTrack({{ $t->id }}, 'color', $event.target.value)"
                               class="h-7 w-7 shrink-0 cursor-pointer rounded-md border border-eo-line bg-white p-0.5" title="Track colour">
                        <input type="text" value="{{ $t->name }}" @change="$wire.updateTrack({{ $t->id }}, 'name', $event.target.value)"
                               class="eo-input h-8 flex-1 text-sm font-bold">
                        <span class="shrink-0 rounded-full bg-eo-bg px-1.5 text-eyebrow font-bold text-eo-muted" title="items">{{ $t->items()->count() }}</span>
                        <x-confirm title="Delete track “{{ $t->name }}”?"
                                body="Its items move to another track."
                                confirm="Delete"
                                run="$wire.deleteTrack({{ $t->id }})"
                                class="shrink-0 rounded-md px-1.5 py-1 text-micro text-eo-muted transition hover:bg-eo-risk-soft hover:text-eo-risk">✕</x-confirm>
                    </div>
                    <input type="text" value="{{ $t->goal }}" @change="$wire.updateTrack({{ $t->id }}, 'goal', $event.target.value)"
                           placeholder="Goal — what this phase is meant to achieve…"
                           class="eo-input mt-2 h-8 w-full text-micro">
                </div>
            @endforeach

            <div class="flex items-center gap-2 rounded-xl border border-dashed border-eo-line p-3">
                <input type="text" wire:model="newTrackName" wire:keydown.enter="addTrack" placeholder="New track name…" class="eo-input h-8 flex-1 text-xs">
                <x-eo.button variant="navy" wire:click="addTrack" class="h-8 px-3 text-micro">＋ Add track</x-eo.button>
            </div>
        </div>

        <div class="flex shrink-0 items-center justify-end border-t border-eo-line bg-eo-workspace/40 px-5 py-3">
            <x-eo.button variant="navy" wire:click="$set('showTracks', false)" class="h-8 px-4 text-micro">Done</x-eo.button>
        </div>
    </aside>
</div>
