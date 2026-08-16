@props(['lines'=>3,'class'=>''])
<div {{ $attributes->merge(['class'=>'animate-pulse space-y-3 '.$class]) }} aria-label="Loading…" role="status">
    @for($i=0;$i<$lines;$i++)
        <div class="h-4 bg-slate-200 rounded-lg {{ $i===0?'w-3/4':($i===1?'w-full':'w-2/3') }}"></div>
    @endfor
</div>
