@props([
    'headers' => [],
])

{{-- Soft Command data table shell. Pass rows via default slot (tr markup). --}}
<div {{ $attributes->class(['eo-table-wrap']) }}>
    @isset($toolbar)
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-eo-line px-4 py-3">
            {{ $toolbar }}
        </div>
    @endisset

    <div class="overflow-x-auto">
        <table class="eo-table">
            @if (count($headers))
                <thead>
                    <tr>
                        @foreach ($headers as $header)
                            <th>{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
            @elseif (isset($head))
                <thead>
                    {{ $head }}
                </thead>
            @endif
            <tbody>
                {{ $slot }}
            </tbody>
        </table>
    </div>

    @isset($footer)
        <div class="border-t border-eo-line px-4 py-3">
            {{ $footer }}
        </div>
    @endisset
</div>
