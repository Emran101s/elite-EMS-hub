<div x-data="{ open: false }" class="relative shrink-0" data-block-drag>
    <button type="button" @click.stop="open = !open" aria-label="Task actions"
            class="flex h-5 w-5 items-center justify-center rounded-md text-navy-300 transition hover:bg-navy-100 hover:text-navy-700">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><circle cx="4" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="16" cy="10" r="1.6"/></svg>
    </button>
    <div x-show="open" x-cloak @click.outside="open = false" x-transition
         class="absolute right-0 top-6 z-30 w-32 overflow-hidden rounded-xl border border-line bg-white py-1 shadow-[0_20px_50px_-18px_rgba(11,31,58,0.5)]">
        <button type="button" wire:click="openTask({{ $item->id }})" @click="open = false" class="block w-full px-3 py-1.5 text-left text-micro font-semibold text-navy-700 transition hover:bg-navy-50">Edit details</button>
        <x-confirm title="Delete “{{ \Illuminate\Support\Str::limit($item->title ?: 'this task', 40) }}”?"
                confirm="Delete"
                run="$wire.deleteTask({{ $item->id }})"
                @click="open = false"
                class="block w-full px-3 py-1.5 text-left text-micro font-semibold text-risk transition hover:bg-risk/10">Delete</x-confirm>
    </div>
</div>
