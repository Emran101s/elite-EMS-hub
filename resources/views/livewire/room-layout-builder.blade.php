@php
    $statusMeta = [
        'needed' => ['Needed', 'bg-navy-100 text-navy-600 border-navy-200'],
        'requested' => ['Requested', 'bg-amber-100 text-amber-700 border-amber-200'],
        'confirmed' => ['Confirmed', 'bg-emerald-100 text-emerald-700 border-emerald-200'],
        'onsite' => ['On-site', 'bg-sky-100 text-sky-700 border-sky-200'],
    ];
@endphp
<div>
    {{-- Header --}}
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('events.hub', [$event, 'tab' => 'venue']) }}" class="text-xs font-semibold text-gold-600 hover:text-gold-700">← {{ $event->name }} · Venue</a>
            <h2 class="mt-0.5 pf text-lg font-bold text-navy-900">{{ $room->name }}</h2>
            <p class="text-xs text-muted">{{ str($room->type)->replace('_', ' ')->title() }}
                @if ($room->capacity) · room capacity {{ number_format($room->capacity) }} @endif
                · <span class="font-semibold text-navy-900">{{ $seatTotal }}</span> seats
                @if (count($room->requirements ?? [])) · <span class="font-semibold text-navy-900">{{ count($room->requirements) }}</span> equipment @endif
                @if ($room->totalCents()) · <span class="font-semibold text-navy-900">{{ $event->money($room->totalCents()) }}</span> total @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            {{-- exports — available on both the Layout and Equipment views --}}
            <a href="{{ route('events.room-layout.pdf', [$event, $room]) }}" target="_blank" class="btn-ghost btn-sm" title="Floor plan to scale + inspector">↧ Layout PDF</a>
            <a href="{{ route('events.room-equipment.pdf', [$event, $room]) }}" target="_blank" class="btn-ghost btn-sm" title="AV &amp; equipment prep sheet">↧ Equipment PDF</a>

            {{-- Workspace tabs --}}
            <div class="inline-flex rounded-xl border border-line bg-white p-1">
                <button type="button" wire:click="$set('view', 'floor')"
                        class="rounded-lg px-4 py-1.5 text-xs font-bold transition {{ $view === 'floor' ? 'bg-navy-900 text-white' : 'text-navy-600 hover:text-navy-900' }}">⊞ Layout</button>
                <button type="button" wire:click="$set('view', 'equipment')"
                        class="rounded-lg px-4 py-1.5 text-xs font-bold transition {{ $view !== 'floor' ? 'bg-navy-900 text-white' : 'text-navy-600 hover:text-navy-900' }}">
                    🎛 Equipment @if (count($room->requirements ?? []))<span class="ml-1 rounded-full bg-gold-500/20 px-1.5 text-eyebrow text-gold-700">{{ count($room->requirements) }}</span>@endif
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
            <div class="card h-fit p-3.5">
                {{-- ✦ Seating generator — opens a full-size modal so the controls are readable --}}
                <button type="button" wire:click="openSeatModal"
                        class="mb-1 flex w-full items-center justify-center gap-1.5 rounded-xl bg-gradient-to-r from-gold-400 to-gold-600 px-3 py-2.5 text-xs font-bold text-navy-950 shadow-[0_6px_18px_-6px_rgba(212,175,55,0.75)] transition hover:brightness-105">
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
                    <p class="field-label {{ $loop->first ? 'mt-3.5 !mb-2.5 border-t border-line pt-3' : 'mt-3.5 !mb-2.5' }}">{{ $gLabel }}</p>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($types as $type)
                            @php [$label, $seats, $w, $h] = $presets[$type]; @endphp
                            <button type="button" wire:click="addElement('{{ $type }}')"
                                    class="group flex flex-col items-center gap-1 rounded-xl border border-line bg-page/40 px-1.5 py-2 transition hover:border-gold-400 hover:bg-gold-50/50 hover:shadow-sm">
                                <span class="flex h-11 w-full items-center justify-center overflow-hidden">
                                    <span class="block" style="transform: scale({{ round(min(50 / $w, 38 / $h), 3) }}); transform-origin: center;">
                                        <x-layout-element :type="$type" :seats="$type === 'chair' ? 0 : ($type === 'round' ? 8 : 0)" :w="$w" :h="$h" />
                                    </span>
                                </span>
                                <span class="text-eyebrow font-bold text-navy-900">{{ $type === 'table' ? 'Table' : $label }}</span>
                            </button>
                        @endforeach
                    </div>
                @endforeach
                <p class="mt-3 border-t border-line pt-2.5 text-eyebrow leading-snug text-muted">Click to drop → drag to place → click to select &amp; resize.</p>
            </div>

            {{-- CENTER · canvas --}}
            <div class="card overflow-auto p-4"
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
                    <span class="text-eyebrow font-bold uppercase tracking-wide text-muted">{{ count($elements) }} {{ \Illuminate\Support\Str::plural('item', count($elements)) }} on the plan</span>
                    <button type="button" wire:click="selectAll" @disabled(empty($elements))
                            class="rounded-lg border border-line bg-white px-2.5 py-1.5 text-eyebrow font-bold text-navy-600 transition enabled:hover:border-navy-300 disabled:opacity-40">Select all</button>
                    @if (count($selectedIds ?? []))
                        <button type="button" wire:click="deleteSelected"
                                class="rounded-lg bg-risk px-2.5 py-1.5 text-eyebrow font-bold text-white transition hover:brightness-110">Delete selected ({{ count($selectedIds) }})</button>
                        <button type="button" wire:click="clearSelection"
                                class="rounded-lg px-2 py-1.5 text-eyebrow font-bold text-navy-400 hover:text-navy-700">Cancel</button>
                    @endif
                    <button type="button" wire:click="clearAll" wire:confirm="Delete everything on the plan?" @disabled(empty($elements))
                            class="ml-auto rounded-lg border border-line bg-white px-2.5 py-1.5 text-eyebrow font-bold text-risk transition enabled:hover:bg-risk/5 disabled:opacity-40">Clear all</button>
                </div>

                <div class="mx-auto shrink-0" style="width:960px;">
                    <div x-ref="canvas" @pointerdown.self="$wire.selectElement('')" class="relative rounded-xl border border-line"
                         style="width:960px; height:560px; background: var(--hull);
                            @if(!$scale) background-image:
                                linear-gradient(var(--color-line) 1px, transparent 1px),
                                linear-gradient(90deg, var(--color-line) 1px, transparent 1px);
                            background-size: 40px 40px, 40px 40px; @endif">

                        {{-- centred venue floor (to scale) --}}
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
                                        class="absolute -left-2.5 -top-2.5 z-40 flex h-5 w-5 items-center justify-center rounded-md border-2 border-white text-eyebrow font-black shadow transition {{ $bulk ? 'bg-info text-white' : 'bg-white text-navy-300 opacity-0 group-hover:opacity-100' }}"
                                        title="Select for bulk delete">{{ $bulk ? '✓' : '＋' }}</button>
                                @if (($el['type'] ?? '') === 'seatblock')
                                    @php $geo = \App\Models\EventRoom::seatChairs($el, $scale ?: 12); @endphp
                                    <div data-rot style="transform: rotate({{ $el['rot'] ?? 0 }}deg); width:{{ $geo['w'] }}px; height:{{ $geo['h'] }}px;" class="relative {{ $sel ? 'rounded-lg ring-2 ring-gold-500 ring-offset-4' : '' }}">
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
                                    <span class="pointer-events-none absolute -bottom-2.5 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-navy-900 px-1.5 py-px text-eyebrow font-bold text-white">{{ $el['seats'] ?? 0 }} seats</span>
                                @else
                                    <div data-rot style="transform: rotate({{ $el['rot'] ?? 0 }}deg);" class="{{ $sel ? 'rounded-lg ring-2 ring-gold-500 ring-offset-2' : '' }}">
                                        <x-layout-element :type="$el['type']" :seats="$el['seats'] ?? 0" :w="$el['w'] ?? 96" :h="$el['h'] ?? 96" :scale="$scale" />
                                    </div>
                                    @if (($el['seats'] ?? 0) > 0)
                                        <span class="pointer-events-none absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 text-eyebrow font-bold text-navy-900">{{ $el['seats'] }}</span>
                                    @endif
                                @endif

                                {{-- item name, upright, above the piece --}}
                                @if (! empty($el['name']))
                                    <span class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-0.5 -translate-x-1/2 whitespace-nowrap rounded bg-white/90 px-1.5 py-px text-eyebrow font-bold text-navy-700 shadow-sm ring-1 ring-line">{{ $el['name'] }}</span>
                                @endif

                                @if ($sel)
                                    {{-- drag-to-rotate handle --}}
                                    <div class="absolute bottom-full left-1/2 mb-1 flex -translate-x-1/2 flex-col items-center">
                                        <div @pointerdown.stop.prevent="startSpin($event, '{{ $el['id'] }}')"
                                             class="flex h-6 w-6 cursor-grab items-center justify-center rounded-full border-2 border-white bg-gold-500 text-micro text-white shadow-md active:cursor-grabbing"
                                             title="Drag to rotate · hold Shift for free angle">⟳</div>
                                        <div class="h-3 w-px bg-gold-400"></div>
                                    </div>
                                    {{-- delete --}}
                                    <button type="button" wire:click="removeElement('{{ $el['id'] }}')" @pointerdown.stop
                                            class="absolute -right-2.5 -top-2.5 flex h-5 w-5 items-center justify-center rounded-full bg-risk text-eyebrow font-bold text-white shadow-md transition hover:scale-110"
                                            title="Delete (or press Delete key)">✕</button>
                                @endif
                            </div>
                        @empty
                            <div class="pointer-events-none flex h-full items-center justify-center">
                                <p class="text-sm text-navy-300">Pick a shape on the left to start building →</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- RIGHT · inspector rail --}}
            <div class="xl:sticky xl:top-4 xl:h-fit">
                <div class="card overflow-hidden">
                    {{-- rail header --}}
                    <div class="flex items-center justify-between border-b border-line bg-navy-900 px-4 py-3">
                        <span class="text-xs font-bold uppercase tracking-[0.14em] text-gold-300">Inspector</span>
                        <button type="button" wire:click="clearAll" wire:confirm="Clear the whole layout?" @disabled(empty($elements)) class="rounded-lg bg-white/10 px-2.5 py-1 text-eyebrow font-bold text-white transition hover:bg-risk/70 disabled:opacity-30">Clear</button>
                    </div>

                    {{-- Room dimensions --}}
                    <div class="border-b border-line p-4">
                        <p class="field-label !mb-2.5 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> Room dimensions</p>
                        <div class="flex items-end gap-2">
                            <div class="flex-1">
                                <label class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Width · m</label>
                                <input type="number" min="0" step="0.5" wire:model.live.debounce.400ms="width_m" class="input h-9 w-full text-sm" placeholder="—">
                            </div>
                            <span class="pb-2 text-muted">×</span>
                            <div class="flex-1">
                                <label class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Length · m</label>
                                <input type="number" min="0" step="0.5" wire:model.live.debounce.400ms="length_m" class="input h-9 w-full text-sm" placeholder="—">
                            </div>
                        </div>
                        @if ($area)
                            <div class="mt-3 flex items-center justify-between rounded-xl bg-page/60 px-3 py-2">
                                <span class="text-eyebrow font-semibold text-muted">Floor area</span>
                                <span class="text-sm font-bold text-navy-900">{{ number_format($area, 0) }} m²</span>
                            </div>
                        @else
                            <p class="mt-2 text-eyebrow text-muted">Set dimensions for a to-scale grid, rulers &amp; real table sizes.</p>
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
                                <p class="field-label !mb-0 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> {{ $label }}</p>
                                <button type="button" wire:click="removeElement('{{ $sid }}')" class="text-eyebrow font-bold text-risk hover:underline">Delete</button>
                            </div>

                            {{-- NAME --}}
                            <label class="mb-3 block">
                                <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Name / label</span>
                                <input type="text" maxlength="40" value="{{ $selected['name'] ?? '' }}"
                                       wire:change="nameElement('{{ $sid }}', $event.target.value)"
                                       placeholder="e.g. Head table, Booth A, VIP round"
                                       class="input h-9 w-full text-sm">
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
                                <p class="field-label !mb-1.5 !text-eyebrow">Arrangement · edit</p>
                                <div class="mb-2 grid grid-cols-2 gap-2">
                                    @foreach ($counts as [$lbl, $fld, $v])
                                        <label class="block">
                                            <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">{{ $lbl }}</span>
                                            <input type="number" min="0" value="{{ $v }}"
                                                   wire:change="updateSeatblock('{{ $sid }}', '{{ $fld }}', $event.target.value)"
                                                   class="input h-9 w-full text-sm">
                                        </label>
                                    @endforeach
                                    @if ($cmField)
                                        <label class="col-span-2 block">
                                            <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">{{ $cmField[0] }}</span>
                                            <div class="flex items-center gap-1">
                                                <input type="number" step="10" value="{{ (int) round(($sb[$cmField[1]] ?? 1.8) * 100) }}"
                                                       wire:change="updateSeatblock('{{ $sid }}', '{{ $cmField[1] }}', $event.target.value)"
                                                       class="input h-9 w-full text-sm">
                                                @if ($cmField[2])
                                                    <span class="text-muted">×</span>
                                                    <input type="number" step="10" value="{{ (int) round(($sb[$cmField[2]] ?? 0.6) * 100) }}"
                                                           wire:change="updateSeatblock('{{ $sid }}', '{{ $cmField[2] }}', $event.target.value)"
                                                           class="input h-9 w-full text-sm">
                                                @endif
                                            </div>
                                        </label>
                                    @endif
                                </div>
                                <div class="mb-3 flex items-center justify-between rounded-xl bg-page/60 px-3 py-2">
                                    <span class="text-eyebrow font-bold uppercase tracking-wide text-muted">Total chairs</span>
                                    <span class="text-base font-black text-navy-900">{{ $sb['seats'] ?? 0 }}</span>
                                </div>
                            @endif

                            {{-- SIZE (non-seatblock only) --}}
                            @unless ($selected['type'] === 'seatblock')
                            <p class="field-label !mb-1.5 !text-eyebrow">Size {{ $scale ? '· metres' : '· pixels' }}</p>
                            @if ($scale)
                                @if ($isRound)
                                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Diameter (m)</label>
                                    <input type="number" min="0.2" step="0.1" value="{{ $wM }}" wire:change="setSizeMeters('{{ $sid }}', 'both', $event.target.value)" class="input mb-3 h-9 w-full text-sm">
                                @else
                                    <div class="mb-3 grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Width (m)</label>
                                            <input type="number" min="0.2" step="0.1" value="{{ $wM }}" wire:change="setSizeMeters('{{ $sid }}', 'w', $event.target.value)" class="input h-9 w-full text-sm">
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Length (m)</label>
                                            <input type="number" min="0.2" step="0.1" value="{{ $hM }}" wire:change="setSizeMeters('{{ $sid }}', 'h', $event.target.value)" class="input h-9 w-full text-sm">
                                        </div>
                                    </div>
                                @endif
                            @else
                                {{-- no room size yet → px steppers + hint --}}
                                @if ($isRound)
                                    <div class="mb-2 flex items-center gap-1">
                                        <button type="button" wire:click="resizeElement('{{ $sid }}', 'both', -10)" class="seg-step">−</button>
                                        <span class="flex-1 text-center text-xs font-bold text-navy-900">{{ $selected['w'] ?? 96 }} px</span>
                                        <button type="button" wire:click="resizeElement('{{ $sid }}', 'both', 10)" class="seg-step">+</button>
                                    </div>
                                @else
                                    <div class="mb-2 grid grid-cols-2 gap-2">
                                        <div class="flex items-center gap-1"><button type="button" wire:click="resizeElement('{{ $sid }}', 'w', -10)" class="seg-step">−</button><span class="flex-1 text-center text-micro font-bold text-navy-900">W</span><button type="button" wire:click="resizeElement('{{ $sid }}', 'w', 10)" class="seg-step">+</button></div>
                                        <div class="flex items-center gap-1"><button type="button" wire:click="resizeElement('{{ $sid }}', 'h', -10)" class="seg-step">−</button><span class="flex-1 text-center text-micro font-bold text-navy-900">L</span><button type="button" wire:click="resizeElement('{{ $sid }}', 'h', 10)" class="seg-step">+</button></div>
                                    </div>
                                @endif
                                <p class="mb-3 text-eyebrow text-gold-700">Set room dimensions above to size in metres.</p>
                            @endif
                            @endunless

                            {{-- ROTATION --}}
                            <div class="mb-3">
                                <label class="mb-1 flex items-center justify-between text-eyebrow font-bold uppercase tracking-wide text-muted"><span>Rotation</span><span class="text-navy-700">{{ $rot }}°</span></label>
                                <div class="flex items-center gap-1.5">
                                    <button type="button" wire:click="rotateBy('{{ $sid }}', -15)" class="seg-step" title="−15°">⟲</button>
                                    <input type="range" min="0" max="359" step="1" value="{{ $rot }}" wire:change="setRotation('{{ $sid }}', $event.target.value)" class="h-1.5 flex-1 accent-gold-500">
                                    <button type="button" wire:click="rotateBy('{{ $sid }}', 15)" class="seg-step" title="+15°">⟳</button>
                                </div>
                                <div class="mt-1.5 flex gap-1">
                                    @foreach ([0, 45, 90, 135, 180, 270] as $deg)
                                        <button type="button" wire:click="setRotation('{{ $sid }}', {{ $deg }})" class="flex-1 rounded-md border border-line py-0.5 text-eyebrow font-bold {{ $rot === $deg ? 'bg-navy-900 text-white' : 'text-navy-500 hover:border-gold-300' }}">{{ $deg }}°</button>
                                    @endforeach
                                </div>
                                <p class="mt-1 text-eyebrow text-muted">Tip: drag the gold handle on the canvas to rotate freely (hold Shift for any angle).</p>
                            </div>

                            {{-- SEATS --}}
                            @if (($selected['seats'] ?? 0) > 0 || in_array($selected['type'], ['round', 'banquet', 'boardroom', 'crescent', 'ushape', 'classroom', 'theater'], true))
                                <div class="flex items-center justify-between rounded-xl bg-page/60 px-3 py-2">
                                    <span class="text-eyebrow font-bold uppercase tracking-wide text-muted">Seats</span>
                                    <div class="flex items-center gap-1">
                                        <button type="button" wire:click="changeSeats('{{ $sid }}', -1)" class="seg-step !h-6 !w-6">−</button>
                                        <span class="min-w-7 text-center text-xs font-bold text-navy-900">{{ $selected['seats'] ?? 0 }}</span>
                                        <button type="button" wire:click="changeSeats('{{ $sid }}', 1)" class="seg-step !h-6 !w-6">+</button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="p-4">
                            <p class="field-label !mb-2.5 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-navy-300"></span> Layout stats</p>
                            <div class="grid grid-cols-2 gap-2">
                                <div class="rounded-xl bg-page/60 px-3 py-2.5"><p class="text-lg font-bold text-navy-900">{{ $seatTotal }}</p><p class="text-eyebrow uppercase tracking-wide text-muted">Seats</p></div>
                                <div class="rounded-xl bg-page/60 px-3 py-2.5"><p class="text-lg font-bold text-navy-900">{{ $tablesCount }}</p><p class="text-eyebrow uppercase tracking-wide text-muted">Tables</p></div>
                                <div class="rounded-xl bg-page/60 px-3 py-2.5"><p class="text-lg font-bold text-navy-900">{{ count($elements) }}</p><p class="text-eyebrow uppercase tracking-wide text-muted">Elements</p></div>
                                <div class="rounded-xl bg-page/60 px-3 py-2.5">
                                    <p class="text-lg font-bold text-navy-900">{{ $area && $seatTotal ? number_format($seatTotal / $area, 1) : '—' }}</p>
                                    <p class="text-eyebrow uppercase tracking-wide text-muted">Seats / m²</p>
                                </div>
                            </div>
                            @if ($room->capacity && $seatTotal)
                                <div class="mt-3">
                                    <div class="mb-1 flex justify-between text-eyebrow font-semibold text-muted"><span>Capacity fill</span><span>{{ min(100, (int) round($seatTotal / $room->capacity * 100)) }}%</span></div>
                                    <div class="h-1.5 overflow-hidden rounded-full bg-navy-100"><div class="h-full rounded-full bg-gold-500" style="width: {{ min(100, (int) round($seatTotal / $room->capacity * 100)) }}%"></div></div>
                                </div>
                            @endif
                            <p class="mt-3 text-eyebrow leading-snug text-muted">Select any element on the canvas to resize it, change seats, or rotate.</p>
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
            <div class="card p-6">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h3 class="pf text-base font-bold text-navy-900">Venue Equipment</h3>
                        <p class="text-eyebrow text-muted">Everything this venue needs, each with a price. The total flows into the Budget under Venues.</p>
                    </div>
                    <span class="text-lg font-bold text-navy-900">{{ $reqTotal ? $event->money($reqTotal) : '—' }}</span>
                </div>

                @if ($catalog->isNotEmpty())
                    <select wire:change="pickReq($event.target.value)" class="input mb-2 h-10 w-full text-sm">
                        <option value="">— Pick from Equipment Catalog —</option>
                        @foreach ($catalog as $ci)<option value="{{ $ci->id }}">{{ $ci->name }}@if ($ci->unit_price_cents) · {{ number_format($ci->unit_price_cents / 100) }}@endif</option>@endforeach
                    </select>
                @endif
                <div class="mb-4 flex items-center gap-1.5">
                    <input type="text" wire:model="reqName" wire:keydown.enter="addRequirement" maxlength="120" placeholder="Equipment (or pick from catalog)" class="input h-10 min-w-0 flex-1 text-sm">
                    <span class="text-eyebrow font-semibold text-muted">{{ $event->currencySymbol() }}</span>
                    <input type="number" min="0" step="0.01" wire:model="reqCost" wire:keydown.enter="addRequirement" placeholder="Price" class="input h-10 w-28 text-sm">
                    <button type="button" wire:click="addRequirement" class="btn-gold h-10 shrink-0 px-4 text-xs">＋ Add</button>
                </div>
                @error('reqName') <p class="mb-2 text-eyebrow font-semibold text-risk">{{ $message }}</p> @enderror

                <ul class="divide-y divide-line">
                    @forelse ($reqs as $req)
                        <li wire:key="dreq-{{ $req['id'] }}" class="group flex items-center justify-between gap-2 py-2.5">
                            <span class="min-w-0 flex-1 truncate text-sm text-navy-800">{{ $req['name'] }}</span>
                            <span class="flex shrink-0 items-center gap-3">
                                <span class="text-sm font-semibold text-navy-900">{{ $event->money($req['cost_cents'] ?? 0) }}</span>
                                <button type="button" wire:click="removeRequirement('{{ $req['id'] }}')" class="rounded-lg bg-risk/10 px-1.5 py-0.5 text-eyebrow font-bold text-red-700 opacity-0 transition hover:bg-risk/20 group-hover:opacity-100">✕</button>
                            </span>
                        </li>
                    @empty
                        <li class="py-6 text-center text-xs text-muted">No equipment yet — add anything this venue needs with its price.</li>
                    @endforelse
                </ul>
            </div>

            {{-- RIGHT · totals rail --}}
            <div class="xl:sticky xl:top-[76px] xl:h-fit">
                <div class="card overflow-hidden">
                    <div class="border-b border-line bg-navy-900 px-4 py-3">
                        <span class="text-xs font-bold uppercase tracking-[0.14em] text-gold-300">Cost Summary</span>
                    </div>
                    <div class="space-y-2 p-4 text-sm">
                        <div class="flex justify-between"><span class="text-muted">Hire cost</span><span class="font-bold text-navy-900">{{ $room->cost_cents ? $event->money($room->cost_cents) : '—' }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Equipment</span><span class="font-bold text-navy-900">{{ $reqTotal ? $event->money($reqTotal) : '—' }}</span></div>
                        <div class="flex items-center justify-between border-t border-line pt-2"><span class="text-micro font-bold uppercase tracking-wide text-navy-900">Venue total</span><span class="text-base font-bold text-navy-900">{{ $event->money($venueTotal) }}</span></div>
                    </div>
                    <div class="border-t border-line p-4">
                        <a href="{{ route('events.hub', [$event, 'tab' => 'budget']) }}" class="flex h-9 w-full items-center justify-center gap-1.5 rounded-xl border border-gold-300 bg-gold-50/60 text-xs font-bold text-gold-700 transition hover:bg-gold-100">Syncs to Budget · Venues →</a>
                        <a href="{{ route('requirements.index') }}" class="mt-2 block text-center text-eyebrow font-semibold text-gold-600 hover:text-gold-700">Manage the Equipment Catalog →</a>
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
                <div class="rounded-xl border {{ $hasDims ? 'border-line bg-page/40' : 'border-gold-300 bg-gold-50/50' }} p-4">
                    <div class="flex items-center justify-between">
                        <p class="field-label !mb-0 flex items-center gap-1.5"><span class="flex h-4 w-4 items-center justify-center rounded-full bg-navy-900 text-eyebrow font-black text-white">1</span> Room size</p>
                        @unless ($hasDims)<span class="text-eyebrow font-bold uppercase tracking-wide text-gold-700">Required first</span>@endunless
                    </div>
                    <div class="mt-2.5 flex items-end gap-3">
                        <div class="flex-1">
                            <label class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Width · m</label>
                            <input type="number" min="0" step="0.5" wire:model.live.debounce.400ms="width_m" class="input h-11 w-full text-sm" placeholder="e.g. 12">
                        </div>
                        <span class="pb-3 text-muted">×</span>
                        <div class="flex-1">
                            <label class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Length · m</label>
                            <input type="number" min="0" step="0.5" wire:model.live.debounce.400ms="length_m" class="input h-11 w-full text-sm" placeholder="e.g. 16">
                        </div>
                    </div>
                    @unless ($hasDims)<p class="mt-2 text-micro text-gold-700">Set the room’s real dimensions so seating is generated to scale.</p>@endunless
                </div>

                {{-- STEP 2 · arrangement --}}
                <div>
                    <p class="field-label flex items-center gap-1.5"><span class="flex h-4 w-4 items-center justify-center rounded-full bg-navy-900 text-eyebrow font-black text-white">2</span> Arrangement</p>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        @foreach (\App\Models\EventRoom::SEATING_ARRANGEMENTS as $k => [$lbl, $blurb, $f])
                            <button type="button" wire:click="$set('seatArr', '{{ $k }}')"
                                    @class([
                                        'rounded-xl border p-2.5 text-left transition',
                                        'border-gold-400 bg-gold-50/60 shadow-sm' => $seatArr === $k,
                                        'border-line bg-white hover:border-navy-200' => $seatArr !== $k,
                                    ])>
                                <span class="block text-xs font-bold text-navy-900">{{ $lbl }}</span>
                                <span class="mt-0.5 block text-eyebrow leading-tight text-muted">{{ \Illuminate\Support\Str::limit($blurb, 46) }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- STEP 3 · numbers --}}
                <div>
                    <p class="field-label flex items-center gap-1.5"><span class="flex h-4 w-4 items-center justify-center rounded-full bg-navy-900 text-eyebrow font-black text-white">3</span> {{ $isU ? 'Tables & chairs' : 'How many & how comfortable' }}</p>

                    @if ($isU)
                        {{-- Table Designer — every table the same size, chairs on the outer edge --}}
                        <div class="grid gap-3 sm:grid-cols-3">
                            <x-field label="Head tables" hint="Across the top (2–3).">
                                <input type="number" wire:model.live.debounce.350ms="uHeadTables" min="1" max="12" class="input h-11 text-sm">
                            </x-field>
                            <x-field label="Tables per arm" hint="Down each side.">
                                <input type="number" wire:model.live.debounce.350ms="uArmTables" min="0" max="24" class="input h-11 text-sm">
                            </x-field>
                            <x-field label="Chairs per table">
                                <input type="number" wire:model.live.debounce.350ms="uPerTable" min="1" max="8" class="input h-11 text-sm">
                            </x-field>
                            <x-field label="Table size · cm (length × depth)" class="sm:col-span-2">
                                <div class="flex items-center gap-2">
                                    <input type="number" wire:model.live.debounce.350ms="tableW_cm" min="60" max="300" step="10" class="input h-11 text-sm" title="Length">
                                    <span class="text-muted">×</span>
                                    <input type="number" wire:model.live.debounce.350ms="tableH_cm" min="40" max="120" step="10" class="input h-11 text-sm" title="Depth">
                                </div>
                            </x-field>
                            <x-field label="Chair · cm">
                                <input type="number" step="5" min="30" max="120" value="{{ (int) round((float) $seatSize * 100) }}"
                                       wire:change="$set('seatSize', $event.target.value / 100)" class="input h-11 text-sm">
                            </x-field>
                        </div>
                        {{-- live total --}}
                        @php
                            $uTot = ((int) $uHeadTables + 2 * (int) $uArmTables) * (int) $uPerTable;
                            $uTables = (int) $uHeadTables + 2 * (int) $uArmTables;
                        @endphp
                        <p class="mt-2 rounded-lg bg-navy-50/60 px-3 py-2 text-micro text-navy-700">
                            <span class="font-black text-navy-900">{{ $uTables }} tables</span> · <span class="font-black text-navy-900">{{ $uTot }} chairs</span> ·
                            each {{ $tableW_cm }}×{{ $tableH_cm }}cm · chairs on the outer edge, open to the stage.
                        </p>
                    @else
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-field label="Seats (target)">
                                <input type="number" wire:model="seatTarget" min="1" placeholder="e.g. 200" @disabled($seatFill) class="input h-11 text-sm disabled:opacity-40">
                            </x-field>
                            <x-field label="Chair size · cm" hint="Standard seat is 60cm.">
                                <input type="number" step="5" min="30" max="120" value="{{ (int) round((float) $seatSize * 100) }}"
                                       wire:change="$set('seatSize', $event.target.value / 100)" class="input h-11 text-sm">
                            </x-field>
                            @if (in_array($fam, ['grid', 'chevron', 'ring'], true))
                                <x-field label="Space between chairs · cm">
                                    <input type="number" step="1" min="0" max="60" wire:model="seatGapCm" class="input h-11 text-sm">
                                </x-field>
                                <x-field label="Legroom (front-to-back)">
                                    <select wire:model="seatComfort" class="input h-11 text-sm">
                                        @foreach (\App\Models\EventRoom::SEATING_COMFORT as $k => [$lbl])<option value="{{ $k }}">{{ $lbl }}</option>@endforeach
                                    </select>
                                </x-field>
                            @endif
                            @if ($fam === 'grid')
                                <x-field label="Aisles">
                                    <select wire:model="seatAisles" class="input h-11 text-sm">
                                        <option value="0">None</option><option value="1">Center</option><option value="2">Two</option>
                                    </select>
                                </x-field>
                            @endif
                            @if ($seatArr === 'banquet')
                                <x-field label="Seats per table">
                                    <input type="number" wire:model="tableSeats" min="4" max="12" class="input h-11 text-sm">
                                </x-field>
                                <x-field label="Table diameter · cm" hint="Standard banquet round is 180cm.">
                                    <input type="number" wire:model="roundDia_cm" min="90" max="300" step="10" class="input h-11 text-sm">
                                </x-field>
                            @endif
                        </div>
                        @if (in_array($fam, ['grid'], true))
                            <label class="mt-3 flex items-center gap-2 text-xs font-semibold text-navy-700"><input type="checkbox" wire:model="seatLabels" class="h-4 w-4 rounded border-line text-gold-700 focus:ring-gold-400"> Row labels (A, B, C…)</label>
                        @endif
                        @if ($fam !== 'perimeter')
                            <label class="mt-2 flex items-center gap-2 text-xs font-semibold text-navy-700"><input type="checkbox" wire:model.live="seatFill" class="h-4 w-4 rounded border-line text-gold-700 focus:ring-gold-400"> Fill the room to maximum capacity</label>
                        @endif
                    @endif

                    @error('seatTarget')<p class="mt-2 rounded-lg bg-risk/5 px-2.5 py-1.5 text-xs font-semibold text-risk">{{ $message }}</p>@enderror
                </div>
            </div>

            <x-slot:footer>
                <button type="button" wire:click="$set('showSeatModal', false)" class="btn-ghost btn-sm">Cancel</button>
                @if ($isU)
                    <button type="button" wire:click="designUShape" class="btn-gold btn-sm">✦ Design U-shape</button>
                @else
                    <button type="button" wire:click="generateSeating" class="btn-gold btn-sm">✦ Generate</button>
                @endif
            </x-slot:footer>
        </x-modal>
    @endif
</div>
