<div class="space-y-5">
    @foreach ($tracks as $t)
        @php $rows = $byTrack[$t->id] ?? collect(); @endphp
        @if ($rows->isNotEmpty() || ! $filterTrack)
            <div wire:key="gal-{{ $t->id }}">
                <div class="mb-2.5 flex items-center gap-2">
                    <span class="h-3 w-3 shrink-0 rounded-[4px]" style="background: {{ $t->color }}"></span>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h3 class="text-micro font-bold uppercase tracking-wide text-eo-text">{{ $t->name }}</h3>
                            <span class="rounded-full bg-eo-bg px-1.5 text-eyebrow font-bold text-eo-muted">{{ $rows->count() }}</span>
                        </div>
                        @if ($t->goal)<p class="text-eyebrow text-eo-muted">{{ $t->goal }}</p>@endif
                    </div>
                    <button type="button" wire:click="addItem({{ $t->id }})" class="ml-auto shrink-0 text-eyebrow font-bold text-eo-muted transition hover:text-eo-teal-ink">＋ Item</button>
                </div>
                @if ($rows->isEmpty())
                    <button type="button" wire:click="addItem({{ $t->id }})" class="w-full rounded-xl border border-dashed border-eo-line px-4 py-4 text-center text-micro font-semibold text-eo-muted transition hover:border-eo-teal hover:text-eo-teal-ink">＋ Add item</button>
                @else
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                        @foreach ($rows as $item)
                            @include('livewire.hub.partials.plan-studio.card', ['item' => $item, 'showStatus' => true])
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    @endforeach

    @if ($untracked->isNotEmpty())
        <div wire:key="gal-untracked">
            <h3 class="mb-2.5 text-micro font-bold uppercase tracking-wide text-eo-muted">No track</h3>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                @foreach ($untracked as $item)
                    @include('livewire.hub.partials.plan-studio.card', ['item' => $item, 'showStatus' => true])
                @endforeach
            </div>
        </div>
    @endif
</div>
