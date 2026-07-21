@php
    // Sales status → booth colours [fill, border/text].
    $statusFill = [
        'available' => ['#FFFFFF', '#94A3B8'],
        'reserved' => ['#FBF3DE', '#8A6D1F'],
        'sold' => ['#E7F6EC', '#1F7A48'],
    ];
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
            <p class="text-xs text-muted">{{ $event->name }} · {{ $fmtM($W) }}×{{ $fmtM($L) }} m ({{ $fmtM($W * $L) }} m²)</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" onclick="floorPng()" class="flex h-10 items-center gap-1.5 rounded-xl border border-line bg-white px-3.5 text-xs font-bold text-navy-700 transition hover:border-gold-300">🖼 Download image</button>
            <a href="{{ route('events.exhibition-floor.pdf', $event) }}" class="flex h-10 items-center gap-1.5 rounded-xl bg-navy-900 px-3.5 text-xs font-bold text-white transition hover:bg-navy-800"><span class="text-gold-400">↧</span> Download PDF</a>
            <a href="{{ route('events.hub', [$event, 'tab' => 'exhibition']) }}" class="flex h-10 items-center rounded-xl border border-line bg-white px-3.5 text-xs font-semibold text-navy-700 transition hover:border-gold-300">← Exhibition</a>
        </div>
    </div>

    {{-- ══ Sales: the floor plan is a revenue dashboard ══ --}}
    <div class="strip-dark mb-4 flex flex-wrap items-center gap-x-8 gap-y-3 px-5 py-4">
        <div class="pointer-events-none absolute -right-10 -top-16 h-44 w-44 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.28),transparent_70%)]"></div>
        <div class="relative">
            <p class="text-eyebrow font-bold uppercase tracking-[0.26em] text-gold-300">Booth Sales</p>
            <p class="mt-0.5 flex items-baseline gap-2">
                <span class="text-3xl font-black leading-none text-gold-400">{{ $sales['sold'] }}</span>
                <span class="text-sm font-semibold text-white">of {{ $sales['total'] }} sold</span>
            </p>
        </div>
        <div class="relative flex flex-wrap items-center gap-2">
            @foreach ([
                ['Revenue sold', $event->money($sales['soldValue']), 'text-emerald-300'],
                ['Pipeline (reserved)', $event->money($sales['pipelineValue']), 'text-gold-300'],
                ['Still on sale', $sales['available'].' · '.$event->money($sales['openValue']), 'text-white/70'],
            ] as [$label, $value, $tone])
                <div class="flex h-12 min-w-[8rem] flex-col justify-center rounded-2xl bg-white/5 px-4 ring-1 ring-white/15">
                    <span class="text-sm font-black leading-none {{ $tone }}">{{ $value }}</span>
                    <span class="mt-1 text-eyebrow font-semibold uppercase tracking-wider text-white/45">{{ $label }}</span>
                </div>
            @endforeach
        </div>
        <div class="relative ml-auto">
            <button type="button" wire:click="addBooth" class="btn-gold h-10 px-4 text-xs">＋ Add booth</button>
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
                <span class="rounded-full px-1.5 text-eyebrow {{ $h->id === $hall?->id ? 'bg-white/15 text-white/80' : 'bg-navy-100 text-navy-500' }}">{{ $h->booths->count() }}</span>
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
                        <label class="mb-0.5 block text-eyebrow font-bold uppercase tracking-wide text-muted">Width (m)</label>
                        <input type="number" min="1" step="0.5" wire:model.live.debounce.500ms="hallW" class="input h-9 w-full text-sm">
                    </div>
                    <span class="mt-4 text-navy-300">×</span>
                    <div class="flex-1">
                        <label class="mb-0.5 block text-eyebrow font-bold uppercase tracking-wide text-muted">Length (m)</label>
                        <input type="number" min="1" step="0.5" wire:model.live.debounce.500ms="hallL" class="input h-9 w-full text-sm">
                    </div>
                </div>
                @if ($halls->count() > 1)
                    <button type="button" wire:click="deleteHall({{ $hall?->id }})" wire:confirm="Delete “{{ $hall?->name }}”? Its booths return to the tray." class="mt-2.5 text-eyebrow font-bold text-risk hover:underline">Delete this hall</button>
                @endif
            </div>

            {{-- waiting exhibitors: buyers without a booth yet --}}
            <div class="card p-4">
                <div class="mb-2 flex items-center justify-between">
                    <p class="field-label !mb-0 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> Waiting for a booth</p>
                    @if ($waiting->isNotEmpty())<button type="button" wire:click="autoArrange" class="text-eyebrow font-bold text-gold-600 hover:text-gold-700">Auto-arrange all</button>@endif
                </div>
                @forelse ($waiting as $ex)
                    <button type="button" wire:click="placeBooth({{ $ex->id }})" wire:key="tray-{{ $ex->id }}" class="mb-1.5 flex w-full items-center gap-2 rounded-lg border border-line bg-white px-2.5 py-1.5 text-left transition hover:border-gold-300 hover:bg-gold-50/40">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-xs font-bold text-navy-900">{{ $ex->company }}</span>
                            <span class="block truncate text-eyebrow text-muted">{{ $ex->booth_size ?: '3×3' }} m · {{ ucfirst($ex->package) }}{{ $ex->fee_cents ? ' · '.$event->money($ex->fee_cents) : '' }}</span>
                        </span>
                        <span class="shrink-0 text-eyebrow font-bold text-gold-600">＋ booth</span>
                    </button>
                @empty
                    <p class="py-3 text-center text-micro text-muted">Every exhibitor has a booth. Sell the empty ones →</p>
                @endforelse
            </div>

            {{-- fixtures catalog --}}
            <div class="card p-4">
                <p class="field-label !mb-2 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-navy-300"></span> Add fixtures</p>
                <div class="grid grid-cols-2 gap-1.5">
                    @foreach ($fixturePresets as $type => [$label, $fw, $fl, $color])
                        <button type="button" wire:click="addFixture('{{ $type }}')" class="flex items-center gap-1.5 rounded-lg border border-line bg-white px-2 py-1.5 text-left text-micro font-semibold text-navy-700 transition hover:border-gold-300 hover:bg-gold-50/40">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-sm" style="background: {{ $color }};"></span>{{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- properties of selected item --}}
            <div class="card p-4">
                <p class="field-label !mb-2 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> Selected</p>
                @if ($selected && $selectedKind === 'booth')
                    @php [$selFill, $selEdge] = $statusFill[$selected->status()]; @endphp
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <p class="text-sm font-bold text-navy-900">Booth {{ $selected->number }}</p>
                        <span class="rounded-full px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide" style="background: {{ $selFill }}; color: {{ $selEdge }}; border: 1px solid {{ $selEdge }};">{{ $selected->statusLabel() }}</span>
                    </div>

                    {{-- The sale --}}
                    @if ($selected->exhibitor)
                        <div class="mb-2 rounded-lg bg-navy-50/60 px-2.5 py-2">
                            <p class="truncate text-xs font-bold text-navy-900">{{ $selected->exhibitor->company }}</p>
                            <p class="text-eyebrow text-muted">{{ ucfirst($selected->exhibitor->status) }} · {{ $event->money($selected->exhibitor->fee_cents) }} fee</p>
                            <button type="button" wire:click="releaseExhibitor({{ $selected->id }})" wire:confirm="Release {{ $selected->exhibitor->company }}? The booth goes back on sale." class="mt-1 text-eyebrow font-bold text-risk hover:underline">Release booth</button>
                        </div>
                    @elseif ($waiting->isNotEmpty())
                        <select wire:change="assignExhibitor({{ $selected->id }}, $event.target.value)" class="input mb-2 h-9 w-full text-xs">
                            <option value="">Sell to…</option>
                            @foreach ($waiting as $w)<option value="{{ $w->id }}">{{ $w->company }}</option>@endforeach
                        </select>
                    @endif

                    <div class="space-y-2">
                        <div class="flex items-center justify-between gap-2 text-xs">
                            <span class="text-muted">Number</span>
                            <input type="text" value="{{ $selected->number }}" wire:change="setBoothNumber({{ $selected->id }}, $event.target.value)" wire:key="bno-{{ $selected->id }}" class="input h-8 w-24 text-right text-xs font-bold">
                        </div>
                        <div class="flex items-center justify-between gap-2 text-xs">
                            <span class="text-muted">Price ({{ $event->currency ?? 'USD' }})</span>
                            <input type="number" min="0" step="50" value="{{ $selected->price_cents / 100 }}" wire:change="setBoothPrice({{ $selected->id }}, $event.target.value)" wire:key="bpr-{{ $selected->id }}" class="input h-8 w-24 text-right text-xs font-bold">
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-muted">Width (m)</span>
                            <span class="flex items-center gap-1">
                                <button type="button" wire:click="resizeBooth({{ $selected->id }}, 'w', -0.5)" class="seg-step !h-7 !w-7">−</button>
                                <span class="w-10 text-center font-bold text-navy-900">{{ $fmtM($selected->w_m) }}</span>
                                <button type="button" wire:click="resizeBooth({{ $selected->id }}, 'w', 0.5)" class="seg-step !h-7 !w-7">+</button>
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-muted">Length (m)</span>
                            <span class="flex items-center gap-1">
                                <button type="button" wire:click="resizeBooth({{ $selected->id }}, 'h', -0.5)" class="seg-step !h-7 !w-7">−</button>
                                <span class="w-10 text-center font-bold text-navy-900">{{ $fmtM($selected->h_m) }}</span>
                                <button type="button" wire:click="resizeBooth({{ $selected->id }}, 'h', 0.5)" class="seg-step !h-7 !w-7">+</button>
                            </span>
                        </div>
                        <button type="button" wire:click="deleteBooth({{ $selected->id }})" wire:confirm="Delete booth {{ $selected->number }}?{{ $selected->exhibitor ? ' '.$selected->exhibitor->company.' returns to the waiting list.' : '' }}" class="mt-1 h-9 w-full rounded-xl border border-line bg-white text-xs font-semibold text-navy-700 transition hover:border-risk/40 hover:text-risk">Delete booth</button>
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
                    <p class="text-micro leading-snug text-muted">Set the hall's real width &amp; length above, place booths from the tray, then drag to arrange — everything is to scale in metres. Press <kbd class="rounded bg-navy-50 px-1 font-bold">Delete</kbd> to remove a selected item.</p>
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
                    if (k && (e.key === 'Delete' || e.key === 'Backspace')) { e.preventDefault(); if (k === 'booth') { $wire.deleteBooth(id); } else { $wire.removeFixture(id); } }
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
                    <span class="pointer-events-none absolute -translate-x-1/2 rounded bg-white px-1.5 text-eyebrow font-bold text-navy-500" style="left:{{ $offX + $venW / 2 }}px; top:{{ max(2, $offY - 16) }}px;">↔ {{ $fmtM($W) }} m</span>
                    <span class="pointer-events-none absolute origin-center -rotate-90 whitespace-nowrap rounded bg-white px-1.5 text-eyebrow font-bold text-navy-500" style="left:{{ max(2, $offX - 24) }}px; top:{{ $offY + $venH / 2 }}px;">↕ {{ $fmtM($L) }} m</span>
                    <span class="pointer-events-none absolute rounded bg-navy-50 px-1.5 text-eyebrow font-semibold text-navy-400" style="left:{{ $offX + 6 }}px; top:{{ $offY + 6 }}px;">1 grid = {{ $gridM }} m</span>

                    {{-- fixtures (under booths) --}}
                    @foreach ($fixtures as $f)
                        @php $sel = $selectedKind === 'fixture' && $selectedId === $f['id']; $fc = $fixturePresets[$f['type']][3] ?? 'var(--color-neutral)'; @endphp
                        <div wire:key="fx-{{ $f['id'] }}" @pointerdown="down($event, 'fixture', '{{ $f['id'] }}')"
                             class="absolute cursor-grab touch-none select-none active:cursor-grabbing"
                             style="left:{{ $offX + $f['x'] * $scale }}px; top:{{ $offY + $f['y'] * $scale }}px; width:{{ $f['w'] * $scale }}px; height:{{ $f['h'] * $scale }}px; transform: translate(-50%,-50%);">
                            <div class="flex h-full w-full items-center justify-center overflow-hidden rounded text-center text-eyebrow font-bold uppercase tracking-wide {{ $sel ? 'ring-2 ring-gold-500 ring-offset-1' : '' }}"
                                 style="background: {{ $fc }}1f; border: 1.5px dashed {{ $fc }}; color: {{ $fc }};">{{ $f['label'] }}</div>
                        </div>
                    @endforeach

                    {{-- booths: colour = sales status --}}
                    @foreach ($booths as $b)
                        @php
                            $sel = $selectedKind === 'booth' && $selectedId === $b->id;
                            $st = $b->status();
                            [$bg, $tx] = $statusFill[$st];
                            $wPx = $b->w_m * $scale; $hPx = $b->h_m * $scale;
                        @endphp
                        <div wire:key="booth-{{ $b->id }}" @pointerdown="down($event, 'booth', {{ $b->id }})"
                             class="absolute cursor-grab touch-none select-none active:cursor-grabbing"
                             style="left:{{ $offX + $b->x * $scale }}px; top:{{ $offY + $b->y * $scale }}px; width:{{ $wPx }}px; height:{{ $hPx }}px; transform: translate(-50%,-50%);">
                            <div class="relative flex h-full w-full flex-col items-center justify-center overflow-hidden rounded px-0.5 text-center leading-tight {{ $sel ? 'ring-2 ring-gold-500 ring-offset-1' : '' }}"
                                 style="background: {{ $bg }}; border: 2px {{ $st === 'available' ? 'dashed' : 'solid' }} {{ $tx }}; color: {{ $tx }};">
                                @if ($hPx > 26)<span class="text-eyebrow font-bold opacity-80">{{ $b->number }}</span>@endif
                                <span class="w-full truncate text-micro font-bold">
                                    {{ $b->exhibitor?->company ?? ($b->price_cents ? $event->money($b->price_cents) : 'For sale') }}
                                </span>
                            </div>
                        </div>
                    @endforeach

                    @if ($booths->isEmpty() && empty($fixtures))
                        <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                            <p class="rounded-xl bg-white/80 px-4 py-2 text-xs font-semibold text-muted">Set the hall size, then add booths to sell — or pull waiting exhibitors in from the tray.</p>
                        </div>
                    @endif
                </div>
                <div class="mt-2 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-eyebrow text-muted">
                    <span class="font-bold uppercase tracking-wide">Status</span>
                    <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-sm border border-dashed" style="border-color:#94A3B8; background:#fff"></span> Available</span>
                    <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-sm border" style="border-color:#8A6D1F; background:#FBF3DE"></span> Reserved</span>
                    <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-sm border" style="border-color:#1F7A48; background:#E7F6EC"></span> Sold</span>
                </div>
                <p class="mt-1 text-center text-eyebrow text-muted">Drag any block to move · click to select &amp; resize · booths &amp; hall are drawn to real metre scale.</p>
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
