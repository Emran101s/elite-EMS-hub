@props(['lines' => []])   {{-- e.g. ['FWP', '26'] --}}
<span {{ $attributes->merge(['class' => 'o-evtsq']) }}>
    @foreach ($lines as $line)<span>{{ $line }}</span>@endforeach
</span>
