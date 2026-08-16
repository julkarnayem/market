<x-layouts.public :title="$asset->title">
<x-slot:head>
    <meta name="description" content="{{ Str::limit(strip_tags($asset->description), 160) }}">
    <meta property="og:title" content="{{ $asset->title }}">
    <meta property="og:description" content="{{ Str::limit(strip_tags($asset->description), 160) }}">
    @if($asset->coverImage)<meta property="og:image" content="{{ $asset->coverImage->url() }}">@endif
    <link rel="canonical" href="{{ route('marketplace.show', $asset->slug) }}">
</x-slot:head>

<div class="mx-auto max-w-7xl px-3 px-sm-4 px-lg-4 py-4">
    {{-- Breadcrumb --}}
    <nav class="breadcrumb mb-3">
        <a href="{{ route('marketplace.index') }}" class="">Marketplace</a>
        <span class="breadcrumb-sep">/</span>
        <a href="{{ route('marketplace.index',['category'=>$asset->category->parent?->slug ?? $asset->category->slug]) }}" class="">{{ $asset->category->parent?->name ?? $asset->category->name }}</a>
        @if($asset->category->parent)
            <span class="breadcrumb-sep">/</span>
            <a href="{{ route('marketplace.index',['subcategory'=>$asset->category->slug]) }}" class="">{{ $asset->category->name }}</a>
        @endif
        <span class="breadcrumb-sep">/</span>
        <span class="text-dark text-truncate max-w-[200px]">{{ $asset->title }}</span>
    </nav>

    <div class="grid-cols-[1fr_22rem] gap-lg-5 align-items-start">
        {{-- Left: Gallery + Details --}}
        <div class="vstack gap-3" x-data="{
            active: 0,
            images: {{ $asset->images->map(fn($i)=>['url'=>$i->url()])->toJson() }},
        }">

            {{-- Gallery --}}
            <div class="card overflow-hidden">
                {{-- Main image --}}
                <div class="bg-light position-relative">
                    @if($asset->images->isNotEmpty())
                        <template x-for="(img,i) in images" :key="i">
                            <img :src="img.url" x-show="active===i"
                                 class="w-100 h-100 object-fit-cover"
                                 :alt="'{{ $asset->title }} image '+(i+1)"
                                 loading="lazy">
                        </template>
                        @if($asset->images->count() > 1)
                            <button @click="active=(active-1+images.length)%images.length"
                                    class="position-absolute h-9 w-9 d-grid place-items-center bg-white/90 rounded-pill shadow text-dark"
                                    aria-label="Previous image">‹</a>
                            <button @click="active=(active+1)%images.length"
                                    class="position-absolute h-9 w-9 d-grid place-items-center bg-white/90 rounded-pill shadow text-dark"
                                    aria-label="Next image">›</a>
                        @endif
                    @else
                        <div class="w-100 h-100 d-grid place-items-center text-6xl">{{ $asset->category->icon ?? '🧩' }}</div>
                    @endif
                    {{-- Badges --}}
                    <div class="position-absolute d-flex gap-2 flex-wrap">
                        @if($asset->isFeaturedNow())<span class="badge-amber">⭐ Featured</span>@endif
                        @if($asset->isSoldOut())<span class="badge-slate">⊘ Sold Out</span>@endif
                        @if($asset->seller->isVerifiedSeller())<span class="badge-mint">✓ Verified Seller</span>@endif
                    </div>
                </div>
                {{-- Thumbnails --}}
                @if($asset->images->count() > 1)
                    <div class="d-flex gap-2 p-2 overflow-x-auto">
                        @foreach($asset->images as $i => $img)
                            <button @click="active={{ $i }}"
                                    class="flex-shrink-0 h-16 w-16 rounded-3 overflow-hidden"
                                    :class="active==={{ $i }} ? 'ring-brand-500' : 'ring-transparent hover:ring-slate-300'"
                                    aria-label="View image {{ $i+1 }}">
                                <img src="{{ $img->url() }}" class="h-100 w-100 object-fit-cover" loading="lazy">
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Description + Attributes --}}
            <x-card>
                <h2 class="section-title mb-2">About this asset</h2>
                <div class="prose prose-sm prose-slate max-w-none text-dark">{{ $asset->description }}</div>
            </x-card>

            @if($asset->attributeValues->isNotEmpty())
            <x-card>
                <h2 class="section-title mb-3">Asset Details</h2>
                <dl class="row row-cols-2 row-cols-3 gap-3">
                    @foreach($asset->attributeValues as $av)
                        <div class="rounded-3 bg-light p-2">
                            <dt class="fs-xs text-muted mb-1">{{ $av->attribute?->label }}</dt>
                            <dd class="fs-sm fw-semibold text-dark">
                                {{ $av->value }}
                                @if($av->attribute?->unit)<span class="fs-xs fw-normal text-muted">{{ $av->attribute->unit }}</span>@endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>
            @endif

            {{-- Seller card --}}
            <x-card>
                <h2 class="section-title mb-3">Seller</h2>
                <div class="d-flex align-items-center gap-3">
                    <span class="h-14 w-14 flex-shrink-0 d-grid place-items-center rounded-4 bg-primary bg-opacity-25 text-primary fs-3 fw-bold">{{ strtoupper(substr($asset->seller->name,0,1)) }}</span>
                    <div class="">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <a href="{{ route('profile.show', $asset->seller->username ?? $asset->seller->id) }}" class="fw-semibold text-dark">{{ $asset->seller->name }}</a>
                            @if($asset->seller->isVerifiedSeller())<span class="badge-mint">✓ Verified</span>@endif
                        </div>
                        <p class="fs-xs text-muted mt-1">Member since {{ $asset->seller->created_at->format('M Y') }}</p>
                        @if($asset->seller->bio)<p class="fs-sm text-muted mt-1">{{ Str::limit($asset->seller->bio, 100) }}</p>@endif
                    </div>
                    <a href="{{ route('profile.show', $asset->seller->username ?? $asset->seller->id) }}" class="btn-outline btn-sm flex-shrink-0 ms-auto">View profile</a>
                </div>
            </x-card>

            {{-- Buyer protection info --}}
            <x-card>
                <h2 class="section-title mb-2">Buyer Protection</h2>
                <ul class="vstack gap-2 fs-sm text-muted">
                    <li class="d-flex gap-2"><span class="text-success flex-shrink-0">✓</span>72-hour protection window after payment</li>
                    <li class="d-flex gap-2"><span class="text-success flex-shrink-0">✓</span>Verified seller required to list</li>
                    <li class="d-flex gap-2"><span class="text-success flex-shrink-0">✓</span>Dispute resolution available</li>
                    <li class="d-flex gap-2"><span class="text-success flex-shrink-0">✓</span>Funds held until delivery confirmed</li>
                </ul>
                <a href="{{ route('legal','buyer-protection') }}" class="fs-xs text-primary mt-2 d-inline-block">Read buyer protection policy →</a>
            </x-card>

            {{-- Related --}}
            @if($related->isNotEmpty())
            <div>
                <h2 class="section-title mb-3">Similar listings</h2>
                <div class="row row-cols-2 row-cols-3 gap-3">
                    @foreach($related as $rel)
                        <a href="{{ route('marketplace.show',$rel->slug) }}" class="card overflow-hidden group">
                            <div class="bg-gradient-to-br from-brand-50 to-mint-50 overflow-hidden">
                                @if($rel->coverImage)<img src="{{ $rel->coverImage->url() }}" class="w-100 h-100 object-fit-cover" loading="lazy">
                                @else<div class="w-100 h-100 d-grid place-items-center fs-3">{{ $rel->category->icon ?? '🧩' }}</div>@endif
                            </div>
                            <div class="p-2">
                                <p class="fs-xs fw-semibold text-dark">{{ $rel->title }}</p>
                                <x-money :amount="$rel->price" class="fs-sm fw-bold text-dark d-block mt-1" />
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Right: Purchase/Offer panel --}}
        <aside class="mt-4 mt-lg-0">
            <div class="card-p position-sticky vstack gap-3">
                <div>
                    <p class="fs-xs text-muted mb-1">Price</p>
                    <x-money :amount="$asset->price" class="fs-2 fw-bold text-dark d-block" />
                    @if($asset->quantity > 1)
                        <p class="fs-xs text-muted mt-1">
                            @if($asset->isSoldOut())<span class="text-danger fw-medium">Sold out</span>
                            @else{{ $asset->available_quantity }} of {{ $asset->quantity }} available
                            @endif
                        </p>
                    @endif
                </div>

                {{-- Buyer protection badge --}}
                <div class="d-flex align-items-center gap-2 rounded-3 bg-success bg-opacity-10 px-2 py-2 fs-xs text-success">
                    <span>🛡</span><span>72-hour buyer protection on every order.</span>
                </div>

                @auth
                    @if(auth()->id() === $asset->user_id)
                        {{-- Own listing --}}
                        <div class="rounded-3 bg-light px-3 py-2 text-center fs-sm text-muted">
                            <p class="fw-medium">This is your listing.</p>
                            <a href="{{ route('dashboard.listings.show', $asset) }}" class="text-primary fs-xs">Manage listing →</a>
                        </div>
                    @elseif($asset->isSoldOut())
                        <button disabled class="btn-outline w-100">Sold Out</a>
                    @elseif(!$asset->isAvailableForPurchase())
                        <button disabled class="btn-outline w-100">Not available</a>
                    @else
                        {{-- Buy Now --}}
                        <a href="{{ route('checkout.show', $asset->slug) }}" class="btn-primary w-100 text-center">
                            Buy Now — {{ \App\Support\Money::format($asset->price) }} →
                        </a>

                        {{-- Make Offer --}}
                        @if($userActiveOffer)
                            <div class="rounded-3 bg-warning bg-opacity-10 p-2 fs-sm">
                                <p class="fw-semibold text-warning">Your offer is pending</p>
                                <p class="text-warning mt-1">Amount: <x-money :amount="$userActiveOffer->amount" /></p>
                                <p class="fs-xs text-warning mt-1" x-data="{ secs: {{ $userActiveOffer->timeRemainingSeconds() }} }" x-init="setInterval(()=>{ if(secs>0)secs-- },1000)"
                                   x-text="'Expires in: '+Math.floor(secs/3600).toString().padStart(2,'0')+':'+Math.floor((secs%3600)/60).toString().padStart(2,'0')+':'+(secs%60).toString().padStart(2,'0')">
                                </p>
                            </div>
                        @else
                            <a href="{{ route('offers.create', ['asset'=>$asset->slug]) }}" class="btn-outline w-100">Make an offer</a>
                        @endif
                    @endif
                @else
                    <a href="{{ route('login') }}" class="btn-primary w-100">Log in to buy</a>
                    <a href="{{ route('offers.create', ['asset'=>$asset->slug]) }}" class="btn-outline w-100">Log in to make offer</a>
                @endauth

                {{-- Favorite --}}
                @auth
                    <form method="POST" action="{{ route('favorites.toggle') }}">
                        @csrf
                        <input type="hidden" name="asset_id" value="{{ $asset->id }}">
                        <button type="submit" class="btn-ghost w-100">
                            {{ $isFavorited ? '★ Saved to favorites' : '☆ Save to favorites' }}
                        </a>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn-ghost w-100">☆ Save to favorites</a>
                @endauth

                {{-- Meta --}}
                <dl class="fs-xs text-muted vstack gap-1 pt-2 border-top border-light">
                    <div class="d-flex justify-content-between"><dt>Category</dt><dd>{{ $asset->category->name }}</dd></div>
                    <div class="d-flex justify-content-between"><dt>Listed</dt><dd>{{ $asset->created_at->format('d M Y') }}</dd></div>
                    <div class="d-flex justify-content-between"><dt>Views</dt><dd>{{ number_format($asset->views_count) }}</dd></div>
                    <div class="d-flex justify-content-between"><dt>Saved by</dt><dd>{{ number_format($asset->favorites_count) }}</dd></div>
                </dl>
            </div>
        </aside>
    </div>
</div>
</x-layouts.public>
