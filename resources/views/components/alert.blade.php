@props(['type' => 'info'])
@php
    $tones = [
        'success'=>'bg-mint-50 text-mint-800 ring-mint-600/20',
        'error'=>'bg-rose-50 text-rose-800 ring-rose-600/20',
        'warning'=>'bg-amber-50 text-amber-800 ring-amber-600/20',
        'info'=>'bg-brand-50 text-brand-800 ring-brand-600/20',
    ];
@endphp
<div {{ $attributes->merge(['class' => 'rounded-xl px-4 py-3 text-sm ring-1 '.($tones[$type] ?? $tones['info'])]) }}>{{ $slot }}</div>
