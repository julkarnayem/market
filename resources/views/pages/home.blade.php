<x-layouts.public
    :title="config('app.name').' — Buy &amp; Sell Digital Assets in Bangladesh'"
    description="Bangladesh's trusted marketplace for digital assets — social accounts, websites, domains and software. Verified sellers, 72h buyer protection, BDT payouts."
>

<style>
/* Homepage custom styles */
.em-green { color: #10B981; }
.bg-em-green { background: #10B981; }
.bg-em-green-dark { background: #059669; }
.bg-em-50 { background: #ECFDF5; }
.border-em { border-color: #10B981; }
.ring-em { box-shadow: 0 0 0 3px rgba(16,185,129,.2); }

.btn-em {
    display:inline-flex;align-items:center;justify-content:center;gap:.375rem;
    padding:.625rem 1.25rem;border-radius:.625rem;font-size:.875rem;font-weight:600;
    background:#10B981;color:#fff;transition:all .15s;border:none;cursor:pointer;
    text-decoration:none;
}
.btn-em:hover { background:#059669; }
.btn-em-lg { padding:.75rem 1.75rem;font-size:1rem;border-radius:.75rem; }
.btn-outline-dark {
    display:inline-flex;align-items:center;gap:.375rem;
    padding:.625rem 1.25rem;border-radius:.625rem;font-size:.875rem;font-weight:600;
    background:transparent;color:#374151;border:1.5px solid #E5E7EB;
    transition:all .15s;text-decoration:none;
}
.btn-outline-dark:hover { background:#F9FAFB;border-color:#D1D5DB; }
.btn-outline-dark-lg { padding:.75rem 1.75rem;font-size:1rem;border-radius:.75rem; }

.listing-card {
    background:#fff;border:1px solid #E5E7EB;border-radius:1rem;overflow:hidden;
    display:flex;flex-direction:column;transition:all .2s;
}
.listing-card:hover { border-color:#10B981;box-shadow:0 8px 24px rgba(0,0,0,.08); }
.listing-card:hover .card-img img { transform:scale(1.04); }

.card-img { aspect-ratio:16/10;overflow:hidden;position:relative;background:#F0FDF4; }
.card-img img { width:100%;height:100%;object-fit:cover;transition:transform .3s; }
.card-img-placeholder { width:100%;height:100%;display:grid;place-items:center;font-size:2rem;background:linear-gradient(135deg,#ECFDF5,#D1FAE5); }

.badge-green { display:inline-flex;align-items:center;gap:.25rem;padding:.25rem .625rem;border-radius:9999px;font-size:.6875rem;font-weight:600;background:#ECFDF5;color:#065F46; }
.badge-gray  { display:inline-flex;align-items:center;gap:.25rem;padding:.25rem .625rem;border-radius:9999px;font-size:.6875rem;font-weight:600;background:#F3F4F6;color:#6B7280; }
.badge-amber { display:inline-flex;align-items:center;gap:.25rem;padding:.25rem .625rem;border-radius:9999px;font-size:.6875rem;font-weight:600;background:#FFFBEB;color:#92400E; }
.badge-sold  { display:inline-flex;align-items:center;gap:.25rem;padding:.25rem .625rem;border-radius:9999px;font-size:.6875rem;font-weight:600;background:#F3F4F6;color:#9CA3AF; }

.trust-item { display:flex;flex-direction:column;align-items:center;text-align:center;gap:.5rem;padding:1.25rem; }
.trust-icon { width:2.5rem;height:2.5rem;display:grid;place-items:center;border-radius:.75rem;background:#ECFDF5; }
.trust-icon svg { width:1.25rem;height:1.25rem;color:#10B981; }

.category-card {
    background:#fff;border:1px solid #E5E7EB;border-radius:.875rem;
    padding:1.25rem;display:flex;flex-direction:column;align-items:center;text-align:center;
    gap:.5rem;transition:all .2s;text-decoration:none;
}
.category-card:hover { border-color:#10B981;background:#ECFDF5;transform:translateY(-2px);box-shadow:0 6px 20px rgba(16,185,129,.12); }
.category-card:hover .cat-name { color:#059669; }
.cat-icon { font-size:1.75rem;line-height:1; }
.cat-name { font-size:.875rem;font-weight:600;color:#111827;transition:color .15s; }
.cat-count { font-size:.75rem;color:#9CA3AF; }

.section-header { display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:1.5rem; }
.section-title { font-size:1.5rem;font-weight:700;color:#111827;letter-spacing:-.02em; }
.section-sub { font-size:.9375rem;color:#6B7280;margin-top:.25rem; }
.view-all { font-size:.875rem;font-weight:600;color:#10B981;text-decoration:none;transition:color .15s;white-space:nowrap; }
.view-all:hover { color:#059669; }

.step-num { font-size:2rem;font-weight:800;line-height:1;color:#10B981;font-family:ui-monospace,monospace; }
.step-card { background:#fff;border:1px solid #E5E7EB;border-radius:1rem;padding:1.5rem;position:relative; }

.price-text { font-family:'JetBrains Mono',ui-monospace,monospace;font-weight:700;color:#111827;font-size:1.0625rem; }

@media(max-width:640px) {
    .section-title { font-size:1.25rem; }
}
</style>

{{-- ═══════════════════════════════════════════════════ --}}
{{-- HERO                                                --}}
{{-- ═══════════════════════════════════════════════════ --}}
<section class="bg-white border-bottom" style="background:linear-gradient(135deg,#fff 0%,#F0FDF9 100%)">
    <div class="mx-auto max-w-7xl px-3 px-sm-4 px-lg-4 py-5 py-sm-5">
        <div class="max-w-3xl mx-auto text-center">

            {{-- Trust badge --}}
            <div class="d-inline-flex align-items-center gap-2 px-3 py-2 rounded-pill fs-sm fw-medium mb-4 border" style="background:#ECFDF5;border-color:#A7F3D0;color:#065F46">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color:#10B981"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Secure Digital Asset Marketplace
            </div>

            {{-- Headline --}}
            <h1 class="font-display fw-bold text-dark mb-3" style="font-size:clamp(2rem,5vw,3.5rem)">
                Buy &amp; Sell<br>
                <span style="color:#10B981">Digital Assets</span><br>
                With Confidence
            </h1>

            <p class="text-muted mb-4 mx-auto" style="font-size:1.0625rem;max-width:540px">
                Social pages, websites, domains and software — from verified sellers, with secure escrow-style payouts in <strong class="fw-semibold text-dark">৳ BDT</strong>.
            </p>

            {{-- CTA buttons --}}
            <div class="d-flex flex-wrap align-items-center justify-content-center gap-3 mb-4">
                <a href="{{ route('marketplace.index') }}" class="btn-em btn-em-lg">
                    Browse Marketplace
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
                <a href="{{ route('register') }}" class="btn-outline-dark btn-outline-dark-lg">
                    Start Selling
                </a>
            </div>

            {{-- SEARCH BAR --}}
            <form action="{{ route('marketplace.index') }}" method="GET" class="position-relative max-w-2xl mx-auto">
                <div class="d-flex align-items-center bg-white border border-2 border-secondary border-opacity-25 rounded-4 shadow-lg overflow-hidden" style="transition:border-color .15s,box-shadow .15s">
                    <div class="ps-3 pe-2 flex-shrink-0">
                        <svg class="h-5 w-5 text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <input
                        type="text"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Search digital assets, pages, groups, websites…"
                        class="flex-grow-1 px-2 py-3 text-dark placeholder-gray-400 outline-none fs-sm"
                    >
                    <div class="p-2 pe-2">
                        <button type="submit" class="px-3 py-2 fs-sm fw-semibold text-white rounded-3" style="background:#10B981" onmouseover="this.style.background='#059669'" onmouseout="this.style.background='#10B981'">
                            Search
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Trust stats --}}
        <div class="mt-5 row row-cols-3 gap-3 max-w-sm mx-auto text-center">
            <div>
                <div class="fs-3 fw-bold" style="color:#10B981;font-family:ui-monospace,monospace">72h</div>
                <div class="fs-xs text-muted mt-1">Buyer protection</div>
            </div>
            <div class="border-start border-end border-secondary border-opacity-25">
                <div class="fs-3 fw-bold" style="color:#10B981;font-family:ui-monospace,monospace">10%</div>
                <div class="fs-xs text-muted mt-1">Flat seller fee</div>
            </div>
            <div>
                <div class="fs-3 fw-bold" style="color:#10B981;font-family:ui-monospace,monospace">৳50</div>
                <div class="fs-xs text-muted mt-1">Min. withdrawal</div>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════ --}}
{{-- TRUST STRIP                                         --}}
{{-- ═══════════════════════════════════════════════════ --}}
<section class="border-bottom bg-white">
    <div class="mx-auto max-w-7xl px-3 px-sm-4 px-lg-4 py-4">
        <div class="row row-cols-2 row-cols-4 gap-1 bg-light rounded-4 overflow-hidden">
            @foreach([
                ['Payment Protection','All payments held securely until delivery confirmed.',
                 '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>'],
                ['72h Buyer Protection','Dispute within 72 hours of delivery — fully protected.',
                 '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
                ['Verified Sellers','Every seller completes identity verification before listing.',
                 '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>'],
                ['Admin Moderated','Every listing reviewed by our team before going live.',
                 '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>'],
            ] as [$title,$desc,$icon])
            <div class="trust-item bg-white">
                <div class="trust-icon" style="background:#ECFDF5">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" class="h-5 w-5" style="color:#10B981">{!! $icon !!}</svg>
                </div>
                <div>
                    <p class="fs-sm fw-semibold text-dark">{{ $title }}</p>
                    <p class="fs-xs text-muted mt-1">{{ $desc }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════ --}}
{{-- CATEGORIES                                          --}}
{{-- ═══════════════════════════════════════════════════ --}}
<section class="py-5" style="background:#F9FAFB">
    <div class="mx-auto max-w-7xl px-3 px-sm-4 px-lg-4">
        <div class="section-header">
            <div>
                <h2 class="section-title">Explore Digital Assets</h2>
                <p class="section-sub">Find the right digital asset for your needs.</p>
            </div>
            <a href="{{ route('marketplace.index') }}" class="view-all">All assets →</a>
        </div>

        @if($categories->isEmpty())
            <div class="text-center py-5 text-secondary">
                <p class="fs-1 mb-2">🗂️</p>
                <p class="fw-medium text-muted">Categories coming soon</p>
            </div>
        @else
            <div class="row row-cols-2 row-cols-3 row-cols-4 row-cols-5 gap-3">
                @foreach($categories as $cat)
                    <a href="{{ route('marketplace.index',['category'=>$cat->slug]) }}" class="category-card">
                        <span class="cat-icon">{{ $cat->icon ?? '🗂️' }}</span>
                        <span class="cat-name">{{ $cat->name }}</span>
                        @if($cat->children_count > 0)
                            <span class="cat-count">{{ $cat->children_count }} sub-categories</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</section>

{{-- ═══════════════════════════════════════════════════ --}}
{{-- FEATURED LISTINGS                                   --}}
{{-- ═══════════════════════════════════════════════════ --}}
@if($featuredAssets->isNotEmpty())
<section class="py-5 bg-white">
    <div class="mx-auto max-w-7xl px-3 px-sm-4 px-lg-4">
        <div class="section-header">
            <div>
                <div class="d-inline-flex align-items-center gap-1 px-2 py-1 rounded-pill fs-xs fw-semibold mb-2" style="background:#FFFBEB;color:#92400E">
                    ⭐ Promoted
                </div>
                <h2 class="section-title">Featured Listings</h2>
            </div>
            <a href="{{ route('marketplace.index',['featured_only'=>1]) }}" class="view-all">View all →</a>
        </div>
        <div class="row row-cols-1 row-cols-2 row-cols-3 row-cols-4 gap-3">
            @foreach($featuredAssets as $asset)
                @include('marketplace.partials.asset-card',['asset'=>$asset,'isFavorited'=>false])
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ═══════════════════════════════════════════════════ --}}
{{-- LATEST LISTINGS                                     --}}
{{-- ═══════════════════════════════════════════════════ --}}
@if($latestAssets->isNotEmpty())
<section class="py-5" style="background:#F9FAFB">
    <div class="mx-auto max-w-7xl px-3 px-sm-4 px-lg-4">
        <div class="section-header">
            <div>
                <h2 class="section-title">Latest Listings</h2>
                <p class="section-sub">Newest digital assets on the marketplace.</p>
            </div>
            <a href="{{ route('marketplace.index') }}" class="view-all">View all →</a>
        </div>
        <div class="row row-cols-1 row-cols-2 row-cols-3 row-cols-4 gap-3">
            @foreach($latestAssets as $asset)
                @include('marketplace.partials.asset-card',['asset'=>$asset,'isFavorited'=>false])
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ═══════════════════════════════════════════════════ --}}
{{-- HOW IT WORKS                                        --}}
{{-- ═══════════════════════════════════════════════════ --}}
<section class="py-5 bg-white">
    <div class="mx-auto max-w-7xl px-3 px-sm-4 px-lg-4">
        <div class="text-center mb-4">
            <h2 class="section-title">How It Works</h2>
            <p class="section-sub mt-2">Simple, secure, and transparent</p>
        </div>
        <div class="row row-cols-1 row-cols-2 row-cols-4 gap-3">
            @foreach([
                ['01','Find a Listing','Browse verified listings across categories. Filter by price, type, or category.','🔍'],
                ['02','Pay Securely','Complete payment through UddoktaPay. Funds are held securely until delivery.','🔒'],
                ['03','Receive Your Asset','The seller delivers your asset through the private order chat.','📦'],
                ['04','Confirm & Complete','Accept delivery or raise a dispute within 72 hours. Orders auto-complete.','✅'],
            ] as [$num,$title,$desc,$icon])
            <div class="step-card">
                <div class="step-num mb-2">{{ $num }}</div>
                <div class="fs-3 mb-2">{{ $icon }}</div>
                <h3 class="fw-semibold text-dark mb-2">{{ $title }}</h3>
                <p class="fs-sm text-muted">{{ $desc }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════ --}}
{{-- SELLER VERIFICATION                                 --}}
{{-- ═══════════════════════════════════════════════════ --}}
<section class="py-5" style="background:#F0FDF4">
    <div class="mx-auto max-w-7xl px-3 px-sm-4 px-lg-4">
        <div class="row row-cols-2 gap-5 align-items-center">
            <div>
                <div class="d-inline-flex align-items-center gap-2 px-2 py-1 rounded-pill fs-sm fw-medium mb-3" style="background:#ECFDF5;color:#065F46">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color:#10B981"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    Seller Verification
                </div>
                <h2 class="fs-3 fs-sm-2 fw-bold text-dark mb-3">Buy From <span style="color:#10B981">Verified Sellers</span> Only</h2>
                <p class="text-muted mb-4">Every seller must complete identity verification before they can list or sell. This ensures every transaction is with a real, accountable person.</p>

                <ul class="vstack gap-2 mb-4">
                    @foreach([
                        ['NID, Passport, or Driving License','Document identity check'],
                ['Admin review process','Manual approval by our team'],
                        ['Verified badge on profile','Publicly visible on all listings'],
                    ] as [$title,$sub])
                    <li class="d-flex align-items-start gap-3">
                        <span class="mt-1 h-5 w-5 rounded-pill d-flex align-items-center justify-content-center flex-shrink-0" style="background:#ECFDF5">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color:#10B981"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <div>
                            <p class="fs-sm fw-semibold text-dark">{{ $title }}</p>
                            <p class="fs-xs text-muted">{{ $sub }}</p>
                        </div>
                    </li>
                    @endforeach
                </ul>

                <a href="{{ route('legal','seller-policy') }}" class="btn-em">
                    Learn About Verification
                </a>
            </div>
            <div class="row row-cols-2 gap-3">
                @foreach([
                    ['🪪','Identity Verified','NID / DOB check'],
        ['✅','Admin Approved','Manual review passed'],
                    ['🛡️','Buyer Protected','72h dispute window'],
                ] as [$icon,$t,$s])
                <div class="bg-white rounded-4 p-3 border text-center shadow-sm">
                    <div class="fs-2 mb-2">{{ $icon }}</div>
                    <p class="fw-semibold text-dark fs-sm">{{ $t }}</p>
                    <p class="fs-xs text-muted mt-1">{{ $s }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════ --}}
{{-- FINAL CTA                                           --}}
{{-- ═══════════════════════════════════════════════════ --}}
<section class="py-5 bg-white border-top">
    <div class="mx-auto max-w-3xl px-3 px-sm-4 px-lg-4 text-center">
        <h2 class="fs-3 fs-sm-2 fw-bold text-dark mb-2">Ready to Buy or Sell Digital Assets?</h2>
        <p class="text-muted mb-4">Explore the marketplace or become a verified seller today.</p>
        <div class="d-flex flex-column flex-sm-row align-items-center justify-content-center gap-3">
            <a href="{{ route('marketplace.index') }}" class="btn-em btn-em-lg w-100 w-sm-auto justify-content-center">
                Browse Marketplace
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </a>
            <a href="{{ route('register') }}" class="btn-outline-dark btn-outline-dark-lg w-100 w-sm-auto justify-content-center">
                Sell an Asset
            </a>
        </div>
        <p class="mt-4 fs-xs text-secondary">Free to join · Listings are free · 10% seller fee on sales only</p>
    </div>
</section>

</x-layouts.public>
