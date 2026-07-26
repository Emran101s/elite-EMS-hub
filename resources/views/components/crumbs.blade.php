@props([
    /**
     * The one breadcrumb every page shares. Items: [['label' =>, 'href' => |null], …];
     * the last item is the current page and never links. When a page passes
     * nothing, the layout derives "Command Canvas → {Title}".
     */
    'items' => [],
])

@if (count($items) > 1)
    <nav aria-label="Breadcrumb" class="mb-1.5">
        <ol class="flex flex-wrap items-center gap-1 text-[0.68rem] font-semibold">
            @foreach ($items as $item)
                <li class="flex min-w-0 items-center gap-1">
                    @if (! $loop->first)
                        <svg class="h-3 w-3 shrink-0 text-navy-300" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    @endif
                    @if (! $loop->last && ! empty($item['href']))
                        <a href="{{ $item['href'] }}" class="max-w-[14rem] truncate text-navy-500 transition hover:text-gold-700">{{ $item['label'] }}</a>
                    @else
                        <span @class(['max-w-[16rem] truncate', 'text-navy-900' => $loop->last, 'text-navy-500' => ! $loop->last]) @if ($loop->last) aria-current="page" @endif>{{ $item['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
