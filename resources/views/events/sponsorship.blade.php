@php
    // Accent per tier — used inline so it survives PDF/PNG rendering too.
    $accentFor = function (string $name) use ($theme) {
        return match (mb_strtolower(trim($name))) {
            'platinum' => ['#1E3352', '#ffffff'],
            'gold' => ['#D4AF37', '#1E3352'],
            'silver' => ['#94A3B8', '#ffffff'],
            'bronze' => ['#B45309', '#ffffff'],
            'media partner' => ['#0EA5E9', '#ffffff'],
            'strategic partner' => ['#6366F1', '#ffffff'],
            'airlines partner' => ['#2563EB', '#ffffff'],
            'tourism partner' => ['#14B8A6', '#ffffff'],
            default => [$theme['accent'], '#1E3352'],
        };
    };
    $topPrice = $packages->max('price_cents');
    $slug = \Illuminate\Support\Str::slug($event->name).'-sponsorship'.($single ? '-'.\Illuminate\Support\Str::slug($single->name) : '');
    $money = fn ($c) => $event->money($c);
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $event->name }} — Sponsorship {{ $single ? $single->name : 'Prospectus' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print { .no-print { display: none !important; } body { background: #fff !important; } }
    </style>
</head>
<body class="bg-navy-50 font-sans text-navy-900 antialiased">

    {{-- toolbar (screen only) --}}
    <div class="no-print sticky top-0 z-20 border-b border-line bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-[900px] items-center justify-between gap-3 px-5 py-3">
            <a href="{{ route('events.hub', [$event, 'tab' => 'sponsors']) }}" class="text-xs font-semibold text-navy-500 hover:text-navy-900">← Back to Sponsors</a>
            <div class="flex items-center gap-2">
                <button type="button" onclick="downloadPng()" class="flex h-9 items-center gap-1.5 rounded-xl border border-line bg-white px-3.5 text-xs font-bold text-navy-700 transition hover:border-gold-300">🖼 Download image</button>
                <a href="{{ route('events.sponsorship.pdf', $event) }}{{ $single ? '?package='.urlencode($single->name) : '' }}" class="flex h-9 items-center gap-1.5 rounded-xl bg-navy-900 px-3.5 text-xs font-bold text-white transition hover:bg-navy-800"><span class="text-gold-400">↧</span> Download PDF</a>
            </div>
        </div>
    </div>

    {{-- the document --}}
    <div class="mx-auto max-w-[900px] px-4 py-8">
        <div id="prospectus" class="overflow-hidden rounded-[26px] bg-white shadow-[0_20px_60px_rgba(11,31,58,0.12)]">

            {{-- hero --}}
            <div class="relative px-10 pb-9 pt-10 text-white" style="background: linear-gradient(135deg, {{ $theme['primary'] }} 0%, #14315a 100%);">
                <div class="pointer-events-none absolute right-0 top-0 h-40 w-40 rounded-full opacity-20" style="background: {{ $theme['accent'] }}; filter: blur(60px);"></div>
                <div class="flex items-center justify-between">
                    <span class="inline-flex items-center gap-2 text-[0.7rem] font-bold uppercase tracking-[0.2em]" style="color: {{ $theme['accent'] }};">◆ Elite Business Hub</span>
                    <span class="text-[0.66rem] font-semibold uppercase tracking-[0.16em] text-white/50">{{ $event->starts_at?->format('M j') }} – {{ $event->ends_at?->format('M j, Y') ?? $event->starts_at?->format('Y') }}</span>
                </div>
                <h1 class="mt-6 max-w-2xl text-3xl font-bold leading-tight text-balance">{{ $event->name }}</h1>
                <p class="mt-2 text-sm text-white/70">{{ $event->city }}@if ($event->city && $event->country), {{ $event->country }}@endif @if ($event->expected_participants) · {{ number_format($event->expected_participants) }} delegates @endif</p>
                <div class="mt-6 h-px w-full bg-white/15"></div>
                <p class="mt-5 text-xl font-bold" style="color: {{ $theme['accent'] }};">{{ $single ? $single->name.' — Partnership Opportunity' : 'Sponsorship & Partnership Prospectus' }}</p>
                <p class="mt-1 max-w-2xl text-[0.82rem] leading-relaxed text-white/70">Partner with us to reach a curated audience of decision-makers and leaders. Choose the package that fits your goals — each tier is built to maximise your visibility and impact.</p>
            </div>

            {{-- tiers --}}
            <div class="grid gap-5 px-8 py-9 {{ $single ? 'sm:grid-cols-1' : ($packages->count() >= 3 ? 'sm:grid-cols-2 lg:grid-cols-3' : 'sm:grid-cols-2') }}">
                @foreach ($packages as $p)
                    @php [$acc, $accText] = $accentFor($p->name); $featured = ! $single && $p->price_cents > 0 && $p->price_cents === $topPrice; @endphp
                    <div class="relative flex flex-col overflow-hidden rounded-2xl border {{ $featured ? 'border-transparent ring-2' : 'border-line' }}" @if ($featured) style="--tw-ring-color: {{ $acc }}" @endif>
                        @if ($featured)
                            <span class="absolute right-3 top-3 z-10 rounded-full px-2 py-0.5 text-[0.5rem] font-bold uppercase tracking-wide" style="background: {{ $acc }}; color: {{ $accText }};">Flagship</span>
                        @endif
                        {{-- header --}}
                        @php $sold = $soldByPackage[$p->name] ?? 0; $left = $p->slots !== null ? max(0, $p->slots - $sold) : null; @endphp
                        <div class="px-5 pb-4 pt-5" style="background: {{ $acc }}; color: {{ $accText }};">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-[0.66rem] font-bold uppercase tracking-[0.16em] opacity-80">Package</p>
                                @if ($p->slots !== null)
                                    <span class="rounded-full bg-white/20 px-2 py-0.5 text-[0.55rem] font-bold uppercase tracking-wide">{{ $left === 0 ? 'Sold out' : ($left === 1 ? 'Only 1 available' : $left.' available') }}</span>
                                @endif
                            </div>
                            <p class="mt-0.5 text-lg font-bold leading-tight">{{ $p->name }}</p>
                            <p class="mt-2 text-2xl font-bold">{{ $p->price_cents ? $money($p->price_cents) : 'On request' }}</p>
                            @if ($p->blurb)<p class="mt-1 text-[0.72rem] leading-snug opacity-85">{{ $p->blurb }}</p>@endif
                        </div>
                        {{-- benefits --}}
                        <div class="flex-1 px-5 py-4">
                            @if (! empty($p->benefits))
                                <ul class="space-y-2">
                                    @foreach ($p->benefits as $b)
                                        <li class="flex items-start gap-2 text-[0.78rem] leading-snug text-navy-800">
                                            <span class="mt-0.5 shrink-0 font-bold" style="color: {{ $acc === '#D4AF37' ? '#B8912E' : $acc }};">✓</span>
                                            <span>{{ $b }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-[0.72rem] italic text-muted">Benefits to be confirmed — add them from the Sponsors tab.</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- footer / CTA --}}
            <div class="border-t border-line bg-page/40 px-10 py-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold text-navy-900">Ready to partner with {{ $event->name }}?</p>
                        <p class="text-xs text-muted">Contact Elite Business Hub to confirm your package and secure your placement.</p>
                    </div>
                    <span class="rounded-xl px-4 py-2 text-xs font-bold" style="background: {{ $theme['primary'] }}; color: #fff;">Elite Business Hub</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        function downloadPng() {
            var node = document.getElementById('prospectus');
            if (!window.htmlToImage) { alert('Image renderer still loading — try again in a second.'); return; }
            window.htmlToImage.toPng(node, { pixelRatio: 2, backgroundColor: '#ffffff' })
                .then(function (url) {
                    var a = document.createElement('a');
                    a.href = url; a.download = '{{ $slug }}.png'; a.click();
                })
                .catch(function (e) { alert('Could not render image: ' + e); });
        }
    </script>
</body>
</html>
