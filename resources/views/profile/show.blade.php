<x-layouts.public :title="$user->name.' — Seller Profile'">

<style>
.profile-avatar {
    width:5rem;height:5rem;border-radius:50%;display:grid;place-items:center;
    font-size:2rem;font-weight:700;color:#fff;background:#F97316;flex-shrink:0;
    font-family:Sora,sans-serif;
}
.profile-card { background:#fff;border:1px solid #E5E7EB;border-radius:.875rem;padding:1.25rem; }
.stat-box { text-align:center;padding:.5rem; }
.stat-num { font-size:1.25rem;font-weight:700;color:#10B981; }
.stat-label { font-size:.7rem;color:#9CA3AF;text-transform:uppercase;letter-spacing:.04em;margin-top:.125rem; }
.trust-row { display:flex;align-items:center;gap:.625rem;padding:.5rem 0;font-size:.8125rem;color:#374151; }
.trust-icon-ok  { width:1.125rem;height:1.125rem;background:#10B981;border-radius:50%;display:grid;place-items:center;color:#fff;flex-shrink:0;font-size:.625rem; }
.trust-icon-no  { width:1.125rem;height:1.125rem;background:#E5E7EB;border-radius:50%;display:grid;place-items:center;color:#9CA3AF;flex-shrink:0;font-size:.625rem; }
.trust-icon-part{ width:1.125rem;height:1.125rem;background:#FEF3C7;border-radius:50%;display:grid;place-items:center;color:#D97706;flex-shrink:0;font-size:.625rem; }
.reliability-box { flex:1;border:1px solid #E5E7EB;border-radius:.625rem;padding:.75rem;text-align:center; }
.reliability-role { font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9CA3AF; }
.reliability-val  { font-size:1rem;font-weight:700;color:#374151;margin-top:.125rem; }
.profile-tab { padding:.625rem 1rem;font-size:.875rem;font-weight:500;color:#6B7280;border-bottom:2px solid transparent;white-space:nowrap;text-decoration:none;transition:all .15s; }
.profile-tab:hover { color:#111827; }
.profile-tab.active { color:#F97316;border-bottom-color:#F97316;font-weight:600; }
.profile-tab-count { display:inline-flex;align-items:center;justify-content:center;min-width:1.25rem;height:1.25rem;border-radius:9999px;font-size:.7rem;font-weight:600;background:#F3F4F6;color:#6B7280;margin-left:.375rem;padding:0 .25rem; }
</style>

<div class="mx-auto max-w-6xl px-3 px-sm-4 px-lg-4 py-4">
    <div class="grid-cols-[18rem_1fr] gap-4">

        {{-- ── Left Sidebar ── --}}
        <div class="vstack gap-3">

            {{-- Profile card --}}
            <div class="profile-card text-center">
                {{-- Avatar --}}
                <div class="d-flex justify-content-center mb-2">
                    <div class="profile-avatar">{{ strtoupper(substr($user->name,0,1)) }}</div>
                </div>

                {{-- Name --}}
                <h1 class="fw-bold text-dark fs-5">{{ $user->name }}</h1>

                {{-- Verification badge --}}
                <div class="mt-2 inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium
                    {{ $user->isVerifiedSeller() ? '' : 'bg-gray-100 text-gray-500' }}"
                    style="{{ $user->isVerifiedSeller() ? 'background:#ECFDF5;color:#065F46' : '' }}">
                    @if($user->isVerifiedSeller())
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color:#10B981"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        Verified Seller
                    @else
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.618 5.984A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        Not Verified
                    @endif
                </div>

                {{-- Online status --}}
                <div class="mt-2 d-flex align-items-center justify-content-center gap-1 fs-xs fw-medium" style="color:#10B981">
                    <span class="h-2 w-2 rounded-pill" style="background:#10B981"></span>
                    Active now
                </div>

                {{-- Stats row --}}
                <div class="row row-cols-4 gap-1 mt-3 border-top pt-3">
                    <div class="stat-box">
                        <div class="stat-num">{{ $stats['reviews'] }}</div>
                        <div class="stat-label">Reviews</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-num">{{ $stats['trades'] }}</div>
                        <div class="stat-label">Trades</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-num">{{ $stats['listed'] }}</div>
                        <div class="stat-label">Listed</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-num">{{ $stats['sold'] }}</div>
                        <div class="stat-label">Sold</div>
                    </div>
                </div>

                {{-- Meta info --}}
                <div class="mt-3 pt-3 border-top vstack gap-2 fs-sm text-start">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Member since</span>
                        <span class="fw-medium text-gray-800">{{ $user->created_at->format('Y-m') }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Completed sales</span>
                        <span class="fw-medium text-gray-800">{{ $stats['sold'] }}</span>
                    </div>
                </div>

                @if($isOwnProfile)
                    <a href="{{ route('dashboard.profile') }}" class="mt-3 d-block w-100 text-center py-2 fs-sm fw-semibold text-white rounded-3" style="background:#10B981">
                        Edit Profile
                    </a>
                @endif
            </div>

            {{-- Reliability Scores --}}
            <div class="profile-card">
                <p class="fs-xs fw-bold text-secondary text-uppercase mb-2">Reliability Scores</p>
                <div class="d-flex gap-2">
                    <div class="reliability-box">
                        <div class="reliability-role">Seller</div>
                        <div class="reliability-val">{{ $stats['sold'] > 0 ? 'Active' : 'New' }}</div>
                    </div>
                    <div class="reliability-box">
                        <div class="reliability-role">Buyer</div>
                        <div class="reliability-val">{{ $stats['purchases'] > 0 ? 'Active' : 'New' }}</div>
                    </div>
                </div>
            </div>

            {{-- Trust & Safety --}}
            <div class="profile-card">
                <p class="fs-xs fw-bold text-secondary text-uppercase mb-2">Trust &amp; Safety</p>
                <div class="divide-y">
                    <div class="trust-row">
                        @if($user->isVerifiedSeller())
                            <span class="trust-icon-ok">✓</span>
                            <span style="color:#10B981;font-weight:500">ID Verified</span>
                        @else
                            <span class="trust-icon-no">✗</span>
                            <span class="text-secondary">ID Not Verified</span>
                        @endif
                    </div>
                    <div class="trust-row">
                        @if($user->phone)
                            <span class="trust-icon-ok">✓</span>
                            <span style="color:#10B981;font-weight:500">Phone Registered</span>
                        @else
                            <span class="trust-icon-no">○</span>
                            <span class="text-secondary">Phone Not Registered</span>
                        @endif
                    </div>
                    <div class="trust-row">
                        @if($stats['trades'] > 0)
                            <span class="trust-icon-ok">✓</span>
                            <span style="color:#10B981;font-weight:500">Has Completed Trades</span>
                        @else
                            <span class="trust-icon-part">○</span>
                            <span class="text-muted">Has Completed Trades</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Right Content ── --}}
        <div>
            {{-- Tabs --}}
            <div class="d-flex border-bottom border-secondary border-opacity-25 overflow-x-auto mb-3">
                @foreach([
                    ['listings',  'Active Listings',      $stats['listed']],
                    ['sales',     'Completed Sales',      $stats['sold']],
                    ['purchases', 'Completed Purchases',  $stats['purchases']],
                    ['reviews',   'Reviews',              $stats['reviews']],
                ] as [$key,$label,$count])
                <a href="{{ route('profile.show', $user->username ?? $user->id) }}?tab={{ $key }}"
                   class="profile-tab {{ $tab === $key ? 'active' : '' }}">
                    {{ $label }}
                    <span class="profile-tab-count">{{ $count }}</span>
                </a>
                @endforeach
            </div>

            {{-- Active Listings --}}
            @if($tab === 'listings')
                @if($listings->isEmpty())
                    <div class="d-flex flex-column align-items-center justify-content-center py-5 text-center">
                        <svg class="h-16 w-16 text-gray-200 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M10 14l2 2 2-2"/></svg>
                        <p class="text-muted fw-medium">No active listings.</p>
                    </div>
                @else
                    <div class="row row-cols-1 row-cols-2 row-cols-3 gap-3">
                        @foreach($listings as $asset)
                            @include('marketplace.partials.asset-card',['asset'=>$asset,'isFavorited'=>false])
                        @endforeach
                    </div>
                    <div class="mt-3">{{ $listings->links() }}</div>
                @endif
            @endif

            {{-- Completed Sales --}}
            @if($tab === 'sales')
                @if($completedSales->isEmpty())
                    <div class="d-flex flex-column align-items-center justify-content-center py-5 text-center">
                        <p class="fs-1 mb-2">📦</p>
                        <p class="text-muted fw-medium">No completed sales yet.</p>
                    </div>
                @else
                    <div class="row row-cols-1 row-cols-2 row-cols-3 gap-3">
                        @foreach($completedSales as $order)
                            @if($order->asset)
                                @include('marketplace.partials.asset-card',['asset'=>$order->asset,'isFavorited'=>false])
                            @endif
                        @endforeach
                    </div>
                    <div class="mt-3">{{ $completedSales->links() }}</div>
                @endif
            @endif

            {{-- Completed Purchases --}}
            @if($tab === 'purchases')
                @if($completedPurchases->isEmpty())
                    <div class="d-flex flex-column align-items-center justify-content-center py-5 text-center">
                        <p class="fs-1 mb-2">🛒</p>
                        <p class="text-muted fw-medium">No completed purchases yet.</p>
                    </div>
                @else
                    <div class="row row-cols-1 row-cols-2 row-cols-3 gap-3">
                        @foreach($completedPurchases as $order)
                            @if($order->asset)
                                @include('marketplace.partials.asset-card',['asset'=>$order->asset,'isFavorited'=>false])
                            @endif
                        @endforeach
                    </div>
                    <div class="mt-3">{{ $completedPurchases->links() }}</div>
                @endif
            @endif

            {{-- Reviews --}}
            @if($tab === 'reviews')
                @if($reviews->isEmpty())
                    <div class="d-flex flex-column align-items-center justify-content-center py-5 text-center">
                        <p class="fs-1 mb-2">⭐</p>
                        <p class="text-muted fw-medium">No reviews yet.</p>
                        <p class="fs-sm text-secondary mt-1">Reviews will appear here after completed trades.</p>
                    </div>
                @else
                    <div class="vstack gap-2">
                    @foreach($reviews as $review)
                        <div class="bg-white border border-secondary border-opacity-25 rounded-4 p-3">
                            <div class="d-flex align-items-start justify-content-between gap-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="h-9 w-9 rounded-pill d-flex align-items-center justify-content-center fs-sm fw-bold text-white flex-shrink-0" style="background:#10B981">
                                        {{ strtoupper(substr($review->reviewer->name,0,1)) }}
                                    </div>
                                    <div>
                                        <p class="fw-semibold text-dark fs-sm">{{ $review->reviewer->name }}</p>
                                        <p class="fs-xs text-secondary">{{ $review->created_at->format('d M Y') }}</p>
                                    </div>
                                </div>
                                <div class="d-flex gap-1 flex-shrink-0">
                                    @for($i=1;$i<=5;$i++)
                                        <span class="text-base {{ $i <= $review->rating ? '' : 'opacity-20' }}" style="color:#F59E0B">★</span>
                                    @endfor
                                </div>
                            </div>
                            @if($review->comment)
                                <p class="mt-2 fs-sm text-muted">{{ $review->comment }}</p>
                            @endif
                            <p class="mt-2 fs-xs text-secondary">{{ $review->asset->title }}</p>
                        </div>
                    @endforeach
                    </div>
                    <div class="mt-3">{{ $reviews->links() }}</div>
                @endif
            @endif
        </div>
    </div>
</div>
</x-layouts.public>
