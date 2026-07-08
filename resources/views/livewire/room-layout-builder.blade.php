<div>
    {{-- Header --}}
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('events.hub', [$event, 'tab' => 'venue']) }}" class="text-xs font-semibold text-gold-600 hover:text-gold-700">← {{ $event->name }} · Venue</a>
            <h2 class="mt-0.5 text-lg font-bold text-navy-900">{{ $room->name }} — Layout</h2>
            <p class="text-xs text-muted">{{ str($room->type)->replace('_', ' ')->title() }}
                @if ($room->capacity) · room capacity {{ number_format($room->capacity) }} @endif
                · <span class="font-semibold text-navy-900">{{ $seatTotal }}</span> seats placed</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" wire:click="clearAll" wire:confirm="Clear the whole layout?" @disabled(empty($elements)) class="h-9 rounded-xl border border-line bg-white px-3.5 text-xs font-semibold text-navy-700 transition hover:border-risk/40 hover:text-risk disabled:opacity-40">Clear</button>
            <a href="{{ route('events.room-layout.pdf', [$event, $room]) }}" class="btn-navy h-9 gap-1.5 px-4 text-xs !text-white"><x-icon name="chart" class="h-3.5 w-3.5" /> Export PDF</a>
        </div>
    </div>

    <div class="grid gap-5 xl:grid-cols-[220px_minmax(0,1fr)]">
        {{-- Palette --}}
        <div class="card h-fit p-4">
            <p class="field-label !mb-3">Add seating</p>
            <div class="grid grid-cols-2 gap-2.5">
                @foreach ($presets as $type => [$label, $seats, $w, $h])
                    <button type="button" wire:click="addElement('{{ $type }}')"
                            class="flex flex-col items-center gap-1 rounded-2xl border border-line bg-page/40 px-2 py-2.5 transition hover:border-gold-300 hover:bg-gold-50/40">
                        <span class="flex h-12 w-full items-center justify-center overflow-hidden">
                            <span style="transform: scale({{ round(min(56 / $w, 40 / $h), 3) }});">
                                <x-layout-element :type="$type" :seats="$seats" :w="$w" :h="$h" />
                            </span>
                        </span>
                        <span class="text-[0.62rem] font-bold text-navy-900">{{ $label }}</span>
                    </button>
                @endforeach
            </div>
            <p class="mt-3 text-[0.6rem] leading-snug text-muted">Click to drop onto the floor, then drag to position. Hover an item to rotate, change seats, or remove.</p>
        </div>

        {{-- Canvas --}}
        <div class="card overflow-auto p-4"
             x-data="{
                drag: null,
                down(e, id) { this.drag = { id, el: e.currentTarget, x: null, y: null }; e.currentTarget.setPointerCapture?.(e.pointerId); e.preventDefault(); },
                move(e) {
                    if (!this.drag) return;
                    const c = this.$refs.canvas.getBoundingClientRect();
                    let x = Math.max(0, Math.min(960, e.clientX - c.left));
                    let y = Math.max(0, Math.min(560, e.clientY - c.top));
                    this.drag.el.style.left = x + 'px'; this.drag.el.style.top = y + 'px';
                    this.drag.x = x; this.drag.y = y;
                },
                up() { if (this.drag && this.drag.x !== null) $wire.moveElement(this.drag.id, this.drag.x, this.drag.y); this.drag = null; }
             }"
             @pointermove.window="move($event)" @pointerup.window="up()">
            <div x-ref="canvas" class="relative mx-auto shrink-0 rounded-xl border border-line"
                 style="width:960px; height:560px; background:
                    linear-gradient(#E2E8F0 1px, transparent 1px) 0 0 / 100% 40px,
                    linear-gradient(90deg, #E2E8F0 1px, transparent 1px) 0 0 / 40px 100%,
                    #FBFCFE;">
                {{-- front-of-room marker --}}
                <div class="absolute inset-x-0 top-0 flex h-6 items-center justify-center rounded-t-xl bg-navy-900/90 text-[0.6rem] font-bold uppercase tracking-widest text-gold-300">Stage / Front</div>

                @forelse ($elements as $el)
                    <div wire:key="el-{{ $el['id'] }}" @pointerdown="down($event, '{{ $el['id'] }}')"
                         class="group absolute cursor-move touch-none select-none"
                         style="left:{{ $el['x'] }}px; top:{{ $el['y'] }}px; transform: translate(-50%, -50%);">
                        <div style="transform: rotate({{ $el['rot'] ?? 0 }}deg);">
                            <x-layout-element :type="$el['type']" :seats="$el['seats'] ?? 0" :w="$el['w'] ?? 96" :h="$el['h'] ?? 96" />
                        </div>
                        {{-- seat badge --}}
                        @if (($el['seats'] ?? 0) > 0)
                            <span class="pointer-events-none absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 text-[0.62rem] font-bold text-navy-900">{{ $el['seats'] }}</span>
                        @endif
                        {{-- hover controls --}}
                        <div class="absolute -top-9 left-1/2 flex -translate-x-1/2 items-center gap-0.5 rounded-lg border border-line bg-white px-1 py-0.5 opacity-0 shadow-lg transition group-hover:opacity-100" @pointerdown.stop>
                            <button type="button" wire:click="rotate('{{ $el['id'] }}')" class="flex h-6 w-6 items-center justify-center rounded text-navy-600 hover:bg-navy-50" title="Rotate">⟳</button>
                            @if (in_array($el['type'], ['round', 'banquet', 'boardroom', 'crescent', 'ushape', 'classroom', 'theater']))
                                <button type="button" wire:click="changeSeats('{{ $el['id'] }}', -1)" class="flex h-6 w-6 items-center justify-center rounded text-navy-600 hover:bg-navy-50">−</button>
                                <span class="px-0.5 text-[0.6rem] font-bold text-navy-900">{{ $el['seats'] ?? 0 }}</span>
                                <button type="button" wire:click="changeSeats('{{ $el['id'] }}', 1)" class="flex h-6 w-6 items-center justify-center rounded text-navy-600 hover:bg-navy-50">+</button>
                            @endif
                            <button type="button" wire:click="removeElement('{{ $el['id'] }}')" class="flex h-6 w-6 items-center justify-center rounded text-risk hover:bg-risk/10" title="Remove">✕</button>
                        </div>
                    </div>
                @empty
                    <div class="flex h-full items-center justify-center">
                        <p class="text-sm text-navy-300">Click a seating type on the left to start building →</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
