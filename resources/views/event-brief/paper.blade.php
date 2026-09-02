{{--
    THE DOSSIER PAPER — one source of truth for how the Event Brief looks.
    The tab's live preview includes it and the PDF wrapper includes it with the
    same compiled CSS, so the export IS the preview.

    Expects: $event, $data, $version, $status, $sections, $infoFields,
             $twocolHeads, $forPdf
--}}
@php
    $meta = $data['meta'] ?? [];
    $hasContent = function (string $key, string $type) use ($data, $event) {
        return match ($type) {
            // The scope lives on its own tab, not in the brief's data blob.
            'sourced' => $event->scopeItems->isNotEmpty(),
            'text' => trim((string) ($data[$key] ?? '')) !== '',
            'kv' => collect($data['event_info'] ?? [])->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty(),
            default => collect($data[$key] ?? [])->filter(fn ($row) => is_array($row)
                ? collect($row)->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty()
                : trim((string) $row) !== '')->isNotEmpty(),
        };
    };
@endphp
<div class="relative mx-auto max-w-[640px] overflow-hidden rounded-md bg-white {{ $forPdf ? '' : 'shadow-[0_30px_70px_-30px_rgba(9,24,49,0.5)]' }}">

    {{-- ═══ dossier hero ═══ --}}
    <div class="relative overflow-hidden bg-gradient-to-br from-navy-900 to-[#061225] px-9 py-8 text-white">
        <div class="pointer-events-none absolute -right-12 -top-16 h-52 w-52 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.24),transparent_70%)]"></div>
        <div class="relative">
            <span class="mb-3 block h-px w-14 bg-gold-400"></span>
            <p class="text-3xs font-bold uppercase tracking-[0.32em] text-gold-300">◆ Elite Business Hub · Event Dossier</p>
            <h1 class="pf mt-2 text-2xl font-black leading-tight">{{ $event->name }}</h1>
            @if (($meta['subtitle'] ?? '') !== '')
                <p class="pf mt-1 text-sm italic text-white/60">{{ $meta['subtitle'] }}</p>
            @endif
            <div class="mt-5 grid grid-cols-3 gap-x-6 gap-y-3 border-t border-white/10 pt-4">
                @foreach ([['Prepared for', 'prepared_for'], ['Prepared by', 'prepared_by'], ['Confidentiality', 'confidentiality']] as [$lbl, $mk])
                    <div>
                        <p class="text-3xs font-bold uppercase tracking-[0.18em] text-white/40">{{ $lbl }}</p>
                        <p class="mt-0.5 text-xs font-semibold">{{ ($meta[$mk] ?? '') ?: '—' }}</p>
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-3xs text-white/45">
                Version {{ $version }}
                @if ($status === 'approved') · <span class="font-bold text-emerald-300">✓ Approved</span>@else · Draft @endif
            </p>
        </div>
    </div>

    {{-- how to use this document --}}
    @if (($meta['how_to'] ?? '') !== '')
        <div class="border-b border-line bg-gold-50/50 px-9 py-3">
            <p class="text-3xs italic leading-relaxed text-navy-500"><span class="mr-1 text-gold-600">✦</span>{{ $meta['how_to'] }}</p>
        </div>
    @endif

    {{-- ═══ sections ═══ --}}
    <div class="px-9 py-7">
        @foreach ($sections as $key => [$num, $title, $type])
            @continue (! $hasContent($key, $type))

            <div class="mb-6 last:mb-0">
                <div class="break-inside-avoid flex items-baseline gap-3 border-b border-navy-900 pb-1">
                    <span class="pf text-lg font-black leading-none text-gold-700/50">{{ str_pad($num, 2, '0', STR_PAD_LEFT) }}</span>
                    <h3 class="pf text-sm font-black text-navy-900">{{ $title }}</h3>
                </div>

                @if ($type === 'sourced')
                    @php $scope = $event->scopeItems; @endphp
                    <div class="mt-2 space-y-3">
                        @foreach ($scope->where('is_exclusion', false)->groupBy('area') as $rows)
                            <div class="break-inside-avoid">
                                <p class="text-4xs font-black uppercase tracking-[0.14em] text-gold-700">{{ $rows->first()->areaLabel() }}</p>
                                <ul class="mt-1 space-y-1">
                                    @foreach ($rows as $item)
                                        <li class="text-3xs leading-relaxed text-navy-700">
                                            <span class="font-bold text-navy-900">{{ $item->title }}</span>@if ($item->body) — {{ $item->body }}@endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach

                        @if ($scope->where('is_exclusion', true)->isNotEmpty())
                            <div class="break-inside-avoid border-l-2 border-gold-300 pl-4">
                                <p class="text-4xs font-black uppercase tracking-[0.14em] text-navy-900">Not included in this scope</p>
                                <ul class="mt-1 space-y-1">
                                    @foreach ($scope->where('is_exclusion', true) as $item)
                                        <li class="text-3xs leading-relaxed text-navy-700">
                                            <span class="font-bold text-navy-900">{{ $item->title }}</span>@if ($item->body) — {{ $item->body }}@endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>

                @elseif ($type === 'text')
                    <div class="mt-2 border-l-2 border-gold-300 pl-4">
                        <p class="whitespace-pre-line text-3xs leading-relaxed text-navy-700">{{ $data[$key] }}</p>
                    </div>

                @elseif ($type === 'kv')
                    <dl class="mt-2 grid grid-cols-2 gap-x-6">
                        @foreach ($infoFields as $fkey => $flabel)
                            @continue (trim((string) ($data['event_info'][$fkey] ?? '')) === '')
                            <div class="break-inside-avoid flex items-baseline justify-between gap-3 border-b border-line/70 py-1.5">
                                <dt class="shrink-0 text-3xs font-bold uppercase tracking-[0.1em] text-navy-400">{{ $flabel }}</dt>
                                <dd class="text-right text-3xs font-semibold text-navy-900">{{ $data['event_info'][$fkey] }}</dd>
                            </div>
                        @endforeach
                    </dl>

                @elseif ($type === 'bullets')
                    <ul class="mt-2 space-y-1">
                        @foreach ($data[$key] ?? [] as $line)
                            @continue (trim((string) $line) === '')
                            <li class="break-inside-avoid flex gap-2 text-3xs leading-relaxed text-navy-700">
                                <span class="text-gold-600">◆</span><span>{{ $line }}</span>
                            </li>
                        @endforeach
                    </ul>

                @elseif ($type === 'kpi')
                    <div class="mt-2.5 grid grid-cols-3 gap-2.5">
                        @foreach ($data[$key] ?? [] as $row)
                            @continue (trim((string) ($row['kpi'] ?? '')) === '' && trim((string) ($row['target'] ?? '')) === '')
                            <div class="break-inside-avoid rounded-xl border border-line bg-page/40 px-3 py-2.5">
                                <p class="pf text-base font-black leading-tight text-navy-900">{{ ($row['target'] ?? '') ?: '—' }}</p>
                                <p class="mt-0.5 text-[8px] font-bold uppercase tracking-[0.1em] text-navy-400">{{ $row['kpi'] ?? '' }}</p>
                                <span class="mt-1.5 block h-0.5 w-6 rounded-full bg-gold-400"></span>
                            </div>
                        @endforeach
                    </div>

                @elseif ($type === 'twocol')
                    <table class="mt-2 w-full">
                        <thead>
                            <tr class="text-left text-[8px] font-bold uppercase tracking-[0.12em] text-navy-400">
                                <th class="w-[30%] py-1 pr-3">{{ $twocolHeads[0] }}</th>
                                <th class="py-1">{{ $twocolHeads[1] }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data[$key] ?? [] as $row)
                                @continue (trim((string) ($row['area'] ?? '')) === '' && trim((string) ($row['notes'] ?? '')) === '')
                                <tr class="break-inside-avoid border-b border-line/70 align-top last:border-0">
                                    <td class="py-1.5 pr-3 text-3xs font-bold text-navy-900">{{ $row['area'] ?? '' }}</td>
                                    <td class="whitespace-pre-line py-1.5 text-3xs leading-relaxed text-navy-600">{{ $row['notes'] ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endforeach
    </div>

    {{-- footer band --}}
    <div class="bg-navy-900 px-9 py-3 text-center">
        <p class="text-[8px] font-semibold uppercase tracking-[0.3em] text-white/45">
            {{ ($meta['confidentiality'] ?? '') ?: 'Confidential' }} · Elite Business Hub · v{{ $version }}
        </p>
    </div>
</div>
