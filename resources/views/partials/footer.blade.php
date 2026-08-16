<footer class="bg-dark text-secondary mt-0">
    <div class="mx-auto max-w-7xl px-3 px-sm-4 px-lg-4 pt-5 pb-4">
        <div class="row row-cols-2 row-cols-5 gap-5">
            {{-- Brand --}}
            <div class="col-span-2">
                <a href="{{ url('/') }}" class="d-flex align-items-center gap-2 mb-3">
                    <span class="h-9 w-9 d-grid place-items-center rounded-3 text-white fw-bold" style="background:#10B981">
                        {{ strtoupper(substr(config('app.name','M'),0,1)) }}
                    </span>
                    <span class="fw-bold fs-5" style="color:#fff">{{ config('app.name') }}</span>
                </a>
                <p class="fs-sm text-secondary max-w-xs">
                    Bangladesh's trusted marketplace for buying and selling digital assets — social pages, websites, domains, and software.
                </p>
                <div class="mt-3 d-flex align-items-center gap-2">
                    <span class="d-inline-flex align-items-center gap-1 px-2 py-1 rounded-pill fs-xs fw-medium bg-emerald-900/50 text-emerald-400 border">
                        <span class="h-1.5 w-1.5 rounded-pill bg-emerald-400"></span> Secure & Moderated
                    </span>
                </div>
            </div>

            {{-- Marketplace --}}
            <div>
                <h4 class="fw-semibold fs-sm mb-3" style="color:#fff">Marketplace</h4>
                <ul class="vstack gap-2 fs-sm">
                    <li><a href="{{ route('marketplace.index') }}" class="" style="color:#9CA3AF" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#9CA3AF'">Browse Listings</a></li>
                    <li><a href="{{ route('marketplace.index') }}#categories" class="" style="color:#9CA3AF" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#9CA3AF'">Categories</a></li>
                    <li><a href="{{ route('faq') }}" class="" style="color:#9CA3AF" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#9CA3AF'">How It Works</a></li>
                    <li><a href="{{ route('contact') }}" class="" style="color:#9CA3AF" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#9CA3AF'">Contact Support</a></li>
                </ul>
            </div>

            {{-- Sellers --}}
            <div>
                <h4 class="fw-semibold fs-sm mb-3" style="color:#fff">Sellers</h4>
                <ul class="vstack gap-2 fs-sm">
                    @auth
                        @if(auth()->user()->canSell())
                            <li><a href="{{ route('dashboard.listings.create') }}" class="" style="color:#9CA3AF" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#9CA3AF'">Create Listing</a></li>
                        @endif
                    @endauth
                    <li><a href="{{ route('register') }}" class="" style="color:#9CA3AF" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#9CA3AF'">Become a Seller</a></li>
                    <li><a href="{{ route('legal','seller-policy') }}" class="" style="color:#9CA3AF" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#9CA3AF'">Seller Policy</a></li>
                    <li><a href="{{ route('legal','prohibited-assets') }}" class="" style="color:#9CA3AF" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#9CA3AF'">Prohibited Assets</a></li>
                </ul>
            </div>

            {{-- Legal --}}
            <div>
                <h4 class="fw-semibold fs-sm mb-3" style="color:#fff">Legal</h4>
                <ul class="vstack gap-2 fs-sm">
                    <li><a href="{{ route('legal','buyer-protection') }}" class="" style="color:#9CA3AF" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#9CA3AF'">Buyer Protection</a></li>
                    <li><a href="{{ route('legal','refund-policy') }}" class="" style="color:#9CA3AF" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#9CA3AF'">Refund Policy</a></li>
                    <li><a href="{{ route('legal','dispute-policy') }}" class="" style="color:#9CA3AF" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#9CA3AF'">Dispute Policy</a></li>
                    <li><a href="{{ route('legal','terms') }}" class="" style="color:#9CA3AF" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#9CA3AF'">Terms of Service</a></li>
                    <li><a href="{{ route('legal','privacy') }}" class="" style="color:#9CA3AF" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#9CA3AF'">Privacy Policy</a></li>
                </ul>
            </div>
        </div>

        {{-- Bottom bar --}}
        <div class="mt-5 pt-4 border-top d-flex flex-column flex-sm-row align-items-center justify-content-between gap-3 fs-xs text-muted">
            <p style="color:#6B7280">© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            <p style="color:#6B7280">Payouts in <span class="font-mono fw-medium" style="color:#9CA3AF">৳ BDT</span> · bKash · Nagad · Rocket · Upay</p>
        </div>
    </div>
</footer>
