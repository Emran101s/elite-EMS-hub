@php
    $equipmentStatusMeta = \App\Models\EventRoom::equipmentStatusMeta();
@endphp
<div>
    {{-- Header --}}
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('events.hub', [$event, 'tab' => 'venue']) }}" class="text-xs font-semibold text-eo-teal-ink hover:text-eo-teal-deep">← {{ $event->name }} · Venue</a>
            <h2 class="mt-0.5 text-lg font-bold text-eo-text">{{ $room->name }}</h2>
            <p class="text-xs text-eo-muted">{{ str($room->type)->replace('_', ' ')->title() }}
                @if ($room->capacity) · room capacity {{ number_format($room->capacity) }} @endif
                · <span class="font-semibold text-eo-text">{{ $seatTotal }}</span> seats
                @if (count($room->requirements ?? [])) · <span class="font-semibold text-eo-text">{{ count($room->requirements) }}</span> equipment @endif
                @if ($room->totalCents()) · <span class="font-semibold text-eo-text">{{ $event->money($room->totalCents()) }}</span> total @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            {{-- exports — available on both the Layout and Equipment views --}}
            <a href="{{ route('events.room-layout.pdf', [$event, $room]) }}" target="_blank" class="eo-btn-ghost eo-btn-sm" title="Floor plan to scale + inspector">↧ Layout PDF</a>
            <a href="{{ route('events.room-equipment.pdf', [$event, $room]) }}" target="_blank" class="eo-btn-ghost eo-btn-sm" title="AV &amp; equipment prep sheet">↧ Equipment PDF</a>

            {{-- Workspace tabs --}}
            <div class="inline-flex rounded-xl border border-eo-line bg-white p-1">
                <button type="button" wire:click="$set('view', 'floor')"
                        class="rounded-lg px-4 py-1.5 text-xs font-bold transition {{ $view === 'floor' ? 'bg-eo-navy text-white' : 'text-eo-muted hover:text-eo-text' }}">⊞ Layout</button>
                <button type="button" wire:click="$set('view', 'equipment')"
                        class="rounded-lg px-4 py-1.5 text-xs font-bold transition {{ $view !== 'floor' ? 'bg-eo-navy text-white' : 'text-eo-muted hover:text-eo-text' }}">
                    🎛 Equipment @if (count($room->requirements ?? []))<span class="ml-1 rounded-full bg-eo-teal-soft px-1.5 text-eyebrow text-eo-teal-ink">{{ count($room->requirements) }}</span>@endif
                </button>
            </div>
        </div>
    </div>

    @if ($view === 'floor')
        @php
            $roomW = is_numeric($width_m) && (float) $width_m > 0 ? (float) $width_m : null;
            $roomL = is_numeric($length_m) && (float) $length_m > 0 ? (float) $length_m : null;
            // One scale for both axes (keeps circles round); venue rectangle centred in the 960×560 canvas.
            $scale = $roomW && $roomL ? min(960 / $roomW, 560 / $roomL) : null;
            $venW = $scale ? (int) round($roomW * $scale) : 960;
            $venH = $scale ? (int) round($roomL * $scale) : 560;
            $offX = (int) round((960 - $venW) / 2);
            $offY = (int) round((560 - $venH) / 2);
            $gridM = $scale ? 1 : null;
            $gridPx = $scale ? $scale * $gridM : 40;
            $fmtM = fn ($n) => rtrim(rtrim(number_format($n, 1), '0'), '.');
            $seatingEls = collect($elements)->filter(fn ($e) => ($presets[$e['type']][4] ?? 'seating') === 'seating');
            $tablesCount = $seatingEls->reject(fn ($e) => $e['type'] === 'chair')->count();
            $area = $roomW && $roomL ? $roomW * $roomL : null;
        @endphp
        <div class="grid gap-4 xl:grid-cols-[196px_minmax(0,1fr)_300px]">
            {{-- LEFT · shape palette --}}
            <div class="eo-soft-card h-fit p-3.5">
                {{-- ✦ Seating generator — opens a full-size modal so the controls are readable --}}
                <button type="button" wire:click="openSeatModal"
                        class="mb-1 flex w-full items-center justify-center gap-1.5 rounded-xl bg-gradient-to-r from-eo-teal-lit to-eo-teal-deep px-3 py-2.5 text-xs font-bold text-white shadow-eo-teal-glow transition hover:brightness-105">
                    <span>✦</span> Auto-generate seating
                </button>
                @if ($seatMsg)<p class="mb-1 rounded-lg bg-emerald-50 px-2 py-1.5 text-eyebrow font-semibold leading-tight text-emerald-700">{{ $seatMsg }}</p>@endif

                {{-- Only the building blocks — the pre-designed styles now live in Generate.
                     Each drops at its real size and can be resized in the inspector. --}}
                @php
                    $palette = [
                        'Tables & chairs' => ['table', 'round', 'chair'],
                        'Equipment & staging' => ['stage', 'screen', 'podium', 'entrance', 'booth'],
                    ];
                @endphp
                @foreach ($palette as $gLabel => $types)
                    <p class="eo-field-label {{ $loop->first ? 'mt-3.5 !mb-2.5 border-t border-eo-line pt-3' : 'mt-3.5 !mb-2.5' }}">{{ $gLabel }}</p>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($types as $type)
                            @php [$label, $seats, $w, $h] = $presets[$type]; @endphp
                            <button type="button" wire:click="addElement('{{ $type }}')"
                                    class="group flex flex-col items-center gap-1 rounded-xl border border-eo-line bg-eo-workspace/40 px-1.5 py-2 transition hover:border-eo-teal hover:bg-eo-teal-soft/50 hover:shadow-sm">
                                <span class="flex h-11 w-full items-center justify-center overflow-hidden">
                                    <span class="block" style="transform: scale({{ round(min(50 / $w, 38 / $h), 3) }}); transform-origin: center;">
                                        <x-layout-element :type="$type" :seats="$type === 'chair' ? 0 : ($type === 'round' ? 8 : 0)" :w="$w" :h="$h" />
                                    </span>
                                </span>
                                <span class="text-eyebrow font-bold text-eo-text">{{ $type === 'table' ? 'Table' : $label }}</span>
                            </button>
                        @endforeach
                    </div>
                @endforeach
                <p class="mt-3 border-t border-eo-line pt-2.5 text-eyebrow leading-snug text-eo-muted">Click to drop → drag to place → click to select &amp; resize.</p>
            </div>

            {{-- CENTER · canvas --}}
            <div class="eo-soft-card overflow-auto p-4"
                 x-data="{
                    drag: null, moved: false, spin: null,
                    down(e, id) { this.drag = { id, el: e.currentTarget, x: null, y: null }; this.moved = false; e.currentTarget.setPointerCapture?.(e.pointerId); e.preventDefault(); },
                    move(e) {
                        if (!this.drag) return;
                        const c = this.$refs.canvas.getBoundingClientRect();
                        let x = Math.max(0, Math.min(960, e.clientX - c.left));
                        let y = Math.max(0, Math.min(560, e.clientY - c.top));
                        this.drag.el.style.left = x + 'px'; this.drag.el.style.top = y + 'px';
                        this.drag.x = x; this.drag.y = y; this.moved = true;
                    },
                    up() {
                        if (this.drag) {
                            if (this.moved && this.drag.x !== null) { $wire.moveElement(this.drag.id, this.drag.x, this.drag.y); }
                            else { $wire.selectElement(this.drag.id); }
                        }
                        this.drag = null;
                    },
                    startSpin(e, id) {
                        const c = this.$refs.canvas.getBoundingClientRect();
                        const wrap = e.currentTarget.closest('[data-x]');
                        this.spin = { id, cx: c.left + (+wrap.dataset.x), cy: c.top + (+wrap.dataset.y), inner: wrap.querySelector('[data-rot]'), deg: +wrap.dataset.rot || 0 };
                    },
                    moveSpin(e) {
                        if (!this.spin) return;
                        let d = Math.atan2(e.clientY - this.spin.cy, e.clientX - this.spin.cx) * 180 / Math.PI + 90;
                        if (!e.shiftKey) d = Math.round(d / 5) * 5;   // hold Shift for free rotation
                        d = ((Math.round(d) % 360) + 360) % 360;
                        this.spin.deg = d;
                        this.spin.inner.style.transform = 'rotate(' + d + 'deg)';
                    },
                    endSpin() { if (this.spin) { $wire.setRotation(this.spin.id, this.spin.deg); this.spin = null; } },
                    onKey(e) {
                        const t = (e.target.tagName || '');
                        if (t === 'INPUT' || t === 'TEXTAREA' || t === 'SELECT') return;
                        const sel = $wire.get('selectedId');
                        if (sel && (e.key === 'Delete' || e.key === 'Backspace')) { e.preventDefault(); $wire.removeElement(sel); }
                    }
                 }"
                 @pointermove.window="move($event); moveSpin($event)" @pointerup.window="up(); endSpin()" @keydown.window="onKey($event)">

                {{-- bulk toolbar: select-all / delete-selected / clear-all --}}
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <span class="text-eyebrow font-bold uppercase tracking-wide text-eo-muted">{{ count($elements) }} {{ \Illuminate\Support\Str::plural('item', count($elements)) }} on the plan</span>
                    <button type="button" wire:click="selectAll" @disabled(empty($elements))
                            class="rounded-lg border border-eo-line bg-white px-2.5 py-1.5 text-eyebrow font-bold text-eo-text transition enabled:hover:border-eo-teal disabled:opacity-40">Select all</button>
                    @if (count($selectedIds ?? []))
                        <button type="button" wire:click="deleteSelected"
                                class="rounded-lg bg-eo-risk px-2.5 py-1.5 text-eyebrow font-bold text-white transition hover:brightness-110">Delete selected ({{ count($selectedIds) }})</button>
                        <button type="button" wire:click="clearSelection"
                                class="rounded-lg px-2 py-1.5 text-eyebrow font-bold text-eo-muted hover:text-eo-text">Cancel</button>
                    @endif
                    <x-confirm title="Delete everything on the plan?" confirm="Clear" run="$wire.clearAll" :disabled="empty($elements)"
                               class="ml-auto rounded-lg border border-eo-line bg-white px-2.5 py-1.5 text-eyebrow font-bold text-eo-risk-ink transition enabled:hover:bg-eo-risk-soft disabled:opacity-40">Clear all</x-confirm>
                </div>

                <div class="mx-auto shrink-0" style="width:960px;">
                    <div x-ref="canvas" @pointerdown.self="$wire.selectElement('')" class="relative rounded-xl border border-eo-line"
                         style="width:960px; height:560px; background: var(--color-eo-card);
                            @if(!$scale) background-image:
                                linear-gradient(var(--color-eo-line) 1px, transparent 1px),
                                linear-gradient(90deg, var(--color-eo-line) 1px, transparent 1px);
                            background-size: 40px 40px, 40px 40px; @endif">

                        {{-- centred venue floor (to scale) — CAD-style drawing, left as-is --}}
                        @if ($scale)
                            <div class="pointer-events-none absolute rounded-lg border-2 border-navy-200"
                                 style="left:{{ $offX }}px; top:{{ $offY }}px; width:{{ $venW }}px; height:{{ $venH }}px; background:
                                    linear-gradient(var(--color-line) 1px, transparent 1px) 0 0 / 100% {{ round($gridPx) }}px,
                                    linear-gradient(90deg, var(--color-line) 1px, transparent 1px) 0 0 / {{ round($gridPx) }}px 100%,
                                    var(--plate);"></div>
                            {{-- width ruler (top) --}}
                            <span class="pointer-events-none absolute -translate-x-1/2 rounded bg-white px-1.5 text-eyebrow font-bold text-navy-500"
                                  style="left:{{ $offX + $venW / 2 }}px; top:{{ max(2, $offY - 16) }}px;">↔ {{ $fmtM($roomW) }} m</span>
                            {{-- length ruler (left) --}}
                            <span class="pointer-events-none absolute origin-center -rotate-90 whitespace-nowrap rounded bg-white px-1.5 text-eyebrow font-bold text-navy-500"
                                  style="left:{{ max(2, $offX - 24) }}px; top:{{ $offY + $venH / 2 }}px;">↔ {{ $fmtM($roomL) }} m</span>
                            <span class="pointer-events-none absolute rounded bg-navy-50 px-1.5 text-eyebrow font-semibold text-navy-400"
                                  style="left:{{ $offX + 6 }}px; top:{{ $offY + 6 }}px;">1 grid = {{ $gridM }} m</span>
                        @endif

                        @forelse ($elements as $el)
                            @php $sel = $selectedId === $el['id']; $bulk = in_array($el['id'], $selectedIds ?? [], true); @endphp
                            <div wire:key="el-{{ $el['id'] }}" data-x="{{ $el['x'] }}" data-y="{{ $el['y'] }}" data-rot="{{ $el['rot'] ?? 0 }}"
                                 @pointerdown="down($event, '{{ $el['id'] }}')"
                                 @class(['group absolute cursor-move touch-none select-none', 'rounded-lg ring-2 ring-info ring-offset-2' => $bulk])
                                 style="left:{{ $el['x'] }}px; top:{{ $el['y'] }}px; transform: translate(-50%, -50%); z-index: {{ $sel || $bulk ? 30 : 10 }};">
                                {{-- bulk-select checkbox (for select-all / delete-selected) --}}
                                <button type="button" wire:click="toggleInSelection('{{ $el['id'] }}')" @pointerdown.stop
                                        class="absolute -left-2.5 -top-2.5 z-40 flex h-5 w-5 items-center justify-center rounded-md border-2 border-white text-eyebrow font-black shadow transition {{ $bulk ? 'bg-info text-white' : 'bg-white text-eo-muted opacity-0 group-hover:opacity-100' }}"
                                        title="Select for bulk delete">{{ $bulk ? '✓' : '＋' }}</button>
                                @if (($el['type'] ?? '') === 'seatblock')
                                    @php $geo = \App\Models\EventRoom::seatChairs($el, $scale ?: 12); @endphp
                                    <div data-rot style="transform: rotate({{ $el['rot'] ?? 0 }}deg); width:{{ $geo['w'] }}px; height:{{ $geo['h'] }}px;" class="relative {{ $sel ? 'rounded-lg ring-2 ring-eo-teal ring-offset-4' : '' }}">
                                        @foreach ($geo['desks'] as [$dx, $dy, $dw, $dh])
                                            <span class="absolute rounded-sm bg-navy-100 ring-1 ring-navy-200" style="left:{{ $geo['w'] / 2 + $dx - $dw / 2 }}px; top:{{ $geo['h'] / 2 + $dy - $dh / 2 }}px; width:{{ $dw }}px; height:{{ $dh }}px;"></span>
                                        @endforeach
                                        @foreach ($geo['tables'] as [$tx, $ty, $td])
                                            <span class="absolute rounded-full bg-navy-50 ring-1 ring-navy-300" style="left:{{ $geo['w'] / 2 + $tx - $td / 2 }}px; top:{{ $geo['h'] / 2 + $ty - $td / 2 }}px; width:{{ $td }}px; height:{{ $td }}px;"></span>
                                        @endforeach
                                        @foreach ($geo['rects'] ?? [] as [$rx, $ry, $rw, $rh])
                                            <span class="absolute rounded bg-navy-100 ring-1 ring-navy-300" style="left:{{ $geo['w'] / 2 + $rx - $rw / 2 }}px; top:{{ $geo['h'] / 2 + $ry - $rh / 2 }}px; width:{{ $rw }}px; height:{{ $rh }}px;"></span>
                                        @endforeach
                                        @foreach ($geo['chairs'] as [$cx, $cy])
                                            <span class="absolute bg-navy-500" style="left:{{ $geo['w'] / 2 + $cx - $geo['chairPx'] / 2 }}px; top:{{ $geo['h'] / 2 + $cy - $geo['chairPx'] / 2 }}px; width:{{ $geo['chairPx'] }}px; height:{{ $geo['chairPx'] }}px; border-radius:1px;"></span>
                                        @endforeach
                                        @foreach ($geo['labels'] ?? [] as [$lx, $ly, $lt])
                                            <span class="pointer-events-none absolute -translate-x-full -translate-y-1/2 pr-0.5 text-eyebrow font-bold text-navy-400" style="left:{{ $geo['w'] / 2 + $lx }}px; top:{{ $geo['h'] / 2 + $ly }}px;">{{ $lt }}</span>
                                        @endforeach
                                    </div>
                                    <span class="pointer-events-none absolute -bottom-2.5 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-eo-navy px-1.5 py-px text-eyebrow font-bold text-white">{{ $el['seats'] ?? 0 }} seats</span>
                                @else
                                    <div data-rot style="transform: rotate({{ $el['rot'] ?? 0 }}deg);" class="{{ $sel ? 'rounded-lg ring-2 ring-eo-teal ring-offset-2' : '' }}">
                                        <x-layout-element :type="$el['type']" :seats="$el['seats'] ?? 0" :w="$el['w'] ?? 96" :h="$el['h'] ?? 96" :scale="$scale" />
                                    </div>
                                    @if (($el['seats'] ?? 0) > 0)
                                        <span class="pointer-events-none absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 text-eyebrow font-bold text-navy-900">{{ $el['seats'] }}</span>
                                    @endif
                                @endif

                                {{-- item name, upright, above the piece --}}
                                @if (! empty($el['name']))
                                    <span class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-0.5 -translate-x-1/2 whitespace-nowrap rounded bg-white/90 px-1.5 py-px text-eyebrow font-bold text-eo-text shadow-sm ring-1 ring-eo-line">{{ $el['name'] }}</span>
                                @endif

                                @if ($sel)
                                    {{-- drag-to-rotate handle --}}
                                    <div class="absolute bottom-full left-1/2 mb-1 flex -translate-x-1/2 flex-col items-center">
                                        <div @pointerdown.stop.prevent="startSpin($event, '{{ $el['id'] }}')"
                                             class="flex h-6 w-6 cursor-grab items-center justify-center rounded-full border-2 border-white bg-eo-teal text-micro text-white shadow-md active:cursor-grabbing"
                                             title="Drag to rotate · hold Shift for free angle">⟳</div>
                                        <div class="h-3 w-px bg-eo-teal-lit"></div>
                                    </div>
                                    {{-- delete --}}
                                    <button type="button" wire:click="removeElement('{{ $el['id'] }}')" @pointerdown.stop
                                            class="absolute -right-2.5 -top-2.5 flex h-5 w-5 items-center justify-center rounded-full bg-eo-risk text-eyebrow font-bold text-white shadow-md transition hover:scale-110"
                                            title="Delete (or press Delete key)">✕</button>
                                @endif
                            </div>
                        @empty
                            <div class="pointer-events-none flex h-full items-center justify-center">
                                <p class="text-sm text-eo-muted">Pick a shape on the left to start building →</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- RIGHT · inspector rail --}}
            <div class="xl:sticky xl:top-4 xl:h-fit">
                <div class="eo-soft-card overflow-hidden">
                    {{-- rail header --}}
                    <div class="flex items-center justify-between border-b border-eo-line bg-eo-navy px-4 py-3">
                        <span class="text-xs font-bold uppercase tracking-[0.14em] text-eo-teal-lit">Inspector</span>
                        <x-confirm title="Clear the whole layout?" confirm="Clear" run="$wire.clearAll" :disabled="empty($elements)" class="rounded-lg bg-white/10 px-2.5 py-1 text-eyebrow font-bold text-white transition hover:bg-eo-risk/70 disabled:opacity-30">Clear</x-confirm>
                    </div>

                    {{-- Room dimensions --}}
                    <div class="border-b border-eo-line p-4">
                        <p class="eo-field-label !mb-2.5 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-eo-teal"></span> Room dimensions</p>
                        <div class="flex items-end gap-2">
                            <div class="flex-1">
                                <label class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-eo-muted">Width · m</label>
                                <input type="number" min="0" step="0.5" wire:model.live.debounce.400ms="width_m" class="eo-input h-9 w-full text-sm" placeholder="—">
                            </div>
                            <span class="pb-2 text-eo-muted">×</span>
                            <div class="flex-1">
                                <label class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-eo-muted">Length · m</label>
                                <input type="number" min="0" step="0.5" wire:model.live.debounce.400ms="length_m" class="eo-input h-9 w-full text-sm" placeholder="—">
                            </div>
                        </div>
                        @if ($area)
                            <div class="mt-3 flex items-center justify-between rounded-xl bg-eo-workspace/60 px-3 py-2">
                                <span class="text-eyebrow font-semibold text-eo-muted">Floor area</span>
                                <span class="text-sm font-bold text-eo-text">{{ number_format($area, 0) }} m²</span>
                            </div>
                        @else
                            <p class="mt-2 text-eyebrow text-eo-muted">Set dimensions for a to-scale grid, rulers &amp; real table sizes.</p>
                        @endif
                    </div>

                    {{-- Selection OR stats --}}
                    @if ($selected)
                        @php
                            $isRound = in_array($selected['type'], ['round', 'banquet', 'chair', 'podium'], true);
                            $sid = $selected['id'];
                            $rot = (int) ($selected['rot'] ?? 0);
                            $wM = $scale ? round(($selected['w'] ?? 96) / $scale, 1) : null;
                            $hM = $scale ? round(($selected['h'] ?? 96) / $scale, 1) : null;
                            $label = $presets[$selected['type']][0] ?? $selected['type'];
                        @endphp
                        <div class="p-4" wire:key="props-{{ $sid }}">
                            <div class="mb-3 flex items-center justify-between">
                                <p class="eo-field-label !mb-0 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-eo-teal"></span> {{ $label }}</p>
                                <button type="button" wire:click="removeElement('{{ $sid }}')" class="text-eyebrow font-bold text-eo-risk-ink hover:underline">Delete</button>
                            </div>

                            {{-- NAME --}}
                            <label class="mb-3 block">
                                <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-eo-muted">Name / label</span>
                                <input type="text" maxlength="40" value="{{ $selected['name'] ?? '' }}"
                                       wire:change="nameElement('{{ $sid }}', $event.target.value)"
                                       placeholder="e.g. Head table, Booth A, VIP round"
                                       class="eo-input h-9 w-full text-sm">
                            </label>

                            {{-- SEATBLOCK · edit the arrangement's counts & table sizes --}}
                            @if ($selected['type'] === 'seatblock')
                                @php
                                    $sb = $selected;
                                    $arr = $sb['arr'] ?? 'theater';
                                    $mode = $sb['mode'] ?? '';
                                    // [label, field, current value] — integer count fields
                                    $counts = match (true) {
                                        $mode === 'utables' => [
                                            ['Head tables', 'headTables', $sb['headTables'] ?? 2],
                                            ['Tables / arm', 'armTables', $sb['armTables'] ?? 3],
                                            ['Chairs / table', 'perTable', $sb['perTable'] ?? 3],
                                        ],
                                        in_array($arr, ['banquet', 'cabaret'], true) => [
                                            ['Tables across', 'tCols', $sb['tCols'] ?? 1],
                                            ['Rows of tables', 'tRows', $sb['tRows'] ?? 1],
                                            ['Seats / table', 'perTable', $sb['perTable'] ?? 10],
                                        ],
                                        in_array($arr, ['ushape', 'boardroom', 'hollowsquare'], true) => [
                                            ['Seats along width', 'pCols', $sb['pCols'] ?? 6],
                                            ['Seats along depth', 'pRows', $sb['pRows'] ?? 4],
                                        ],
                                        $arr === 'circle' => [['Chairs', 'count', $sb['count'] ?? 12]],
                                        default => [   // theater / classroom / chevron grids
                                            ['Rows', 'rows', $sb['rows'] ?? 1],
                                            ['Columns', 'cols', $sb['cols'] ?? 1],
                                        ],
                                    };
                                    // centimetre table-size field for this arrangement, if any
                                    $cmField = match (true) {
                                        $mode === 'utables' => ['Table · cm (L × D)', 'tableW_m', 'tableH_m'],
                                        in_array($arr, ['banquet', 'cabaret'], true) => ['Table Ø · cm', 'tableDia_m', null],
                                        default => null,
                                    };
                                @endphp
                                <p class="eo-field-label !mb-1.5 !text-eyebrow">Arrangement · edit</p>
                                <div class="mb-2 grid grid-cols-2 gap-2">
                                    @foreach ($counts as [$lbl, $fld, $v])
                                        <label class="block">
                                            <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-eo-muted">{{ $lbl }}</span>
                                            <input type="number" min="0" value="{{ $v }}"
                                                   wire:change="updateSeatblock('{{ $sid }}', '{{ $fld }}', $event.target.value)"
                                                   class="eo-input h-9 w-full text-sm">
                                        </label>
                                    @endforeach
                                    @if ($cmField)
                                        <label class="col-span-2 block">
                                            <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-eo-muted">{{ $cmField[0] }}</span>
                                            <div class="flex items-center gap-1">
                                                <input type="number" step="10" value="{{ (int) round(($sb[$cmField[1]] ?? 1.8) * 100) }}"
                                                       wire:change="updateSeatblock('{{ $sid }}', '{{ $cmField[1] }}', $event.target.value)"
                                                       class="eo-input h-9 w-full text-sm">
                                                @if ($cmField[2])
                                                    <span class="text-eo-muted">×</span>
                                                    <input type="number" step="10" value="{{ (int) round(($sb[$cmField[2]] ?? 0.6) * 100) }}"
                                                           wire:change="updateSeatblock('{{ $sid }}', '{{ $cmField[2] }}', $event.target.value)"
                                                           class="eo-input h-9 w-full text-sm">
                                                @endif
                                            </div>
                                        </label>
                                    @endif
                                </div>
                                <div class="mb-3 flex items-center justify-between rounded-xl bg-eo-workspace/60 px-3 py-2">
                                    <span class="text-eyebrow font-bold uppercase tracking-wide text-eo-muted">Total chairs</span>
                                    <span class="text-base font-black text-eo-text">{{ $sb['seats'] ?? 0 }}</span>
                                </div>
                            @endif

                            {{-- SIZE (non-seatblock only) --}}
                            @unless ($selected['type'] === 'seatblock')
                            <p class="eo-field-label !mb-1.5 !text-eyebrow">Size {{ $scale ? '· metres' : '· pixels' }}</p>
                            @if ($scale)
                                @if ($isRound)
                                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-eo-muted">Diameter (m)</label>
                                    <input type="number" min="0.2" step="0.1" value="{{ $wM }}" wire:change="setSizeMeters('{{ $sid }}', 'both', $event.target.value)" class="eo-input mb-3 h-9 w-full text-sm">
                                @else
                                    <div class="mb-3 grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-eo-muted">Width (m)</label>
                                            <input type="number" min="0.2" step="0.1" value="{{ $wM }}" wire:change="setSizeMeters('{{ $sid }}', 'w', $event.target.value)" class="eo-input h-9 w-full text-sm">
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-eo-muted">Length (m)</label>
                                            <input type="number" min="0.2" step="0.1" value="{{ $hM }}" wire:change="setSizeMeters('{{ $sid }}', 'h', $event.target.value)" class="eo-input h-9 w-full text-sm">
                                        </div>
                                    </div>
                                @endif
                            @else
                                {{-- no room size yet → px steppers + hint --}}
                                @if ($isRound)
                                    <div class="mb-2 flex items-center gap-1">
                                        <button type="button" wire:click="resizeElement('{{ $sid }}', 'both', -10)" class="flex h-8 w-8 items-center justify-center rounded-lg border border-eo-line bg-white text-sm font-bold text-eo-text transition hover:border-eo-teal">−</button>
                                        <span class="flex-1 text-center text-xs font-bold text-eo-text">{{ $selected['w'] ?? 96 }} px</span>
                                        <button type="button" wire:click="resizeElement('{{ $sid }}', 'both', 10)" class="flex h-8 w-8 items-center justify-center rounded-lg border border-eo-line bg-white text-sm font-bold text-eo-text transition hover:border-eo-teal">+</button>
                                    </div>
                                @else
                                    <div class="mb-2 grid grid-cols-2 gap-2">
                                        <div class="flex items-center gap-1"><button type="button" wire:click="resizeElement('{{ $sid }}', 'w', -10)" class="flex h-8 w-8 items-center justify-center rounded-lg border border-eo-line bg-white text-sm font-bold text-eo-text transition hover:border-eo-teal">−</button><span class="flex-1 text-center text-micro font-bold text-eo-text">W</span><button type="button" wire:click="resizeElement('{{ $sid }}', 'w', 10)" class="flex h-8 w-8 items-center justify-center rounded-lg border border-eo-line bg-white text-sm font-bold text-eo-text transition hover:border-eo-teal">+</button></div>
                                        <div class="flex items-center gap-1"><button type="button" wire:click="resizeElement('{{ $sid }}', 'h', -10)" class="flex h-8 w-8 items-center justify-center rounded-lg border border-eo-line bg-white text-sm font-bold text-eo-text transition hover:border-eo-teal">−</button><span class="flex-1 text-center text-micro font-bold text-eo-text">L</span><button type="button" wire:click="resizeElement('{{ $sid }}', 'h', 10)" class="flex h-8 w-8 items-center justify-center rounded-lg border border-eo-line bg-white text-sm font-bold text-eo-text transition hover:border-eo-teal">+</button></div>
                                    </div>
                                @endif
                                <p class="mb-3 text-eyebrow text-amber-700">Set room dimensions above to size in metres.</p>
                            @endif
                            @endunless

                            {{-- ROTATION --}}
                            <div class="mb-3">
                                <label class="mb-1 flex items-center justify-between text-eyebrow font-bold uppercase tracking-wide text-eo-muted"><span>Rotation</span><span class="text-eo-text">{{ $rot }}°</span></label>
                                <div class="flex items-center gap-1.5">
                                    <button type="button" wire:click="rotateBy('{{ $sid }}', -15)" class="flex h-8 w-8 items-center justify-center rounded-lg border border-eo-line bg-white text-sm font-bold text-eo-text transition hover:border-eo-teal" title="−15°">⟲</button>
                                    <input type="range" min="0" max="359" step="1" value="{{ $rot }}" wire:change="setRotation('{{ $sid }}', $event.target.value)" class="h-1.5 flex-1 accent-eo-teal">
                                    <button type="button" wire:click="rotateBy('{{ $sid }}', 15)" class="flex h-8 w-8 items-center justify-center rounded-lg border border-eo-line bg-white text-sm font-bold text-eo-text transition hover:border-eo-teal" title="+15°">⟳</button>
                                </div>
                                <div class="mt-1.5 flex gap-1">
                                    @foreach ([0, 45, 90, 135, 180, 270] as $deg)
                                        <button type="button" wire:click="setRotation('{{ $sid }}', {{ $deg }})" class="flex-1 rounded-md border border-eo-line py-0.5 text-eyebrow font-bold {{ $rot === $deg ? 'bg-eo-navy text-white' : 'text-eo-muted hover:border-eo-teal' }}">{{ $deg }}°</button>
                                    @endforeach
                                </div>
                                <p class="mt-1 text-eyebrow text-eo-muted">Tip: drag the teal handle on the canvas to rotate freely (hold Shift for any angle).</p>
                            </div>

                            {{-- SEATS --}}
                            @if (($selected['seats'] ?? 0) > 0 || in_array($selected['type'], ['round', 'banquet', 'boardroom', 'crescent', 'ushape', 'classroom', 'theater'], true))
                                <div class="flex items-center justify-between rounded-xl bg-eo-workspace/60 px-3 py-2">
                                    <span class="text-eyebrow font-bold uppercase tracking-wide text-eo-muted">Seats</span>
                                    <div class="flex items-center gap-1">
                                        <button type="button" wire:click="changeSeats('{{ $sid }}', -1)" class="flex h-6 w-6 items-center justify-center rounded-lg border border-eo-line bg-white text-sm font-bold text-eo-text transition hover:border-eo-teal">−</button>
                                        <span class="min-w-7 text-center text-xs font-bold text-eo-text">{{ $selected['seats'] ?? 0 }}</span>
                                        <button type="button" wire:click="changeSeats('{{ $sid }}', 1)" class="flex h-6 w-6 items-center justify-center rounded-lg border border-eo-line bg-white text-sm font-bold text-eo-text transition hover:border-eo-teal">+</button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="p-4">
                            <p class="eo-field-label !mb-2.5 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-eo-muted"></span> Layout stats</p>
                            <div class="grid grid-cols-2 gap-2">
                                <div class="rounded-xl bg-eo-workspace/60 px-3 py-2.5"><p class="text-lg font-bold text-eo-text">{{ $seatTotal }}</p><p class="text-eyebrow uppercase tracking-wide text-eo-muted">Seats</p></div>
                                <div class="rounded-xl bg-eo-workspace/60 px-3 py-2.5"><p class="text-lg font-bold text-eo-text">{{ $tablesCount }}</p><p class="text-eyebrow uppercase tracking-wide text-eo-muted">Tables</p></div>
                                <div class="rounded-xl bg-eo-workspace/60 px-3 py-2.5"><p class="text-lg font-bold text-eo-text">{{ count($elements) }}</p><p class="text-eyebrow uppercase tracking-wide text-eo-muted">Elements</p></div>
                                <div class="rounded-xl bg-eo-workspace/60 px-3 py-2.5">
                                    <p class="text-lg font-bold text-eo-text">{{ $area && $seatTotal ? number_format($seatTotal / $area, 1) : '—' }}</p>
                                    <p class="text-eyebrow uppercase tracking-wide text-eo-muted">Seats / m²</p>
                                </div>
                            </div>
                            @if ($room->capacity && $seatTotal)
                                <div class="mt-3">
                                    <div class="mb-1 flex justify-between text-eyebrow font-semibold text-eo-muted"><span>Capacity fill</span><span>{{ min(100, (int) round($seatTotal / $room->capacity * 100)) }}%</span></div>
                                    <div class="h-1.5 overflow-hidden rounded-full bg-eo-bg"><div class="h-full rounded-full bg-eo-teal" style="width: {{ min(100, (int) round($seatTotal / $room->capacity * 100)) }}%"></div></div>
                                </div>
                            @endif
                            <p class="mt-3 text-eyebrow leading-snug text-eo-muted">Select any element on the canvas to resize it, change seats, or rotate.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @else
        {{-- ══════════════ EQUIPMENT · COSTED, SYNCS TO BUDGET ══════════════ --}}
        @php $reqs = $room->requirements ?? []; $reqTotal = $room->requirementsTotalCents(); $venueTotal = $room->totalCents(); @endphp
        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_300px]">
            {{-- MAIN · equipment list --}}
            <div class="eo-soft-card p-6">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-eo-text">Venue Equipment</h3>
                        <p class="text-eyebrow text-eo-muted">Everything this venue needs, each with a price. The total flows into the Budget under Venues.</p>
                    </div>
                    <span class="text-lg font-bold text-eo-text">{{ $reqTotal ? $event->money($reqTotal) : '—' }}</span>
                </div>

                @if ($catalog->isNotEmpty())
                    <select wire:change="pickReq($event.target.value)" class="eo-select mb-2 h-10 w-full text-sm">
                        <option value="">— Pick from Equipment Catalog —</option>
                        @foreach ($catalog as $ci)<option value="{{ $ci->id }}">{{ $ci->name }}@if ($ci->unit_price_cents) · {{ number_format($ci->unit_price_cents / 100) }}@endif</option>@endforeach
                    </select>
                @endif
                <div class="mb-1.5 flex flex-wrap items-center gap-1.5">
                    <input type="text" wire:model="reqName" wire:keydown.enter="addRequirement" maxlength="120" placeholder="Equipment (or pick from catalog)" class="eo-input h-10 min-w-0 flex-1 text-sm">
                    <span class="text-eyebrow font-semibold text-eo-muted">{{ $event->currencySymbol() }}</span>
                    <input type="number" min="0" step="0.01" wire:model="reqCost" wire:keydown.enter="addRequirement" placeholder="Rate" title="Price per unit, per day" class="eo-input h-10 w-24 text-sm">
                    <span class="text-eyebrow font-semibold text-eo-muted">×</span>
                    <input type="number" min="1" wire:model="reqQty" wire:keydown.enter="addRequirement" placeholder="Qty" title="How many" class="eo-input h-10 w-16 text-sm">
                    <span class="text-eyebrow font-semibold text-eo-muted">×</span>
                    <input type="number" min="1" wire:model="reqDays" wire:keydown.enter="addRequirement" placeholder="Days" title="For how many days" class="eo-input h-10 w-16 text-sm">
                    <button type="button" wire:click="addRequirement" class="eo-btn-primary h-10 shrink-0 !px-4 text-xs">{{ $editingReqId ? '✓ Save' : '＋ Add' }}</button>
                    @if ($editingReqId)
                        <button type="button" wire:click="cancelEditRequirement" class="h-10 shrink-0 rounded-xl px-3 text-xs font-bold text-eo-muted transition hover:text-eo-text">Cancel</button>
                    @endif
                </div>
                <p class="mb-3 text-eyebrow text-eo-muted">
                    The rate is <b>per unit, per day</b> — 12 table microphones over 5 days is 12 × 5, not a total worked out on paper.
                    Blank counts as one. This venue is held for <b>{{ $room->chargedDays() }}</b> {{ str('day')->plural($room->chargedDays()) }}.
                </p>
                @error('reqName') <p class="mb-2 text-eyebrow font-semibold text-eo-risk-ink">{{ $message }}</p> @enderror

                <ul class="divide-y divide-eo-line">
                    @forelse ($reqs as $req)
                        <li wire:key="dreq-{{ $req['id'] }}" @class(['group flex items-center justify-between gap-2 py-2.5', 'rounded-lg bg-eo-teal-soft/60 px-2' => $editingReqId === $req['id']])>
                            @php
                                $rQty = max(1, (int) ($req['qty'] ?? 1)); $rDays = max(1, (int) ($req['days'] ?? 1));
                                $rSt = $req['status'] ?? 'needed';
                                $stMeta = $equipmentStatusMeta[$rSt] ?? $equipmentStatusMeta['needed'];
                                $stLabel = $stMeta['label'];
                                $stClass = $stMeta['class'];
                            @endphp
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm text-eo-text">{{ $req['name'] }}</span>
                                {{-- The working shown, so the total can be checked without opening it. --}}
                                <span class="block text-eyebrow text-eo-muted">
                                    {{ $event->money($req['cost_cents'] ?? 0) }}
                                    @if ($rQty > 1) × {{ $rQty }} @endif
                                    @if ($rDays > 1) × {{ $rDays }} days @endif
                                    @if ($rQty === 1 && $rDays === 1) · one unit, one day @endif
                                </span>
                                {{-- Hired for longer than the room is held: either the
                                     room's days are wrong or this line's are. --}}
                                @if ($rDays > $room->chargedDays())
                                    <span class="mt-0.5 block text-eyebrow font-semibold text-amber-700">
                                        ⚠ {{ $rDays }} days, but the venue is only held for {{ $room->chargedDays() }}.
                                    </span>
                                @endif
                            </span>
                            <span class="flex shrink-0 items-center gap-3">
                                {{-- Tap to move it along; this is what the prep sheet prints. --}}
                                <button type="button" wire:click="advanceRequirement('{{ $req['id'] }}')"
                                        title="Needed → Requested → Confirmed → On-site"
                                        class="rounded-full px-2 py-0.5 text-eyebrow font-bold transition hover:opacity-75 {{ $stClass }}">{{ $stLabel }}</button>
                                <span class="text-sm font-semibold text-eo-text">{{ $event->money(\App\Models\EventRoom::requirementCents($req)) }}</span>
                                <button type="button" wire:click="editRequirement('{{ $req['id'] }}')" title="Edit" class="rounded-lg px-1.5 py-0.5 text-eyebrow font-bold text-eo-muted opacity-0 transition hover:bg-eo-bg hover:text-eo-text group-hover:opacity-100">✎</button>
                                <button type="button" wire:click="removeRequirement('{{ $req['id'] }}')" class="rounded-lg bg-eo-risk-soft px-1.5 py-0.5 text-eyebrow font-bold text-eo-risk opacity-0 transition hover:bg-eo-risk-soft/70 group-hover:opacity-100">✕</button>
                            </span>
                        </li>
                    @empty
                        <li class="py-6 text-center text-xs text-eo-muted">No equipment yet — add anything this venue needs with its price.</li>
                    @endforelse
                </ul>
            </div>

            {{-- RIGHT · totals rail --}}
            <div class="xl:sticky xl:top-12 xl:h-fit">
                <div class="eo-soft-card overflow-hidden">
                    <div class="border-b border-eo-line bg-eo-navy px-4 py-3">
                        <span class="text-xs font-bold uppercase tracking-[0.14em] text-eo-teal-lit">Cost Summary</span>
                    </div>
                    <div class="space-y-2 p-4 text-sm">
                        @php
                            // Spelled out, because "6 days" hides that one of
                            // them is a build day nobody has a session on.
                            $dParts = [($room->daysAreCounted() ? $room->daysOnTheAgenda() ?: 1 : $room->days).' '.($room->daysAreCounted() ? 'on the agenda' : 'set')];
                            if ($room->setup_days) $dParts[] = $room->setup_days.' setup';
                        @endphp
                        <div class="flex justify-between gap-2"><span class="text-eo-muted">Hire · {{ $event->money($room->cost_cents ?? 0) }} × {{ $room->chargedDays() }} {{ str('day')->plural($room->chargedDays()) }} <span class="text-eyebrow">({{ implode(' + ', $dParts) }})</span></span><span class="shrink-0 font-bold text-eo-text">{{ $room->cost_cents ? $event->money($room->hireCents()) : '—' }}</span></div>
                        <div class="flex justify-between"><span class="text-eo-muted">Equipment</span><span class="font-bold text-eo-text">{{ $reqTotal ? $event->money($reqTotal) : '—' }}</span></div>
                        <div class="flex items-center justify-between border-t border-eo-line pt-2"><span class="text-micro font-bold uppercase tracking-wide text-eo-text">Venue total</span><span class="text-base font-bold text-eo-text">{{ $event->money($venueTotal) }}</span></div>
                    </div>
                    <div class="border-t border-eo-line p-4">
                        <a href="{{ route('events.hub', [$event, 'tab' => 'budget']) }}" class="flex h-9 w-full items-center justify-center gap-1.5 rounded-xl border border-eo-teal/30 bg-eo-teal-soft/60 text-xs font-bold text-eo-teal-ink transition hover:bg-eo-teal-soft">Syncs to Budget · Venues →</a>
                        <a href="{{ route('requirements.index') }}" class="mt-2 block text-center text-eyebrow font-semibold text-eo-teal-ink hover:text-eo-teal-deep">Manage the Equipment Catalog →</a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ══════════ Seating generator — full-size, readable ══════════ --}}
    @if ($showSeatModal)
        @php
            $fam = \App\Models\EventRoom::SEATING_ARRANGEMENTS[$seatArr][2] ?? 'grid';
            $hasDims = is_numeric($width_m) && (float) $width_m > 0 && is_numeric($length_m) && (float) $length_m > 0;
            $isU = $seatArr === 'ushape';
        @endphp
        <x-modal title="Generate seating"
                 subtitle="Pick an arrangement, set the numbers, and drop it on the plan."
                 max="2xl" close="$set('showSeatModal', false)">
            <div class="space-y-5">

                {{-- STEP 1 · room size (generate needs it) --}}
                <div class="rounded-xl border {{ $hasDims ? 'border-eo-line bg-eo-workspace/40' : 'border-amber-300 bg-eo-warn-soft' }} p-4">
                    <div class="flex items-center justify-between">
                        <p class="eo-field-label !mb-0 flex items-center gap-1.5"><span class="flex h-4 w-4 items-center justify-center rounded-full bg-eo-navy text-eyebrow font-black text-white">1</span> Room size</p>
                        @unless ($hasDims)<span class="text-eyebrow font-bold uppercase tracking-wide text-amber-700">Required first</span>@endunless
                    </div>
                    <div class="mt-2.5 flex items-end gap-3">
                        <div class="flex-1">
                            <label class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-eo-muted">Width · m</label>
                            <input type="number" min="0" step="0.5" wire:model.live.debounce.400ms="width_m" class="eo-input h-11 w-full text-sm" placeholder="e.g. 12">
                        </div>
                        <span class="pb-3 text-eo-muted">×</span>
                        <div class="flex-1">
                            <label class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-eo-muted">Length · m</label>
                            <input type="number" min="0" step="0.5" wire:model.live.debounce.400ms="length_m" class="eo-input h-11 w-full text-sm" placeholder="e.g. 16">
                        </div>
                    </div>
                    @unless ($hasDims)<p class="mt-2 text-micro text-amber-700">Set the room’s real dimensions so seating is generated to scale.</p>@endunless
                </div>

                {{-- STEP 2 · arrangement --}}
                <div>
                    <p class="eo-field-label flex items-center gap-1.5"><span class="flex h-4 w-4 items-center justify-center rounded-full bg-eo-navy text-eyebrow font-black text-white">2</span> Arrangement</p>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        @foreach (\App\Models\EventRoom::SEATING_ARRANGEMENTS as $k => [$lbl, $blurb, $f])
                            <button type="button" wire:click="$set('seatArr', '{{ $k }}')"
                                    @class([
                                        'rounded-xl border p-2.5 text-left transition',
                                        'border-eo-teal bg-eo-teal-soft/60 shadow-sm' => $seatArr === $k,
                                        'border-eo-line bg-white hover:border-eo-teal/40' => $seatArr !== $k,
                                    ])>
                                <span class="block text-xs font-bold text-eo-text">{{ $lbl }}</span>
                                <span class="mt-0.5 block text-eyebrow leading-tight text-eo-muted">{{ \Illuminate\Support\Str::limit($blurb, 46) }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- STEP 3 · numbers --}}
                <div>
                    <p class="eo-field-label flex items-center gap-1.5"><span class="flex h-4 w-4 items-center justify-center rounded-full bg-eo-navy text-eyebrow font-black text-white">3</span> {{ $isU ? 'Tables & chairs' : 'How many & how comfortable' }}</p>

                    @if ($isU)
                        {{-- Table Designer — every table the same size, chairs on the outer edge --}}
                        <div class="grid gap-3 sm:grid-cols-3">
                            <x-field label="Head tables" hint="Across the top (2–3).">
                                <input type="number" wire:model.live.debounce.350ms="uHeadTables" min="1" max="12" class="eo-input h-11 text-sm">
                            </x-field>
                            <x-field label="Tables per arm" hint="Down each side.">
                                <input type="number" wire:model.live.debounce.350ms="uArmTables" min="0" max="24" class="eo-input h-11 text-sm">
                            </x-field>
                            <x-field label="Chairs per table">
                                <input type="number" wire:model.live.debounce.350ms="uPerTable" min="1" max="8" class="eo-input h-11 text-sm">
                            </x-field>
                            <x-field label="Table size · cm (length × depth)" class="sm:col-span-2">
                                <div class="flex items-center gap-2">
                                    <input type="number" wire:model.live.debounce.350ms="tableW_cm" min="60" max="300" step="10" class="eo-input h-11 text-sm" title="Length">
                                    <span class="text-eo-muted">×</span>
                                    <input type="number" wire:model.live.debounce.350ms="tableH_cm" min="40" max="120" step="10" class="eo-input h-11 text-sm" title="Depth">
                                </div>
                            </x-field>
                            <x-field label="Chair · cm">
                                <input type="number" step="5" min="30" max="120" value="{{ (int) round((float) $seatSize * 100) }}"
                                       wire:change="$set('seatSize', $event.target.value / 100)" class="eo-input h-11 text-sm">
                            </x-field>
                        </div>
                        {{-- live total --}}
                        @php
                            $uTot = ((int) $uHeadTables + 2 * (int) $uArmTables) * (int) $uPerTable;
                            $uTables = (int) $uHeadTables + 2 * (int) $uArmTables;
                        @endphp
                        <p class="mt-2 rounded-lg bg-eo-bg/60 px-3 py-2 text-micro text-eo-text">
                            <span class="font-black text-eo-text">{{ $uTables }} tables</span> · <span class="font-black text-eo-text">{{ $uTot }} chairs</span> ·
                            each {{ $tableW_cm }}×{{ $tableH_cm }}cm · chairs on the outer edge, open to the stage.
                        </p>
                    @else
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-field label="Seats (target)">
                                <input type="number" wire:model="seatTarget" min="1" placeholder="e.g. 200" @disabled($seatFill) class="eo-input h-11 text-sm disabled:opacity-40">
                            </x-field>
                            <x-field label="Chair size · cm" hint="Standard seat is 60cm.">
                                <input type="number" step="5" min="30" max="120" value="{{ (int) round((float) $seatSize * 100) }}"
                                       wire:change="$set('seatSize', $event.target.value / 100)" class="eo-input h-11 text-sm">
                            </x-field>
                            @if (in_array($fam, ['grid', 'chevron', 'ring'], true))
                                <x-field label="Space between chairs · cm">
                                    <input type="number" step="1" min="0" max="60" wire:model="seatGapCm" class="eo-input h-11 text-sm">
                                </x-field>
                                <x-field label="Legroom (front-to-back)">
                                    <select wire:model="seatComfort" class="eo-select h-11 text-sm">
                                        @foreach (\App\Models\EventRoom::SEATING_COMFORT as $k => [$lbl])<option value="{{ $k }}">{{ $lbl }}</option>@endforeach
                                    </select>
                                </x-field>
                            @endif
                            @if ($fam === 'grid')
                                <x-field label="Aisles">
                                    <select wire:model="seatAisles" class="eo-select h-11 text-sm">
                                        <option value="0">None</option><option value="1">Center</option><option value="2">Two</option>
                                    </select>
                                </x-field>
                            @endif
                            @if ($seatArr === 'banquet')
                                <x-field label="Seats per table">
                                    <input type="number" wire:model="tableSeats" min="4" max="12" class="eo-input h-11 text-sm">
                                </x-field>
                                <x-field label="Table diameter · cm" hint="Standard banquet round is 180cm.">
                                    <input type="number" wire:model="roundDia_cm" min="90" max="300" step="10" class="eo-input h-11 text-sm">
                                </x-field>
                            @endif
                        </div>
                        @if (in_array($fam, ['grid'], true))
                            <label class="mt-3 flex items-center gap-2 text-xs font-semibold text-eo-text"><input type="checkbox" wire:model="seatLabels" class="h-4 w-4 rounded border-eo-line text-eo-teal focus:ring-eo-teal/40"> Row labels (A, B, C…)</label>
                        @endif
                        @if ($fam !== 'perimeter')
                            <label class="mt-2 flex items-center gap-2 text-xs font-semibold text-eo-text"><input type="checkbox" wire:model.live="seatFill" class="h-4 w-4 rounded border-eo-line text-eo-teal focus:ring-eo-teal/40"> Fill the room to maximum capacity</label>
                        @endif
                    @endif

                    @error('seatTarget')<p class="mt-2 rounded-lg bg-eo-risk-soft px-2.5 py-1.5 text-xs font-semibold text-eo-risk-ink">{{ $message }}</p>@enderror
                </div>
            </div>

            <x-slot:footer>
                <button type="button" wire:click="$set('showSeatModal', false)" class="eo-btn-ghost eo-btn-sm">Cancel</button>
                @if ($isU)
                    <button type="button" wire:click="designUShape" class="eo-btn-primary eo-btn-sm">✦ Design U-shape</button>
                @else
                    <button type="button" wire:click="generateSeating" class="eo-btn-primary eo-btn-sm">✦ Generate</button>
                @endif
            </x-slot:footer>
        </x-modal>
    @endif
</div>
