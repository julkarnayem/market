@props(['padded' => true])
<div {{ $attributes->merge(['class' => $padded ? 'card-p' : 'card']) }}>{{ $slot }}</div>
