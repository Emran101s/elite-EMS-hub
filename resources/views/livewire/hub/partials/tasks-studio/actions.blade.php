<div x-data="{ open: false }" class="relative shrink-0" data-block-drag>
    <button type="button" @click.stop="open = !open" aria-label="Task actions"
            class="flex h-5 w-5 items-center justify-center rounded-md text-muted transition hover:bg-page hover:text-ink">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><circle cx="4" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="16" cy="10" r="1.6"/></svg>
    </button>
    <div x-show="open" x-cloak @click.outside="open = false" x-transition
         class="absolute right-0 top-6 z-30 w-32 overflow-hidden rounded-lg border border-line bg-white py-1 shadow-overlay">
        <button type="button" wire:click="openTask({{ $item->id }})" @click="open = false" class="block w-full px-3 py-1.5 text-left text-micro font-semibold text-ink transition hover:bg-page">Edit details</button>
        <x-confirm title="Delete “{{ \Illuminate\Support\Str::limit($item->title ?: 'this task', 40) }}”?"
                confirm="Delete"
                run="$wire.deleteTask({{ $item->id }})"
                @click="open = false"
                class="block w-full px-3 py-1.5 text-left text-micro font-semibold text-danger-ink transition hover:bg-danger-soft">Delete</x-confirm>
    </div>
</div>
