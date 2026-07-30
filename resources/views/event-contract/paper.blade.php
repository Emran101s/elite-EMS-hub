{{--
    THE PAPER — the one source of truth for how a contract document looks.
    The live preview includes it, and the PDF wrapper includes it with the very
    same compiled CSS, so what you see in the editor IS what exports. Change it
    here and both surfaces change together.

    Expects: $tLabel, $type, $title, $data, $bilingual, $event, $fmt, $est, $f,
             $signatories, $reference, $status, $fullySigned, $forPdf
--}}
@php
    $company = $company ?? \App\Models\CompanyProfile::first();
    // Only offer the logo if the file is really there — a broken image on a
    // letterhead is worse than the seal fallback.
    $companyLogo = $company?->logo_path && file_exists(public_path('storage/'.$company->logo_path))
        ? asset('storage/'.$company->logo_path)
        : null;
    $isLetter = $type === 'letter';

    // The preamble: who is contracting, where, on what date, and what for.
    // Generated rather than stored, so it can never drift from the parties and
    // dates panels — edit a representative there and this sentence follows.
    $recitals = $type === 'client' && isset($data['meta'], $data['first_party'])
        ? \App\Support\ContractClauses::recitals($data)
        : null;
@endphp
<div class="relative mx-auto max-w-[640px] overflow-hidden rounded-md bg-white {{ $forPdf ? '' : 'shadow-[0_30px_70px_-30px_rgba(9,24,49,0.5)]' }}">

    @if ($isLetter)
        {{-- ═══ letterhead — a business letter, not an agreement ═══ --}}
        <div class="px-10 pt-9">
            <div class="flex items-start justify-between gap-6 border-b-2 border-navy-900 pb-5">
                <div class="flex items-center gap-3.5">
                    @if ($companyLogo)
                        <img src="{{ $companyLogo }}" alt="" class="h-12 w-auto">
                    @else
                        <span class="flex h-12 w-12 items-center justify-center rounded-full text-base font-bold text-[#5a4718] shadow"
                              style="background: radial-gradient(circle at 35% 30%, #E4C874, #C8A44A 55%, #9c7d2e); font-family: Georgia, serif;">EB</span>
                    @endif
                    <div>
                        <p class="pf text-lg font-black leading-tight text-navy-900">{{ $company?->name ?? 'Elite Business Hub' }}</p>
                        <p class="text-3xs font-bold uppercase tracking-[0.22em] text-gold-700">Events · Conferences · Exhibitions</p>
                    </div>
                </div>
                <div class="text-right text-3xs leading-relaxed text-muted">
                    @if ($company?->address)<span class="block">{{ $company->address }}</span>@endif
                    @if ($company?->city || $company?->country)<span class="block">{{ collect([$company?->city, $company?->country])->filter()->implode(', ') }}</span>@endif
                    @if ($company?->phone)<span class="block">{{ $company->phone }}</span>@endif
                    @if ($company?->email)<span class="block">{{ $company->email }}</span>@endif
                    @if ($company?->website)<span class="block">{{ $company->website }}</span>@endif
                </div>
            </div>

            <div class="mt-5 flex items-start justify-between gap-6">
                <div>
                    <p class="text-3xs font-bold uppercase tracking-[0.16em] text-muted">To</p>
                    <p class="text-sm font-bold text-navy-900">{{ ($data['counterparty']['name_en'] ?? '') ?: '—' }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs font-semibold text-navy-900">{{ $data['meta']['date'] ?? now()->format('j F Y') }}</p>
                    <p class="font-mono text-3xs text-muted">{{ $reference }}</p>
                </div>
            </div>

            @if ($title && $title !== 'Letter')
                <p class="mt-4 text-sm font-black text-navy-900">Re: {{ $title }}</p>
            @endif
        </div>
    @else
        {{-- paper masthead --}}
        <div class="relative overflow-hidden bg-gradient-to-br from-navy-900 to-[#061225] px-8 py-6 text-white">
            <div class="pointer-events-none absolute -right-10 -top-16 h-44 w-44 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.28),transparent_70%)]"></div>
            <p class="text-3xs font-bold uppercase tracking-[0.26em] text-gold-300">Elite Business Hub · {{ $tLabel }}</p>
            <p class="pf mt-1 text-xl font-black leading-tight">{{ $title ?: ($type === 'client' ? ($data['meta']['title_en'] ?? 'Management Services Agreement') : $tLabel) }}</p>
            @if ($bilingual && ($data['meta']['title_ar'] ?? ''))
                <p dir="rtl" class="mt-0.5 text-sm text-white/70 [font-family:Amiri,serif]">{{ $data['meta']['title_ar'] }}</p>
            @endif
            <p class="mt-2 text-3xs text-white/55">
                {{ $data['event']['name'] ?? $event->name }} · {{ $data['event']['dates'] ?? '' }} · {{ $data['event']['location'] ?? '' }}
            </p>
        </div>
    @endif

    <div class="px-8 py-6 text-[12.5px] leading-relaxed text-navy-900">

        {{-- parties strip --}}
        @if ($type === 'client')
            <div class="mb-5 rounded-xl border border-line bg-page/40 px-4 py-3">
                <p class="text-3xs font-bold uppercase tracking-[0.14em] text-gold-700">Between</p>
                <p class="mt-1 text-xs font-bold">{{ $data['first_party']['name_en'] ?? 'Elite Business Hub' }}</p>
                <p class="text-3xs text-muted">First Party</p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @foreach ($data['second_parties'] ?? [] as $sp)
                        <span class="rounded-full bg-navy-900 px-2.5 py-1 text-3xs font-bold text-white">
                            {{ $sp['name_en'] ?: '—' }} · <span class="text-gold-300">{{ (float) ($sp['share'] ?? 0) }}%</span>
                        </span>
                    @endforeach
                </div>
            </div>
        @elseif (! $isLetter && ($data['counterparty']['name_en'] ?? '') !== '')
            <div class="mb-5 rounded-xl border border-line bg-page/40 px-4 py-3">
                <p class="text-3xs font-bold uppercase tracking-[0.14em] text-gold-700">With</p>
                <p class="mt-1 text-xs font-bold">{{ $data['counterparty']['name_en'] }}</p>
            </div>
        @endif

        {{-- ── the preamble ──
             An agreement that opens on "1. Scope of Work" is missing the words
             that make it an agreement: entered into where, on what date, by
             whom, and — the recital — for what. This was computed and never
             printed; the card above summarises the parties, this states them. --}}
        @if ($recitals)
            <div class="mb-5 border-b border-line pb-4">
                @foreach ($recitals['en'] as $i => $para)
                    @if ($bilingual)
                        <div class="mt-2 grid grid-cols-2 gap-3 first:mt-0">
                            <p class="text-3xs leading-relaxed text-navy-700">{{ $para }}</p>
                            <p dir="rtl" class="text-3xs leading-relaxed text-navy-700 [font-family:Amiri,serif]">{{ $recitals['ar'][$i] ?? '' }}</p>
                        </div>
                    @else
                        <p class="mt-2 text-3xs leading-relaxed text-navy-700 first:mt-0">{{ $para }}</p>
                    @endif
                @endforeach
            </div>
        @endif

        {{-- clauses, exactly as they'll print --}}
        @forelse ($data['blocks'] ?? [] as $bi => $b)
            <div class="mb-4 break-inside-avoid">
                {{-- a letter reads as prose — no numbered clause headers --}}
                @unless ($isLetter)
                <div class="flex items-baseline justify-between gap-3 border-b border-navy-900 pb-1">
                    <p class="text-xs font-black"><span class="text-gold-600">{{ $bi + 1 }}.</span> {{ $b['title_en'] ?: 'Untitled clause' }}</p>
                    @if ($bilingual && ($b['title_ar'] ?? ''))
                        <p dir="rtl" class="text-xs font-bold text-navy-600 [font-family:Amiri,serif]">{{ $b['title_ar'] }}</p>
                    @endif
                </div>
                @endunless

                @if (($b['type'] ?? 'prose') === 'costshare')
                    <table class="mt-2 w-full text-3xs">
                        @foreach ($data['second_parties'] ?? [] as $sp)
                            <tr class="border-b border-line last:border-0">
                                <td class="py-1 font-semibold">{{ $sp['name_en'] ?: '—' }}</td>
                                <td class="py-1 text-right font-bold">{{ (float) ($sp['share'] ?? 0) }}%</td>
                                <td class="py-1 text-right text-muted">{{ $fmt($est * (float) ($sp['share'] ?? 0) / 100) }}</td>
                            </tr>
                        @endforeach
                    </table>
                @elseif (($b['type'] ?? 'prose') === 'schedule')
                    <table class="mt-2 w-full text-3xs">
                        @foreach ($f['payment_schedule'] ?? [] as $s)
                            <tr class="border-b border-line last:border-0">
                                <td class="py-1 font-bold">{{ (float) ($s['pct'] ?? 0) }}%</td>
                                <td class="py-1">{{ $s['when_en'] ?? '' }}</td>
                                <td class="py-1 text-right text-muted">{{ $fmt($est * (float) ($s['pct'] ?? 0) / 100) }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif

                @foreach ($b['en'] ?? [] as $p => $para)
                    @if ($bilingual)
                        <div class="mt-2 grid grid-cols-2 gap-3">
                            <p class="text-3xs leading-relaxed text-navy-700">{{ $para }}</p>
                            <p dir="rtl" class="text-3xs leading-relaxed text-navy-700 [font-family:Amiri,serif]">{{ $b['ar'][$p] ?? '' }}</p>
                        </div>
                    @else
                        <p class="mt-2 {{ $isLetter ? 'text-xs leading-loose' : 'text-3xs leading-relaxed' }} text-navy-700">{{ $para }}</p>
                    @endif
                @endforeach

                @if (in_array($b['type'] ?? '', ['bullets', 'list'], true))
                    <ul class="mt-2 space-y-0.5">
                        @foreach ($b['items'] ?? [] as $it)
                            <li class="flex items-baseline justify-between gap-3 text-3xs">
                                <span><span class="text-gold-600">◆</span> <b>{{ $it['l_en'] ?? '' }}</b>@if ($it['t_en'] ?? '') — {{ $it['t_en'] }}@endif</span>
                                @if ($bilingual && (($it['l_ar'] ?? '') !== ''))<span dir="rtl" class="text-navy-600 [font-family:Amiri,serif]">{{ $it['l_ar'] }}</span>@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @empty
            <p class="rounded-xl border border-dashed border-line px-4 py-10 text-center text-xs text-muted">
                The body is empty — add sections on the left and watch them appear here.
            </p>
        @endforelse

        {{-- signatures --}}
        @if ($signatories->isNotEmpty())
            <div class="mt-6 border-t-2 border-navy-900 pt-3 break-inside-avoid">
                <p class="text-3xs font-bold uppercase tracking-[0.16em] text-gold-700">{{ $isLetter ? 'Issued by' : 'Signatures' }}@unless ($isLetter) · <span class="[font-family:Amiri,serif]">التواقيع</span>@endunless</p>
                <div class="mt-2 grid gap-2" style="grid-template-columns: repeat({{ min(3, max(1, $signatories->count())) }}, minmax(0, 1fr));">
                    @foreach ($signatories as $s)
                        <div class="rounded-lg border {{ $s->isSigned() ? 'border-emerald-200' : 'border-line' }} border-t-2 border-t-navy-900 px-2.5 py-2">
                            <p class="text-[8px] font-bold uppercase tracking-wide text-gold-700">{{ $s->roleLabel() }}</p>
                            <p class="mt-0.5 truncate text-3xs font-bold">{{ $s->name ?: '—' }}</p>
                            @if ($s->isSigned())
                                <p class="pf mt-1.5 truncate text-sm italic text-navy-800">{{ $s->signature_data }}</p>
                                <p class="text-[8px] font-bold uppercase text-emerald-700">✓ {{ $s->signed_at->format('j M Y') }}</p>
                            @else
                                <div class="mt-5 border-t border-dashed border-navy-300 pt-0.5 text-[7px] uppercase tracking-wide text-muted">Signature &amp; date</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <p class="mt-5 border-t border-line pt-2 text-center font-mono text-[8px] text-muted">
            {{ $reference }} · {{ $bilingual ? 'EN / AR' : 'EN' }} · {{ \Illuminate\Support\Str::headline($status) }}
        </p>
    </div>

    {{-- the seal presses on when everyone has signed --}}
    @if ($fullySigned)
        <span class="absolute bottom-16 right-8 flex h-16 w-16 -rotate-12 items-center justify-center rounded-full text-lg font-bold text-[#5a4718] shadow-xl"
              style="background: radial-gradient(circle at 35% 30%, #E4C874, #C8A44A 55%, #9c7d2e); font-family: Georgia, serif;">EB</span>
    @endif
</div>
