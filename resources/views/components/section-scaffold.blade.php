@props(['title', 'note' => null, 'part' => 'a later release'])
<x-card>
    <div class="d-flex align-items-start gap-3">
        <span class="h-11 w-11 flex-shrink-0 d-grid place-items-center rounded-3 bg-primary bg-opacity-10 text-primary fs-4">🧩</span>
        <div>
            <h2 class="font-display fs-5 fw-bold text-dark">{{ $title }}</h2>
            <p class="fs-sm text-muted mt-1 max-w-lg">{{ $note ?? "This area is wired into the app and its data model. The interactive workflow ships in {$part}." }}</p>
            @if (trim($slot))<div class="mt-3">{{ $slot }}</div>@endif
        </div>
    </div>
</x-card>
