@props(['label','value','icon'=>null,'tone'=>'default','href'=>null,'delta'=>null])
@php $tones=['default'=>'text-slate-900','mint'=>'text-mint-600','amber'=>'text-amber-600','rose'=>'text-rose-600','brand'=>'text-brand-600']; @endphp
<div {{ $attributes->merge(['class'=>'stat-card']) }}>
    <div class="d-flex align-items-center justify-content-between">
        <p class="stat-label">{{ $label }}</p>
        @if($icon)<span class="fs-4">{{ $icon }}</span>@endif
    </div>
    <p class="stat-value {{ $tones[$tone]??$tones['default'] }}">{{ $value }}</p>
    @if($delta)<p class="stat-delta text-muted">{{ $delta }}</p>@endif
    @if($href)<a href="{{ $href }}" class="fs-xs text-primary mt-1 d-inline-block">View all →</a>@endif
</div>
