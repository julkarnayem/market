@props(['href'=>null])
@if($href)
<a href="{{ $href }}" class="d-block">
    <div class="px-3 py-2 d-flex flex-column gap-1 border-bottom border-light d-sm-none">{{ $slot }}</div>
</a>
@else
<div class="px-3 py-2 d-flex flex-column gap-1 border-bottom border-light d-sm-none">{{ $slot }}</div>
@endif
