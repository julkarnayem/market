<x-layouts.public :title="$page['title']">
    <div class="mx-auto max-w-3xl px-3 px-sm-4 px-lg-4 py-4">
        <a href="{{ url('/') }}" class="fs-sm text-primary">← Home</a>
        <h1 class="font-display fs-2 fw-bold text-dark mt-2">{{ $page['title'] }}</h1>
        <p class="fs-sm text-secondary mt-1">Last updated {{ now()->format('F Y') }}</p>
        <div class="prose prose-slate max-w-none mt-4">
            {{-- NOTE: $page['body'] is hardcoded in PageController::pages() — not user input. Safe to render. --}}
            {!! $page['body'] !!}
        </div>
    </div>
</x-layouts.public>
