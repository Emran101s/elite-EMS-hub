@props(['href' => null])
@php $tag = $href ? 'a' : 'button'; @endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @else type="button" @endif {{ $attributes->merge(['class' => 'o-chip']) }}>{{ $slot }}</{{ $tag }}>
