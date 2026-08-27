<div class="space-y-5">
    @forelse ($byModule as $slug => $rows)
        @php [$mlabel, $mhex] = \App\Models\Task::MODULES[$slug] ?? [ucfirst($slug), 'var(--color-neutral)']; @endphp
        <div wire:key="tgal-{{ $slug }}">
            <div class="mb-2.5 flex items-center gap-2">
                <span class="h-3 w-3 shrink-0 rounded-[4px]" style="background: {{ $mhex }}"></span>
                <h3 class="text-micro font-bold uppercase tracking-wide text-ink">{{ $mlabel }}</h3>
                <span class="rounded-full bg-page px-1.5 text-eyebrow font-bold text-muted">{{ $rows->count() }}</span>
                <button type="button" wire:click="addTask('{{ $slug }}')" class="ml-auto text-eyebrow font-bold text-muted transition hover:text-gold-700">＋ Task</button>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                @foreach ($rows as $item)
                    @include('livewire.hub.partials.tasks-studio.card', ['item' => $item, 'showStatus' => true])
                @endforeach
            </div>
        </div>
    @empty
        <x-empty icon="clipboard" title="No tasks yet" hint="Track work items, ownership and progress toward event day.">
            <x-slot:actions>
                <x-eo.button size="sm" wire:click="addTask">＋ Add a task</x-eo.button>
            </x-slot:actions>
        </x-empty>
    @endforelse

    @if ($unmoduled->isNotEmpty())
        <div wire:key="tgal-none">
            <h3 class="mb-2.5 text-micro font-bold uppercase tracking-wide text-muted">No module</h3>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                @foreach ($unmoduled as $item)
                    @include('livewire.hub.partials.tasks-studio.card', ['item' => $item, 'showStatus' => true])
                @endforeach
            </div>
        </div>
    @endif
</div>
