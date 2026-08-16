<x-layouts.public :title="$category->name">
    <div class="mx-auto max-w-7xl px-3 px-sm-4 px-lg-4 py-4">
        <nav class="fs-sm text-muted mb-2">
            <a href="{{ route('marketplace.index') }}" class="">Marketplace</a>
            <span class="mx-1">/</span>
            @if ($category->parent)<a href="{{ route('marketplace.category', $category->parent->slug) }}" class="">{{ $category->parent->name }}</a><span class="mx-1">/</span>@endif
            <span class="text-dark">{{ $category->name }}</span>
        </nav>
        <h1 class="font-display fs-3 fw-bold text-dark mb-1">{{ $category->name }}</h1>
        <p class="fs-sm text-muted mb-3">{{ $assets->total() }} assets</p>

        @if ($category->children->isNotEmpty())
            <div class="d-flex flex-wrap gap-2 mb-4">
                @foreach ($category->children as $child)
                    <a href="{{ route('marketplace.category', $child->slug) }}" class="badge-slate">{{ $child->name }}</a>
                @endforeach
            </div>
        @endif

        @if ($assets->isEmpty())
            <x-empty-state title="No assets in this category yet" icon="🗂️">Be the first to list one.</x-empty-state>
        @else
            <div class="row row-cols-1 row-cols-2 row-cols-3 gap-3">
                @foreach ($assets as $asset)
                    @include('marketplace.partials.asset-card', ['asset' => $asset])
                @endforeach
            </div>
            <div class="mt-4">{{ $assets->withQueryString()->links() }}</div>
        @endif
    </div>
</x-layouts.public>
