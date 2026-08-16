@props(['amount' => 0, 'symbol' => true, 'muted' => false])
{{-- $amount is integer poisha --}}
<span {{ $attributes->merge(['class' => 'money '.($muted ? 'text-slate-500' : '')]) }}>{{ \App\Support\Money::format((int) $amount, $symbol) }}</span>
