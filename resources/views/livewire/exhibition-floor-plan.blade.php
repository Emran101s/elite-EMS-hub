@php
    $boothHex = [
        'standard' => ['#EEF3F9', '#1E3352'],
        'premium' => ['#FBF3DE', '#8A6D1F'],
        'island' => ['#1E3352', '#FFFFFF'],
        'custom' => ['#F1F5F9', '#475569'],
    ];
    $bh = fn ($pkg) => $boothHex[$pkg] ?? ['#EEF3F9', '#1E3352'];
    // Sales status → dot colour (cancelled booths are filtered out of the plan).
    $statusHex = ['reserved' => '#F59E0B', 'confirmed' => '#3B82F6', 'paid' => '#22C55E'];
    // Metre → canvas scale (centre the hall rectangle in the 960×560 canvas).
    $W = $hall?->width_m ?: 30;
    $L = $hall?->length_m ?: 20;
    $scale = min(960 / $W, 560 / $L);
    $venW = (int) round($W * $scale);
    $venH = (int) round($L * $scale);
    $offX = (int) round((960 - $venW) / 2);
    $offY = (int) round((560 - $venH) / 2);
    $gridM = $scale >= 26 ? 1 : ($scale >= 13 ? 2 : 5);
    $gridPx = $scale * $gridM;
    $fmtM = fn ($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.');
    $slug = \Illuminate\Support\Str::slug($event->name.'-'.($hall?->name ?? 'hall'));
@endphp

<div>
    {{-- top actions --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold text-navy-900">Exhibition Floor Plan</h1>
            <p class="text-xs text-muted">{{ $event->name }} · {{ $placed->count() }}/{{ $totalBooths }} booths placed · {{ $fmtM($W) }}×{{ $fmtM($L) }} m ({{ $fmtM($W * $L) }} m²)</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" onclick="floorPng()" class="flex h-10 items-center gap-1.5 rounded-xl border border-line bg-white px-3.5 text-xs font-bold text-navy-700 transition hover:border-gold-300">🖼 Download image</button>
            <a href="{{ route('events.exhibition-floor.pdf', $event) }}" class="flex h-10 items-center gap-1.5 rounded-xl bg-navy-900 px-3.5 text-xs font-bold text-white transition hover:bg-navy-800"><span class="text-gold-400">↧</span> Download PDF</a>
            <a href="{{ route('events.hub', [$event, 'tab' => 'exhibition']) }}" class="flex h-10 items-center rounded-xl border border-line bg-white px-3.5 text-xs font-semibold text-navy-700 transition hover:border-gold-300">← Exhibition</a>
        </div>
    </div>

    {{-- hall tabs --}}
    <div class="mb-4 flex flex-wrap items-center gap-1.5">
        @foreach ($halls as $h)
            <button type="button" wire:click="selectHall({{ $h->id }})" @class([
                'flex items-center gap-1.5 rounded-xl border px-3.5 py-2 text-xs font-bold transition',
                'border-navy-900 bg-navy-900 text-white' => $h->id === $hall?->id,
                'border-line bg-white text-navy-700 hover:border-gold-300' => $h->id !== $hall?->id,
            ])>
                🏛 {{ $h->name }}
                <span class="rounded-full px-1.5 text-[0.55rem] {{ $h->id === $hall?->id ? 'bg-white/15 text-white/80' : 'bg-navy-100 text-navy-500' }}">{{ $h->exhibitors->count() }}</span>
            </button>
        @endforeach
        <button type="button" wire:click="addHall" class="flex items-center gap-1 rounded-xl border border-dashed border-line bg-white px-3 py-2 text-xs font-bold text-navy-500 transition hover:border-gold-300 hover:text-gold-700">＋ Add hall</button>
    </div>

    <div class="grid gap-5 xl:grid-cols-[300px_minmax(0,1fr)]">
        {{-- ══ LEFT rail ══ --}}
        <div class="space-y-4">
            {{-- hall settings --}}
            <div class="card p-4">
                <p class="field-label !mb-2 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> Hall</p>
                <input type="text" value="{{ $hall?->name }}" wire:change="renameHall({{ $hall?->id }}, $event.target.value)" wire:key="hallname-{{ $hall?->id }}" class="input mb-2 h-9 w-full text-sm font-bold" placeholder="Hall name">
                <div class="flex items-center gap-2">
                    <div class="flex-1">
                        <label class="mb-0.5 block text-[0.55rem] font-bold uppercase tracking-wide text-muted">Width (m)</label>
                        <input type="number" min="1" step="0.5" wire:model.live.debounce.500ms="hallW" class="input h-9 w-full text-sm">
                    </div>
                    <span class="mt-4 text-navy-300">×</span>
                    <div class="flex-1">
                        <label class="mb-0.5 block text-[0.55rem] font-bold uppercase tracking-wide text-muted">Length (m)</label>
                        <input type="number" min="1" step="0.5" wire:model.live.debounce.500ms="hallL" class="input h-9 w-full text-sm">
                    </div>
                </div>
                @if ($halls->count() > 1)
                    <button type="button" wire:click="deleteHall({{ $hall?->id }})" wire:confirm="Delete “{{ $hall?->name }}”? Its booths return to the tray." class="mt-2.5 text-[0.62rem] font-bold text-risk hover:underline">Delete this hall</button>
                @endif
            </div>

            {{-- unplaced tray --}}
            <div class="card p-4">
                <div class="mb-2 flex items-center justify-between">
                    <p class="field-label !mb-0 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> Unplaced booths</p>
                    @if ($unplaced->isNotEmpty())<button type="button" wire:click="autoArrange" class="text-[0.6rem] font-bold text-gold-600 hover:text-gold-700">Auto-arrange</button>@endif
                </div>
                @forelse ($unplaced as $ex)
                    <button type="button" wire:click="placeBooth({{ $ex->id }})" wire:key="tray-{{ $ex->id }}" class="mb-1.5 flex w-full items-center gap-2 rounded-lg border border-line bg-white px-2.5 py-1.5 text-left transition hover:border-gold-300 hover:bg-gold-50/40">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-sm" style="background: {{ $bh($ex->package)[0] }}; border:1px solid {{ $bh($ex->package)[1] }};"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-xs font-bold text-navy-900">{{ $ex->company }}</span>
                            <span class="block truncate text-[0.56rem] text-muted">{{ $ex->booth_number ? '#'.$ex->booth_number.' · ' : '' }}{{ $ex->booth_size ?: '3×3' }} m · {{ ucfirst($ex->package) }}</span>
                        </span>
                        <span class="shrink-0 text-[0.6rem] font-bold text-gold-600">＋ place</span>
                    </button>
                @empty
                    <p class="py-3 text-center text-[0.68rem] text-muted">All booths are placed in a hall. 🎉</p>
                @endforelse
            </div>

            {{-- fixtures catalog --}}
            <div class="card p-4">
                <p class="field-label !mb-2 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-navy-300"></span> Add fixtures</p>
                <div class="grid grid-cols-2 gap-1.5">
                    @foreach ($fixturePresets as $type => [$label, $fw, $fl, $color])
                        <button type="button" wire:click="addFixture('{{ $type }}')" class="flex items-center gap-1.5 rounded-lg border border-line bg-white px-2 py-1.5 text-left text-[0.66rem] font-semibold text-navy-700 transition hover:border-gold-300 hover:bg-gold-50/40">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-sm" style="background: {{ $color }};"></span>{{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- properties of selected item --}}
            <div class="card p-4">
                <p class="field-label !mb-2 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> Selected</p>
                @if ($selected && $selectedKind === 'booth')
                    <p class="text-sm font-bold text-navy-900">{{ $selected->company }}</p>
                    <p class="mb-3 text-[0.62rem] text-muted">{{ $selected->booth_number ? '#'.$selected->booth_number.' · ' : '' }}{{ $fmtM($selected->booth_w_m) }}×{{ $fmtM($selected->booth_h_m) }} m</p>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-muted">Width (m)</span>
                            <span class="flex items-center gap-1">
                                <button type="button" wire:click="resizeBooth({{ $selected->id }}, 'w', -0.5)" class="seg-step !h-7 !w-7">−</button>
                                <span class="w-10 text-center font-bold text-navy-900">{{ $fmtM($selected->booth_w_m) }}</span>
                                <button type="button" wire:click="resizeBooth({{ $selected->id }}, 'w', 0.5)" class="seg-step !h-7 !w-7">+</button>
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-muted">Length (m)</span>
                            <span class="flex items-center gap-1">
                                <button type="button" wire:click="resizeBooth({{ $selected->id }}, 'h', -0.5)" class="seg-step !h-7 !w-7">−</button>
                                <span class="w-10 text-center font-bold text-navy-900">{{ $fmtM($selected->booth_h_m) }}</span>
                                <button type="button" wire:click="resizeBooth({{ $selected->id }}, 'h', 0.5)" class="seg-step !h-7 !w-7">+</button>
                            </span>
                        </div>
                        <button type="button" wire:click="unplaceBooth({{ $selected->id }})" class="mt-1 h-9 w-full rounded-xl border border-line bg-white text-xs font-semibold text-navy-700 transition hover:border-risk/40 hover:text-risk">Remove from plan</button>
                    </div>
                @elseif ($selected && $selectedKind === 'fixture')
                    <input type="text" value="{{ $selected['label'] }}" wire:change="renameFixture('{{ $selected['id'] }}', $event.target.value)" wire:key="fxlabel-{{ $selected['id'] }}" class="input mb-3 h-9 w-full text-sm font-bold">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-muted">Width (m)</span>
                            <span class="flex items-center gap-1">
                                <button type="button" wire:click="resizeFixture('{{ $selected['id'] }}', 'w', -0.5)" class="seg-step !h-7 !w-7">−</button>
                                <span class="w-10 text-center font-bold text-navy-900">{{ $fmtM($selected['w']) }}</span>
                                <button type="button" wire:click="resizeFixture('{{ $selected['id'] }}', 'w', 0.5)" class="seg-step !h-7 !w-7">+</button>
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-muted">Length (m)</span>
                            <span class="flex items-center gap-1">
                                <button type="button" wire:click="resizeFixture('{{ $selected['id'] }}', 'h', -0.5)" class="seg-step !h-7 !w-7">−</button>
                                <span class="w-10 text-center font-bold text-navy-900">{{ $fmtM($selected['h']) }}</span>
                                <button type="button" wire:click="resizeFixture('{{ $selected['id'] }}', 'h', 0.5)" class="seg-step !h-7 !w-7">+</button>
                            </span>
                        </div>
                        <button type="button" wire:click="removeFixture('{{ $selected['id'] }}')" class="mt-1 h-9 w-full rounded-xl border border-line bg-white text-xs font-semibold text-navy-700 transition hover:border-risk/40 hover:text-risk">Delete fixture</button>
                    </div>
                @else
                    <p class="text-[0.68rem] leading-snug text-muted">Set the hall's real width &amp; length above, place booths from the tray, then drag to arrange — everything is to scale in metres. Press <kbd class="rounded bg-navy-50 px-1 font-bold">Delete</kbd> to remove a selected item.</p>
                @endif
            </div>
        </div>

        {{-- ══ CENTER · canvas ══ --}}
        <div class="card overflow-auto p-4"
             x-data="{
                drag: null, moved: false,
                down(e, kind, id) { this.drag = { kind, id, el: e.currentTarget, x: null, y: null }; this.moved = false; e.currentTarget.setPointerCapture?.(e.pointerId); e.preventDefault(); },
                move(e) {
                    if (!this.drag) return;
                    const cv = this.$refs.canvas, c = cv.getBoundingClientRect();
                    const ox = +cv.dataset.offx, oy = +cv.dataset.offy, vw = +cv.dataset.venw, vh = +cv.dataset.venh;
                    let x = Math.max(ox, Math.min(ox + vw, e.clientX - c.left));
                    let y = Math.max(oy, Math.min(oy + vh, e.clientY - c.top));
                    this.drag.el.style.left = x + 'px'; this.drag.el.style.top = y + 'px';
                    this.drag.x = x; this.drag.y = y; this.moved = true;
                },
                up() {
                    if (this.drag) {
                        if (this.moved && this.drag.x !== null) {
                            const cv = this.$refs.canvas;
                            const scale = +cv.dataset.scale, ox = +cv.dataset.offx, oy = +cv.dataset.offy, wm = +cv.dataset.wm, lm = +cv.dataset.lm;
                            let xM = Math.max(0, Math.min(wm, (this.drag.x - ox) / scale));
                            let yM = Math.max(0, Math.min(lm, (this.drag.y - oy) / scale));
                            if (this.drag.kind === 'booth') { $wire.moveBooth(this.drag.id, xM, yM); }
                            else { $wire.moveFixture(this.drag.id, xM, yM); }
                        } else { $wire.selectItem(this.drag.kind, this.drag.id); }
                    }
                    this.drag = null;
                },
                onKey(e) {
                    const t = (e.target.tagName || ''); if (t === 'INPUT' || t === 'TEXTAREA' || t === 'SELECT') return;
                    const k = $wire.get('selectedKind'), id = $wire.get('selectedId');
                    if (k && (e.key === 'Delete' || e.key === 'Backspace')) { e.preventDefault(); if (k === 'booth') { $wire.unplaceBooth(id); } else { $wire.removeFixture(id); } }
                }
             }"
             @pointermove.window="move($event)" @pointerup.window="up()" @keydown.window="onKey($event)">
            <div class="mx-auto shrink-0" style="width:960px;">
                <div id="floorplan" x-ref="canvas" @pointerdown.self="$wire.deselect()"
                     data-scale="{{ $scale }}" data-offx="{{ $offX }}" data-offy="{{ $offY }}" data-venw="{{ $venW }}" data-venh="{{ $venH }}" data-wm="{{ $W }}" data-lm="{{ $L }}"
                     class="relative overflow-hidden rounded-xl border border-line" style="width:960px; height:560px; background:#F4F6FA;">

                    {{-- hall floor (to scale) with 1 m grid + rulers --}}
                    <div class="pointer-events-none absolute rounded-md border-2 border-navy-200"
                         style="left:{{ $offX }}px; top:{{ $offY }}px; width:{{ $venW }}px; height:{{ $venH }}px; background:
                            linear-gradient(#E4EAF3 1px, transparent 1px) 0 0 / 100% {{ round($gridPx) }}px,
                            linear-gradient(90deg, #E4EAF3 1px, transparent 1px) 0 0 / {{ round($gridPx) }}px 100%,
                            #FFFFFF;"></div>
                    <span class="pointer-events-none absolute -translate-x-1/2 rounded bg-white px-1.5 text-[0.62rem] font-bold text-navy-500" style="left:{{ $offX + $venW / 2 }}px; top:{{ max(2, $offY - 16) }}px;">↔ {{ $fmtM($W) }} m</span>
                    <span class="pointer-events-none absolute origin-center -rotate-90 whitespace-nowrap rounded bg-white px-1.5 text-[0.62rem] font-bold text-navy-500" style="left:{{ max(2, $offX - 24) }}px; top:{{ $offY + $venH / 2 }}px;">↕ {{ $fmtM($L) }} m</span>
                    <span class="pointer-events-none absolute rounded bg-navy-50 px-1.5 text-[0.55rem] font-semibold text-navy-400" style="left:{{ $offX + 6 }}px; top:{{ $offY + 6 }}px;">1 grid = {{ $gridM }} m</span>

                    {{-- fixtures (under booths) --}}
                    @foreach ($fixtures as $f)
                        @php $sel = $selectedKind === 'fixture' && $selectedId === $f['id']; $fc = $fixturePresets[$f['type']][3] ?? '#64748b'; @endphp
                        <div wire:key="fx-{{ $f['id'] }}" @pointerdown="down($event, 'fixture', '{{ $f['id'] }}')"
                             class="absolute cursor-grab touch-none select-none active:cursor-grabbing"
                             style="left:{{ $offX + $f['x'] * $scale }}px; top:{{ $offY + $f['y'] * $scale }}px; width:{{ $f['w'] * $scale }}px; height:{{ $f['h'] * $scale }}px; transform: translate(-50%,-50%);">
                            <div class="flex h-full w-full items-center justify-center overflow-hidden rounded text-center text-[0.58rem] font-bold uppercase tracking-wide {{ $sel ? 'ring-2 ring-gold-500 ring-offset-1' : '' }}"
                                 style="background: {{ $fc }}1f; border: 1.5px dashed {{ $fc }}; color: {{ $fc }};">{{ $f['label'] }}</div>
                        </div>
                    @endforeach

                    {{-- booths --}}
                    @foreach ($placed as $b)
                        @php $sel = $selectedKind === 'booth' && $selectedId === $b->id; [$bg, $tx] = $bh($b->package); $wPx = $b->booth_w_m * $scale; $hPx = $b->booth_h_m * $scale; @endphp
                        <div wire:key="booth-{{ $b->id }}" @pointerdown="down($event, 'booth', {{ $b->id }})"
                             class="absolute cursor-grab touch-none select-none active:cursor-grabbing"
                             style="left:{{ $offX + $b->booth_x * $scale }}px; top:{{ $offY + $b->booth_y * $scale }}px; width:{{ $wPx }}px; height:{{ $hPx }}px; transform: translate(-50%,-50%);">
                            <div class="relative flex h-full w-full flex-col items-center justify-center overflow-hidden rounded border-2 px-0.5 text-center leading-tight {{ $sel ? 'ring-2 ring-gold-500 ring-offset-1' : '' }}"
                                 style="background: {{ $bg }}; border-color: {{ $tx }}; color: {{ $tx }};">
                                <span class="absolute right-0.5 top-0.5 h-2 w-2 rounded-full ring-1 ring-white/80" style="background: {{ $statusHex[$b->status] ?? '#94A3B8' }};" title="{{ ucfirst($b->status) }}"></span>
                                @if ($b->booth_number && $hPx > 26)<span class="text-[0.6rem] font-bold opacity-80">#{{ $b->booth_number }}</span>@endif
                                <span class="w-full truncate text-[0.66rem] font-bold">{{ $b->company }}</span>
                            </div>
                        </div>
                    @endforeach

                    @if ($placed->isEmpty() && empty($fixtures))
                        <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                            <p class="rounded-xl bg-white/80 px-4 py-2 text-xs font-semibold text-muted">Set the hall size, then place booths from the tray or add fixtures.</p>
                        </div>
                    @endif
                </div>
                <div class="mt-2 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-[0.58rem] text-muted">
                    <span class="font-bold uppercase tracking-wide">Status</span>
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full" style="background:#F59E0B"></span> Reserved</span>
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full" style="background:#3B82F6"></span> Confirmed</span>
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full" style="background:#22C55E"></span> Paid</span>
                    <span class="text-navy-300">·</span>
                    <span>Fill colour = package tier</span>
                </div>
                <p class="mt-1 text-center text-[0.6rem] text-muted">Drag any block to move · click to select &amp; resize · booths &amp; hall are drawn to real metre scale.</p>
            </div>
        </div>
    </div>

    <script>
        function floorPng() {
            var node = document.getElementById('floorplan');
            if (!window.html2canvas) { alert('Image renderer still loading — try again in a second.'); return; }
            window.html2canvas(node, { scale: 2, backgroundColor: '#ffffff', logging: false, useCORS: true })
                .then(function (canvas) { var a = document.createElement('a'); a.href = canvas.toDataURL('image/png'); a.download = '{{ $slug }}.png'; a.click(); })
                .catch(function (e) { alert('Could not render image: ' + e); });
        }
    </script>
</div>
