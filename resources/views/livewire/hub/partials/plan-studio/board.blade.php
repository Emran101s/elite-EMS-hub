<style>
    .ps-ghost { opacity: .4; }
    .ps-drag { transform: rotate(1.5deg); box-shadow: 0 20px 45px -15px rgba(11,31,58,.5) !important; }
</style>
<div class="flex gap-3 overflow-x-auto pb-2">
    @foreach (\App\Models\PlanItem::STATUSES as $sv => [$slabel, $shex])
        @php $col = $byStatus[$sv]; @endphp
        <div wire:key="col-{{ $sv }}" class="flex w-[270px] shrink-0 flex-col rounded-2xl border border-eo-line bg-eo-workspace/50">
            <div class="flex items-center gap-2 border-b border-eo-line/70 px-3 py-2.5">
                <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $shex }}"></span>
                <span class="text-micro font-bold uppercase tracking-wide text-eo-text">{{ $slabel }}</span>
                <span class="rounded-full bg-white px-1.5 text-eyebrow font-bold text-eo-muted ring-1 ring-eo-line">{{ $col->count() }}</span>
                <button type="button" wire:click="addItem(null, '{{ $sv }}')" class="ml-auto flex h-5 w-5 items-center justify-center rounded-md text-eo-muted transition hover:bg-white hover:text-eo-teal-ink" title="Add an item here">＋</button>
            </div>
            <div data-gate-col="{{ $sv }}" class="flex min-h-[90px] flex-1 flex-col gap-2 p-2.5">
                @forelse ($col as $item)
                    @include('livewire.hub.partials.plan-studio.card', ['item' => $item, 'showStatus' => false])
                @empty
                    <button type="button" wire:click="addItem(null, '{{ $sv }}')" class="rounded-xl border border-dashed border-eo-line py-4 text-center text-eyebrow font-semibold text-eo-muted transition hover:border-eo-teal hover:text-eo-teal-ink">＋ Add item</button>
                @endforelse
            </div>
        </div>
    @endforeach
</div>
