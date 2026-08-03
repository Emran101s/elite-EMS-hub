{{--
    One block, rendered. The body's clauses and an appendix's sections are the
    same shape, so they print through the same partial — an appendix that looked
    different from the agreement it is bound into would read as a different
    document.

    Expects: $blocks, $number (a closure: index → the label before the title),
             $bilingual, $isLetter, $fmt, $est, $f, $data, $refs (slug => number),
             and optionally $empty (the message when there is nothing).
--}}
@php
    // Resolves {{value}} to the current Value & Payments figure, then
    // {{appendix:slug}} to its number — in that order, since a token that
    // named an appendix could never also name the value.
    $ref = fn (string $s, string $lang = 'en') => \App\Support\ContractAppendices::resolve(
        \App\Support\ContractClauses::resolveValue($s, $data, $lang), $refs ?? [], $lang);

    // A cost-share clause with nothing to share is a heading over an empty
    // table. Once the Client is a single entity the article has no subject, so
    // it leaves the document rather than printing "Deep Root · 100%".
    $sharesApply = \App\Support\ContractClauses::sharesApply($data);
    $blocks = array_values(array_filter(
        $blocks,
        fn ($b) => ($b['type'] ?? '') !== 'costshare' || $sharesApply,
    ));
@endphp

@forelse ($blocks as $bi => $b)
    @php
        // Keeping a clause whole is right for a short one and wrong for a long
        // one: an eighteen-bullet scope that refuses to split cannot fit beside
        // a preamble, so it jumps to the next page and leaves half of the first
        // one blank. Long clauses flow; their heading stays with what follows.
        $tall = count($b['items'] ?? []) > 6 || count($b['en'] ?? []) > 3;
    @endphp
    <div @class(['mb-4', 'break-inside-avoid' => ! $tall])>
        {{-- a letter reads as prose — no numbered clause headers --}}
        @unless ($isLetter)
            <div class="flex items-baseline justify-between gap-3 border-b border-navy-900 pb-1" style="break-after: avoid">
                <p class="text-xs font-black"><span class="text-gold-600">{{ $number($bi) }}</span> {{ $b['title_en'] ?: 'Untitled clause' }}</p>
                @if ($bilingual && ($b['title_ar'] ?? ''))
                    <p dir="rtl" class="text-xs font-bold text-navy-600 [font-family:Amiri,serif]">{{ $b['title_ar'] }}</p>
                @endif
            </div>
        @endunless

        @if (($b['type'] ?? 'prose') === 'costshare')
            <table class="mt-2 w-full text-3xs">
                @foreach (\App\Support\ContractClauses::parties($data) as $sp)
                    <tr class="border-b border-line last:border-0">
                        <td class="py-1 font-semibold">{{ $sp['name_en'] ?: ($sp['name_ar'] ?? '—') }}</td>
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
                    <p class="text-3xs leading-relaxed text-navy-700">{{ $ref($para) }}</p>
                    <p dir="rtl" class="text-3xs leading-relaxed text-navy-700 [font-family:Amiri,serif]">{{ $ref($b['ar'][$p] ?? '', 'ar') }}</p>
                </div>
            @else
                <p class="mt-2 {{ $isLetter ? 'text-xs leading-loose' : 'text-3xs leading-relaxed' }} text-navy-700">{{ $ref($para) }}</p>
            @endif
        @endforeach

        @if (in_array($b['type'] ?? '', ['bullets', 'list'], true))
            <ul class="mt-2 space-y-0.5">
                @foreach ($b['items'] ?? [] as $it)
                    <li class="flex items-baseline justify-between gap-3 text-3xs">
                        <span><span class="text-gold-600">◆</span> <b>{{ $ref($it['l_en'] ?? '') }}</b>@if ($it['t_en'] ?? '') — {{ $ref($it['t_en']) }}@endif</span>
                        @if ($bilingual && (($it['l_ar'] ?? '') !== ''))<span dir="rtl" class="text-navy-600 [font-family:Amiri,serif]">{{ $ref($it['l_ar'], 'ar') }}</span>@endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@empty
    <p class="rounded-xl border border-dashed border-line px-4 py-10 text-center text-xs text-muted">
        {{ $empty ?? 'The body is empty — add sections on the left and watch them appear here.' }}
    </p>
@endforelse
