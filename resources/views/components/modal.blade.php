@props(['name','title'=>null,'size'=>'md'])
@php $sizes=['sm'=>'max-w-md','md'=>'max-w-lg','lg'=>'max-w-2xl','xl'=>'max-w-4xl']; @endphp
<div x-show="$store.modals.active==='{{ $name }}'" x-cloak
     class="position-fixed top-0 start-0 end-0 bottom-0 d-flex align-items-end align-sm-items-center justify-content-center p-3"
     x-transition:enter="duration-200" x-transition:leave="duration-150"
     @keydown.escape.window="$store.modals.active=null">
    <div class="position-absolute top-0 start-0 end-0 bottom-0 bg-slate-900/50" @click="$store.modals.active=null"></div>
    <div class="relative w-full {{ $sizes[$size]??$sizes['md'] }} card shadow-pop">
        @if($title)
        <div class="d-flex align-items-center justify-content-between p-3 border-bottom border-light">
            <h3 class="font-display fw-semibold text-dark">{{ $title }}</h3>
            <button @click="$store.modals.active=null" class="btn-ghost btn-icon" aria-label="Close">✕</button>
        </div>
        @endif
        <div class="p-3">{{ $slot }}</div>
    </div>
</div>
