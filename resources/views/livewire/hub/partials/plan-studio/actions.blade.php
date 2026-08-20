<div x-data="{ open: false }" class="relative shrink-0" data-block-drag>
    <button type="button" @click.stop="open = !open" aria-label="Item actions"
            class="flex h-5 w-5 items-center justify-center rounded-md text-eo-muted transition hover:bg-eo-bg hover:text-eo-text">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><circle cx="4" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="16" cy="10" r="1.6"/></svg>
    </button>
    <div x-show="open" x-cloak @click.outside="open = false" x-transition
         class="absolute right-0 top-6 z-30 w-36 overflow-hidden rounded-xl border border-eo-line bg-white py-1 shadow-eo-float">
        <button type="button" wire:click="openItem({{ $item->id }})" @click="open = false"
                class="block w-full px-3 py-1.5 text-left text-micro font-semibold text-eo-text transition hover:bg-eo-bg">Edit details</button>
        <x-confirm title="Delete “{{ \Illuminate\Support\Str::limit($item->title ?: 'this item', 40) }}”?"
                body="Its subtasks go with it."
                confirm="Delete"
                run="$wire.deleteItem({{ $item->id }})"
                @click="open = false"
                class="block w-full px-3 py-1.5 text-left text-micro font-semibold text-eo-risk-ink transition hover:bg-eo-risk-soft">Delete</x-confirm>
    </div>
</div>
