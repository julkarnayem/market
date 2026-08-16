@php
    $isSoldOut = $asset->isSoldOut();
    $isFeatured = $asset->isFeaturedNow();
@endphp
<article class="card overflow-hidden flex flex-col group {{ $isSoldOut ? 'opacity-75' : 'card-hover' }}">
    {{-- Image --}}
    <a href="{{ route('marketplace.show', $asset->slug) }}" class="d-block position-relative bg-gradient-to-br from-brand-50 to-mint-50 overflow-hidden">
        @if($asset->coverImage)
            <img src="{{ $asset->coverImage->url() }}" alt="{{ $asset->title }}"
                 class="w-100 h-100 object-fit-cover"
                 loading="lazy">
        @else
            <div class="w-100 h-100 d-grid place-items-center fs-1">{{ $asset->category->icon ?? '🧩' }}</div>
        @endif
        {{-- Badges --}}
        <div class="position-absolute d-flex flex-column gap-1">
            @if($isFeatured)<span class="badge-amber">⭐ Featured</span>@endif
            @if($isSoldOut)<span class="badge-slate">⊘ Sold Out</span>@endif
        </div>
        {{-- Favorite button --}}
        @auth
            <button
                onclick="event.preventDefault(); toggleFav({{ $asset->id }}, this)"
                data-favorited="{{ isset($isFavorited) && $isFavorited ? '1' : '0' }}"
                class="position-absolute h-8 w-8 d-grid place-items-center rounded-pill bg-white/90 shadow fs-5"
                aria-label="Toggle favorite">
                <span>{{ isset($isFavorited) && $isFavorited ? '★' : '☆' }}</span>
            </button>
        @else
            <a href="{{ route('login') }}"
               class="position-absolute h-8 w-8 d-grid place-items-center rounded-pill bg-white/90 shadow fs-5"
               title="Login to save">☆</a>
        @endauth
    </a>

    {{-- Body --}}
    <div class="p-3 d-flex flex-column flex-grow-1">
        <div class="d-flex align-items-center gap-1 mb-1 flex-wrap">
            <span class="badge-slate">{{ $asset->category->name }}</span>
            @if($asset->seller->isVerifiedSeller())
                <span class="badge-mint">✓ Verified</span>
            @endif
        </div>

        <a href="{{ route('marketplace.show', $asset->slug) }}" class="fw-semibold text-dark fs-sm flex-grow-1">
            {{ $asset->title }}
        </a>

        {{-- Quantity indicator --}}
        @if($asset->quantity > 1)
            <p class="fs-xs text-secondary mt-1">
                @if($isSoldOut)Sold out
                @else {{ $asset->available_quantity }} of {{ $asset->quantity }} available
                @endif
            </p>
        @endif

        <div class="mt-2 d-flex align-items-end justify-content-between">
            <x-money :amount="$asset->price" class="fs-5 fw-bold" :style="!isset($isSoldOut) || !$isSoldOut ? 'color:#10B981' : 'color:#9CA3AF'" />
            <div class="d-flex align-items-center gap-1 fs-xs text-muted">
                <a href="{{ route('profile.show', $asset->seller->username ?? $asset->seller->id) }}"
                   class="text-truncate max-w-[80px]">{{ $asset->seller->name }}</a>
            </div>
        </div>
    </div>
</article>

@once
@push('scripts')
<script>
async function toggleFav(assetId, btn) {
    const favorited = btn.dataset.favorited === '1';
    btn.disabled = true;
    try {
        const r = await fetch('{{ route("favorites.toggle") }}', {
            method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},
            body: JSON.stringify({asset_id:assetId})
        });
        const d = await r.json();
        btn.dataset.favorited = d.favorited ? '1':'0';
        btn.querySelector('span').textContent = d.favorited ? '★':'☆';
    } catch(e) { console.error(e); }
    btn.disabled = false;
}
</script>
@endpush
@endonce
