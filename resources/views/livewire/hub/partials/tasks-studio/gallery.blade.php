<div class="space-y-5">
    @forelse ($byModule as $slug => $rows)
        @php [$mlabel, $mhex] = \App\Models\Task::MODULES[$slug] ?? [ucfirst($slug), 'var(--color-neutral)']; @endphp
        <div wire:key="tgal-{{ $slug }}">
            <div class="mb-2.5 flex items-center gap-2">
                <span class="h-3 w-3 shrink-0 rounded-[4px]" style="background: {{ $mhex }}"></span>
                <h3 class="text-micro font-bold uppercase tracking-wide text-navy-700">{{ $mlabel }}</h3>
                <span class="rounded-full bg-navy-50 px-1.5 text-eyebrow font-bold text-navy-400">{{ $rows->count() }}</span>
                <button type="button" wire:click="addTask('{{ $slug }}')" class="ml-auto text-eyebrow font-bold text-navy-300 transition hover:text-gold-600">＋ Task</button>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                @foreach ($rows as $item)
                    @include('livewire.hub.partials.tasks-studio.card', ['item' => $item, 'showStatus' => true])
                @endforeach
            </div>
        </div>
    @empty
        <div class="rounded-2xl border border-dashed border-line px-6 py-12 text-center text-xs text-muted">No tasks yet — <button type="button" wire:click="addTask" class="font-semibold text-gold-600 hover:underline">add one</button>.</div>
    @endforelse

    @if ($unmoduled->isNotEmpty())
        <div wire:key="tgal-none">
            <h3 class="mb-2.5 text-micro font-bold uppercase tracking-wide text-navy-400">No module</h3>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                @foreach ($unmoduled as $item)
                    @include('livewire.hub.partials.tasks-studio.card', ['item' => $item, 'showStatus' => true])
                @endforeach
            </div>
        </div>
    @endif
</div>
