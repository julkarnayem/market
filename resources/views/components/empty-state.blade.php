@props(['title' => 'Nothing here yet', 'icon' => '📭'])
<div class="card-p text-center py-5">
    <div class="fs-2 mb-2">{{ $icon }}</div>
    <h3 class="fw-semibold text-dark">{{ $title }}</h3>
    @if (trim($slot))<p class="mt-1 fs-sm text-muted max-w-sm mx-auto">{{ $slot }}</p>@endif
</div>
