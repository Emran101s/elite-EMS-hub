            {{-- ══ Where the money goes — spend composition, as a honeycomb ══
                 The one read the header (budget used) and the sidebar (P&L
                 summary) don't give: how the cost splits across categories,
                 seen rather than added up from the ledger below. Read-only,
                 off the same forecast the ledger totals — one hex per
                 category, sized by its share, biggest first. Each keeps the
                 colour it takes from its position in the ledger, so the
                 honeycomb and the rows beneath it read as one thing. --}}
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
                $maxShare = $compTotal > 0 ? $composition->max('cost') / $compTotal : 1;
            @endphp

            @if ($compTotal > 0)
                <div class="cx-lcard">
                    <div class="cx-lcard-head">
                        <span class="cx-lt">Where the money goes</span>
                        <span class="text-[13px] font-bold tabular-nums text-ink">{{ $fmt($compTotal) }} <span class="font-semibold text-muted">forecast</span></span>
                    </div>

                    <div class="cx-honeyband p-4">
                        @foreach ($composition as $c)
                            @php
                                $share = $compTotal > 0 ? $c['cost'] / $compTotal : 0;
                                // Hex sized 40–72px by its share relative to the largest slice,
                                // so "where the money goes" reads at a glance, not just a legend.
                                $size = (int) round(40 + ($maxShare > 0 ? $share / $maxShare : 0) * 32);
                            @endphp
                            <span class="cx-hcell" title="{{ $c['name'] }} · {{ $fmt($c['cost']) }}">
                                <span class="cx-hcx" style="width:{{ $size }}px;height:{{ round($size * 1.1) }}px;background:{{ $c['color'] }};font-size:{{ max(10, round($size * .3)) }}px">{{ round($share * 100) }}%</span>
                                <span class="cx-hcn">{{ $c['name'] }}</span>
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

