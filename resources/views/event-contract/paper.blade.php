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

    // The annexes, and the slug → number map that turns {{appendix:scope}} in
    // the clause text into "Appendix 1". Both surfaces read the same list, so
    // the preview cannot number an appendix differently from the export.
    // Who the Client actually is, and whether a percentage means anything.
    // One definition, read by the parties strip and by the cost-share table.
    $parties = \App\Support\ContractClauses::parties($data);
    $sharesApply = \App\Support\ContractClauses::sharesApply($data);

    $appendices = $appendices ?? [];
    $refs = [];
    foreach ($appendices as $i => $a) {
        $refs[$a['slug'] ?? ''] = (string) ($i + 1);
    }
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
                {{-- A share divides a cost between funders. One Client pays all
                     of it, so a percentage beside a single name says nothing —
                     and an unfilled row saying "0%" is simply wrong. Named
                     parties only, and shares only when there is a split. --}}
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @foreach ($parties as $sp)
                        <span class="rounded-full bg-navy-900 px-2.5 py-1 text-3xs font-bold text-white">
                            {{ $sp['name_en'] ?: ($sp['name_ar'] ?? '—') }}@if ($sharesApply) · <span class="text-gold-300">{{ (float) ($sp['share'] ?? 0) }}%</span>@endif
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
        @include('event-contract.blocks', [
            'blocks' => $data['blocks'] ?? [],
            'number' => fn ($i) => ($i + 1).'.',
        ])

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
                                <p class="text-[8px] font-bold uppercase text-emerald-700">✓ {{ $s->signed_at->format('j M Y · H:i') }}</p>
                                {{-- The fingerprint of the document as it stood when
                                     this signature was taken. Without it a signed PDF
                                     cannot be told from one edited afterwards, which
                                     is the whole point of recording the hash. --}}
                                @if ($s->signed_hash)
                                    <p class="mt-0.5 font-mono text-[6.5px] text-muted">verify {{ substr($s->signed_hash, 0, 12) }}</p>
                                @endif
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

        {{-- ══════════ THE ANNEXES ══════════
             Each starts on its own page and ends in an initials line, which is
             what stops an appendix being swapped for a different one after the
             agreement has been signed. Sections are numbered within the
             appendix (1.1, 1.2) so a reader can cite them. --}}
        @foreach ($appendices as $ai => $ax)
            @php $an = $ai + 1; @endphp
            <section class="mt-8 border-t-2 border-navy-900 pt-5" style="break-before: page">
                <div class="flex items-baseline justify-between gap-3">
                    <div>
                        <p class="text-3xs font-bold uppercase tracking-[0.22em] text-gold-700">Appendix {{ $an }}</p>
                        <p class="pf mt-0.5 text-base font-black text-navy-900">{{ $ax['title_en'] ?: 'Untitled appendix' }}</p>
                    </div>
                    @if ($bilingual && ($ax['title_ar'] ?? ''))
                        <div dir="rtl" class="text-right">
                            <p class="text-3xs font-bold tracking-[0.1em] text-gold-700 [font-family:Amiri,serif]">الملحق {{ $an }}</p>
                            <p class="mt-0.5 text-sm font-bold text-navy-900 [font-family:Amiri,serif]">{{ $ax['title_ar'] }}</p>
                        </div>
                    @endif
                </div>

                <p class="mt-1 font-mono text-[8px] text-muted">
                    This appendix forms an integral part of {{ $reference }}.
                </p>

                <div class="mt-4">
                    @include('event-contract.blocks', [
                        'blocks' => $ax['blocks'] ?? [],
                        'number' => fn ($i) => $an.'.'.($i + 1),
                        'empty' => 'This appendix is empty — fill it in, or pull it from the module that owns it.',
                    ])
                </div>

                <div class="mt-5 flex items-end justify-between gap-6 border-t border-line pt-3 break-inside-avoid">
                    <p class="text-3xs text-muted">Initialled for and on behalf of the Parties</p>
                    <div class="flex gap-6">
                        @foreach (['Contractor', 'Client'] as $side)
                            <span class="w-24 border-t border-dashed border-navy-300 pt-0.5 text-center text-[7px] uppercase tracking-wide text-muted">{{ $side }}</span>
                        @endforeach
                    </div>
                </div>
            </section>
        @endforeach
    </div>

    {{-- the seal presses on when everyone has signed --}}
    @if ($fullySigned)
        <span class="absolute bottom-16 right-8 flex h-16 w-16 -rotate-12 items-center justify-center rounded-full text-lg font-bold text-[#5a4718] shadow-xl"
              style="background: radial-gradient(circle at 35% 30%, #E4C874, #C8A44A 55%, #9c7d2e); font-family: Georgia, serif;">EB</span>
    @endif
</div>
