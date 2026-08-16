@props(['items' => []])
<nav aria-label="Breadcrumb" class="breadcrumb mb-3">
    @foreach($items as $i => $item)
        @if($i > 0)<span class="breadcrumb-sep" aria-hidden="true">/</span>@endif
        @if(isset($item['url']) && $i < count($items)-1)
            <a href="{{ $item['url'] }}" class="">{{ $item['label'] }}</a>
        @else
            <span class="text-dark fw-medium">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
