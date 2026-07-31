<style>
    .ts-ghost { opacity: .4; }
    .ts-drag { transform: rotate(1.5deg); box-shadow: 0 22px 48px -16px rgba(3,10,23,.55) !important; }
</style>
<div class="flex gap-4 overflow-x-auto pb-3">
    @foreach (\App\Models\Task::STAGES as $sv => [$slabel, $shex, $sopen])
        @php $col = $byStatus[$sv] ?? collect(); @endphp
        @continue ($sv === 'cancelled' && $col->isEmpty())
        {{-- Columns share the width instead of claiming a fixed 300px each.
             Five fixed columns need 1564px and the work area is 1538 — which is
             why Done sat 26px off the edge and looked missing. Flexible, they
             fill whatever there is, and only fall back to scrolling when the
             space is genuinely too tight for a readable card. --}}
        <div wire:key="tcol-{{ $sv }}" class="flex min-w-[264px] flex-1 flex-col">
            {{-- column header --}}
            <div class="kanban-head">
                <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $shex }}"></span>
                <span class="text-xs font-bold text-navy-800">{{ $slabel }}</span>
                <span class="rounded-full bg-white px-2 text-eyebrow font-bold text-navy-400 ring-1 ring-line">{{ $col->count() }}</span>
                <button type="button" wire:click="addTask(null, null, '{{ $sv }}')" class="ml-auto flex h-6 w-6 items-center justify-center rounded-lg text-navy-300 transition hover:bg-white hover:text-gold-600" title="Add a task here">＋</button>
            </div>

            {{-- droppable column body --}}
            <div data-task-col="{{ $sv }}" class="flex min-h-[120px] flex-1 flex-col gap-3 rounded-2xl bg-navy-900/[0.03] p-2.5 ring-1 ring-inset ring-navy-900/[0.04]">
                @forelse ($col as $item)
                    @include('livewire.hub.partials.tasks-studio.card', ['item' => $item])
                @empty
                    <button type="button" wire:click="addTask(null, null, '{{ $sv }}')" class="rounded-2xl border border-dashed border-line py-6 text-center text-eyebrow font-bold uppercase tracking-wide text-navy-300 transition hover:border-gold-300 hover:text-gold-600">＋ Add task</button>
                @endforelse
            </div>
        </div>
    @endforeach
</div>
