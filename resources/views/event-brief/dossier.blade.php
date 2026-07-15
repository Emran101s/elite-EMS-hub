<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=playfair-display:500,600,700,800,900&display=swap" rel="stylesheet">
    <style>{!! $css !!}</style>
    <style>
        @page { size: A4; margin: 0; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        html, body { background: #ffffff; }
        .sheet { width: 794px; margin: 0 auto; }
        .sec-head { break-after: avoid; }   /* never orphan a heading at a page foot */
        .avoid { break-inside: avoid; }     /* keep individual rows/cards intact */
        .pf { font-family: 'Playfair Display', Georgia, serif; }
    </style>
    @php
        $meta = $data['meta'] ?? [];
        $n2 = fn ($n) => str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    @endphp
</head>
<body class="bg-white font-sans text-ink antialiased">
<div class="sheet">

    {{-- Dossier hero --}}
    <div class="relative overflow-hidden bg-gradient-to-br from-navy-900 via-navy-900 to-[#071528] px-12 pb-10 pt-12 text-white">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -right-16 top-1/2 h-64 w-64 -translate-y-1/2 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.22),transparent_65%)]"></div>
            <div class="absolute left-12 top-11 h-px w-16 bg-gold-400"></div>
        </div>
        <div class="relative">
            <p class="pt-4 text-[0.62rem] font-bold uppercase tracking-[0.36em] text-gold-400">◆ Elite Business Hub · Event Dossier</p>
            <h1 class="pf mt-5 text-[2.6rem] font-bold leading-[1.05] text-white">{{ $event->name }}</h1>
            <p class="pf mt-3 text-base italic text-white/60">{{ $meta['subtitle'] ?? 'Event project brief & single source of truth' }}</p>
            <div class="mt-8 grid grid-cols-3 gap-x-8 gap-y-5 border-t border-white/10 pt-6">
                @foreach ([['Prepared For', $meta['prepared_for'] ?? '—'],['Prepared By', $meta['prepared_by'] ?? 'Elite Business Hub'],['Version', 'v'.$brief->version.($brief->status === 'approved' ? ' · Approved' : '')],['Event Dates', $data['event_info']['dates'] ?? 'TBC'],['Location', $data['event_info']['location'] ?? '—'],['Confidentiality', $meta['confidentiality'] ?? 'Confidential']] as [$lbl, $val])
                    <div>
                        <p class="text-[0.52rem] font-bold uppercase tracking-[0.2em] text-gold-400/80">{{ $lbl }}</p>
                        <p class="mt-1.5 text-[0.9rem] font-medium text-white">{{ $val }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- How-to strip --}}
    @if (! empty($meta['how_to']))
        <div class="flex items-start gap-3 border-b border-line bg-gold-50/50 px-12 py-4">
            <span class="mt-0.5 text-gold-500">✦</span>
            <p class="pf text-[0.8rem] italic leading-relaxed text-navy-500">{{ $meta['how_to'] }}</p>
        </div>
    @endif

    {{-- Sections --}}
    <div class="space-y-11 px-12 py-11">
        @foreach ($sections as $key => [$num, $title, $type])
            <section>
                <div class="sec-head mb-5 flex items-baseline gap-3 border-b border-line pb-2.5">
                    <span class="pf text-3xl font-bold leading-none text-gold-400/40">{{ $n2($num) }}</span>
                    <h3 class="pf text-xl font-bold text-navy-900">{{ $title }}</h3>
                    <span class="ml-auto h-[3px] w-10 self-center rounded-full bg-gold-400"></span>
                </div>

                @if ($type === 'kv')
                    <dl class="divide-y divide-line/70">
                        @foreach ($infoFields as $fkey => $flabel)
                            <div class="avoid grid grid-cols-[150px_1fr] items-center gap-4 py-2.5">
                                <dt class="text-[0.58rem] font-bold uppercase tracking-[0.12em] text-navy-400">{{ $flabel }}</dt>
                                <dd class="text-[0.92rem] font-medium text-navy-900">{{ $data['event_info'][$fkey] ?? '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>

                @elseif ($type === 'text')
                    <div class="border-l-2 border-gold-300 pl-5">
                        <p class="text-[0.98rem] leading-relaxed text-navy-700">{{ $data[$key] ?? '' }}</p>
                    </div>

                @elseif ($type === 'bullets')
                    <ul class="space-y-2">
                        @foreach ($data[$key] ?? [] as $line)
                            @if (trim($line) !== '')
                                <li class="flex items-start gap-3"><span class="mt-0.5 text-gold-500">▸</span><span class="text-[0.92rem] text-navy-700">{{ $line }}</span></li>
                            @endif
                        @endforeach
                    </ul>

                @elseif ($type === 'kpi')
                    <div class="grid grid-cols-3 gap-3">
                        @foreach ($data[$key] ?? [] as $row)
                            <div class="avoid rounded-2xl border border-line bg-gradient-to-br from-page/60 to-white p-4">
                                <div class="pf text-[1.7rem] font-bold leading-none text-navy-900">{{ $row['target'] ?? '' }}</div>
                                <div class="mt-2 text-[0.6rem] font-bold uppercase tracking-[0.1em] text-navy-400">{{ $row['kpi'] ?? '' }}</div>
                                <span class="mt-2.5 block h-0.5 w-8 rounded-full bg-gold-400"></span>
                            </div>
                        @endforeach
                    </div>

                @elseif ($type === 'twocol')
                    <div>
                        <div class="grid grid-cols-[180px_1fr] gap-4 border-b-2 border-gold-400 px-3 pb-1.5 text-[0.54rem] font-bold uppercase tracking-[0.12em] text-navy-400">
                            <span>{{ $twocolHeads[0] }}</span><span>{{ $twocolHeads[1] }}</span>
                        </div>
                        @foreach ($data[$key] ?? [] as $row)
                            <div class="avoid grid grid-cols-[180px_1fr] items-start gap-4 border-b border-line/70 px-3 py-2.5">
                                <div class="pf text-[0.92rem] font-bold text-navy-900">{{ $row['area'] ?? '' }}</div>
                                <div class="text-[0.85rem] leading-relaxed text-navy-600">{{ $row['notes'] ?? '' }}</div>
                            </div>
                        @endforeach
                    </div>

                @elseif ($type === 'approval')
                    <div class="grid grid-cols-3 gap-3">
                        @foreach ($data[$key] ?? [] as $row)
                            <div class="avoid rounded-2xl border border-line bg-page/30 p-4">
                                <div class="text-[0.55rem] font-bold uppercase tracking-[0.12em] text-gold-600">{{ $row['title'] ?? '' }}</div>
                                <div class="pf mt-1.5 text-[0.95rem] font-bold text-navy-900">{{ $row['name'] ?: '—' }}</div>
                                <div class="mt-10 border-t border-dashed border-navy-200 pt-1.5 text-[0.5rem] uppercase tracking-wider text-navy-300">Signature &amp; date</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
    </div>

    <div class="bg-navy-950 px-12 py-4 text-center">
        <p class="text-[0.55rem] font-medium uppercase tracking-[0.3em] text-white/40">{{ $meta['confidentiality'] ?? 'Confidential' }} · Elite Business Hub · v{{ $brief->version }}</p>
    </div>

</div>
</body>
</html>
