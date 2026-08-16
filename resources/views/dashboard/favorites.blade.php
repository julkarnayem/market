<x-layouts.dashboard title="Favorites" heading="Saved Assets">
    @if($favorites->isEmpty())
        <x-empty-state icon="★" title="No saved assets">
            Tap the ★ on any listing to save it here for later.
            <x-slot:slot><x-button :href="route('marketplace.index')" variant="outline" class="mt-3">Browse marketplace</x-button></x-slot:slot>
        </x-empty-state>
    @else
        <div class="row row-cols-1 row-cols-2 row-cols-3 gap-3">
            @foreach($favorites as $fav)
                @php $asset=$fav->asset; @endphp
                <div class="card overflow-hidden d-flex flex-column">
                    <a href="{{ route('marketplace.show',$asset->slug) }}" class="d-block bg-gradient-to-br from-brand-50 to-mint-50 d-grid place-items-center fs-2">{{ $asset->category->icon??'🧩' }}</a>
                    <div class="p-3 d-flex flex-column flex-grow-1">
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <a href="{{ route('marketplace.show',$asset->slug) }}" class="fw-semibold text-dark">{{ $asset->title }}</a>
                            <form method="POST" action="{{ route('dashboard.favorites.remove',$fav) }}" class="flex-shrink-0">
                                @csrf @method('DELETE')
                                <button class="btn-ghost btn-sm text-danger" title="Remove">✕</button>
                            </form>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-2">
                            <x-money :amount="$asset->price" class="fw-bold text-dark" />
                            <x-status-badge :status="$asset->status->value" />
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.dashboard>
