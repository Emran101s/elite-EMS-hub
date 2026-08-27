            {{-- ══ Where the money goes — spend composition ══
                 The one read the header (budget used) and the sidebar (P&L
                 summary) don't give: how the cost splits across categories,
                 seen rather than added up from the ledger below. Read-only,
                 off the same forecast the ledger totals — a segmented share
                 bar plus a legend, biggest first. Each category keeps the
                 colour it takes from its position in the ledger, so the band
                 and the rows beneath it read as one thing. --}}
            @php
                // $catSolid is hoisted to the parent's @php block so the ledger
                // rows below share the exact same per-position colours.
                $catColorFor = [];
                foreach ($sections as $ci => $cs) {
                    $catColorFor[$cs['name']] = $catSolid[$ci % count($catSolid)];
                }
                $composition = collect($sections)
                    ->map(fn ($cs) => [
                        'name' => $cs['name'],
                        'cost' => (int) $cs['items']->sum(fn ($it) => $it->costCents()),
                        'color' => $catColorFor[$cs['name']],
                    ])
                    ->filter(fn ($r) => $r['cost'] > 0)
                    ->sortByDesc('cost')
                    ->values();
                $compTotal = (int) $composition->sum('cost');
            @endphp

            @if ($compTotal > 0)
                <div class="mb-3 rounded-lg border border-line bg-white p-4">
                    <div class="mb-3 flex items-baseline justify-between gap-3">
                        <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Where the money goes</p>
                        <p class="text-[13px] font-bold tabular-nums text-ink">{{ $fmt($compTotal) }} <span class="font-semibold text-muted">forecast</span></p>
                    </div>

                    {{-- segmented share bar --}}
                    <div class="flex h-2.5 w-full overflow-hidden rounded-full bg-page">
                        @foreach ($composition as $c)
                            <span class="h-full first:rounded-l-full last:rounded-r-full"
                                  style="width: {{ max(1.5, round($c['cost'] / $compTotal * 100, 2)) }}%; background: {{ $c['color'] }}"
                                  title="{{ $c['name'] }} · {{ $fmt($c['cost']) }}"></span>
                        @endforeach
                    </div>

                    {{-- legend --}}
                    <div class="mt-3 grid grid-cols-2 gap-x-5 gap-y-1.5 sm:grid-cols-3 xl:grid-cols-4">
                        @foreach ($composition as $c)
                            <div class="flex items-center gap-2 text-[11.5px]">
                                <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $c['color'] }}"></span>
                                <span class="min-w-0 flex-1 truncate font-semibold text-ink">{{ $c['name'] }}</span>
                                <span class="shrink-0 tabular-nums text-muted">{{ round($c['cost'] / $compTotal * 100) }}%</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

