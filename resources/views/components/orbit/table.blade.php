@props([
    // columns: ['Venue', ['label'=>'Cost','num'=>true], …]
    'columns' => [],
    // rows: [['St. Regis', ['v'=>'JD 12,400','num'=>true]], …] — or use the slot
    'rows' => [],
])
{{-- Money and counts are right-aligned via .num, which also switches them to the
     data face with tabular numerals so columns line up. --}}
<div style="overflow-x:auto">
    <table {{ $attributes->merge(['class' => 'o-table']) }}>
        @if ($columns)
            <thead><tr>
                @foreach ($columns as $col)
                    <th @class(['num' => is_array($col) && ($col['num'] ?? false)])>{{ is_array($col) ? ($col['label'] ?? '') : $col }}</th>
                @endforeach
            </tr></thead>
        @endif
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td @class(['num' => is_array($cell) && ($cell['num'] ?? false)])>{{ is_array($cell) ? ($cell['v'] ?? '') : $cell }}</td>
                    @endforeach
                </tr>
            @empty
                {{ $slot }}
            @endforelse
        </tbody>
    </table>
</div>
