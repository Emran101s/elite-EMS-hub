<style>
    .ts-ghost { opacity: .4; }
    .ts-drag { transform: rotate(1.5deg); box-shadow: 0 20px 45px -15px rgba(11,31,58,.5) !important; }
</style>
<div class="flex gap-3 overflow-x-auto pb-2">
    @foreach (\App\Models\Task::STAGES as $sv => [$slabel, $shex])
        @php $col = $byStatus[$sv] ?? collect(); @endphp
        <div wire:key="tcol-{{ $sv }}" class="flex w-[270px] shrink-0 flex-col rounded-2xl border border-line bg-page/50">
            <div class="flex items-center gap-2 border-b border-line/70 px-3 py-2.5">
                <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $shex }}"></span>
                <span class="text-micro font-bold uppercase tracking-wide text-navy-700">{{ $slabel }}</span>
                <span class="rounded-full bg-white px-1.5 text-eyebrow font-bold text-navy-400 ring-1 ring-line">{{ $col->count() }}</span>
                <button type="button" wire:click="addTask(null, null, '{{ $sv }}')" class="ml-auto flex h-5 w-5 items-center justify-center rounded-md text-navy-300 transition hover:bg-white hover:text-gold-600" title="Add a task here">＋</button>
            </div>
            <div data-task-col="{{ $sv }}" class="flex min-h-[90px] flex-1 flex-col gap-2 p-2.5">
                @forelse ($col as $item)
                    @include('livewire.hub.partials.tasks-studio.card', ['item' => $item, 'showStatus' => false])
                @empty
                    <button type="button" wire:click="addTask(null, null, '{{ $sv }}')" class="rounded-xl border border-dashed border-line py-4 text-center text-eyebrow font-semibold text-navy-300 transition hover:border-gold-300 hover:text-gold-600">＋ Add task</button>
                @endforelse
            </div>
        </div>
    @endforeach
</div>
