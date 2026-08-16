@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'type' => 'button'])
@php
    $variants = ['primary'=>'btn-primary','mint'=>'btn-mint','outline'=>'btn-outline','ghost'=>'btn-ghost','danger'=>'btn-danger'];
    $sizes = ['sm'=>'btn-sm','md'=>'','lg'=>'btn-lg'];
    $classes = ($variants[$variant] ?? 'btn-primary').' '.($sizes[$size] ?? '');
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
