<header x-data="{ mobileMenu: false, searchOpen: false }" class="position-sticky bg-white border-bottom shadow-sm">
    <div class="mx-auto max-w-7xl px-3 px-sm-4 px-lg-4">
        <div class="d-flex h-16 align-items-center gap-3">

            {{-- Logo --}}
            <a href="{{ url('/') }}" class="d-flex align-items-center gap-2 flex-shrink-0">
                <span class="h-9 w-9 d-grid place-items-center rounded-3 text-white font-display fw-bold" style="background:#10B981">
                    {{ strtoupper(substr(config('app.name','M'),0,1)) }}
                </span>
                <span class="d-none d-sm-block font-display fw-bold text-dark fs-5">{{ config('app.name','Marketplace') }}</span>
            </a>

            {{-- Desktop nav --}}
            <nav class="d-none d-md-flex align-items-center gap-1 ms-2">
                <a href="{{ url('/') }}" class="px-2 py-2 fs-sm fw-medium text-muted rounded-3">Home</a>
                <a href="{{ route('marketplace.index') }}" class="px-2 py-2 fs-sm fw-medium text-muted rounded-3">Marketplace</a>
                <a href="{{ route('faq') }}" class="px-2 py-2 fs-sm fw-medium text-muted rounded-3">Help</a>
                <a href="{{ route('contact') }}" class="px-2 py-2 fs-sm fw-medium text-muted rounded-3">Contact</a>
            </nav>

            {{-- Desktop search --}}
            <form action="{{ route('marketplace.index') }}" method="GET" class="d-none d-lg-flex flex-grow-1 max-w-sm mx-3">
                <div class="position-relative w-100">
                    <svg class="position-absolute h-4 w-4 text-secondary" style="top:50%;left:.75rem;transform:translateY(-50%)" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input name="q" value="{{ request('q') }}" placeholder="Search assets…"
                           class="w-100 pe-3 py-2 fs-sm border border-secondary border-opacity-25 rounded-3 bg-light" style="padding-left:2.25rem"
                           style="focus:border-color:#10B981">
                </div>
            </form>

            <div class="d-flex align-items-center gap-2 ms-auto">
                @auth
                    <a href="{{ route('dashboard.messages') }}" class="p-2 text-muted rounded-3 d-none d-sm-flex" title="Messages">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </a>
                    <a href="{{ route('dashboard.notifications') }}" class="p-2 text-muted rounded-3 d-none d-sm-flex" title="Notifications">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    </a>

                    @if(auth()->user()->canSell())
                        <a href="{{ route('dashboard.listings.create') }}" class="d-none d-sm-inline-flex align-items-center gap-1 px-3 py-2 fs-sm fw-semibold text-white rounded-3" style="background:#10B981" onmouseover="this.style.background='#059669'" onmouseout="this.style.background='#10B981'">
                            + Sell asset
                        </a>
                    @else
                        <a href="{{ route('dashboard.verification') }}" class="d-none d-sm-inline-flex px-3 py-2 fs-sm fw-semibold text-dark border border-secondary border-opacity-25 rounded-3">
                            Become a seller
                        </a>
                    @endif

                    {{-- User menu --}}
                    <div x-data="{ open: false }" class="position-relative">
                        <button @click="open = !open" class="d-flex align-items-center gap-2 p-1 rounded-3">
                            <span class="h-8 w-8 d-grid place-items-center rounded-pill fs-sm fw-bold text-white" style="background:#10B981">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </span>
                        </button>
                        <div x-show="open" @click.outside="open = false" x-transition x-cloak
                             class="position-absolute mt-2 w-56 bg-white border rounded-4 shadow-xl p-1">
                            <div class="px-2 py-2 border-bottom mb-1">
                                <p class="fs-sm fw-semibold text-dark text-truncate">{{ auth()->user()->name }}</p>
                                <p class="fs-xs text-muted text-truncate">{{ auth()->user()->email }}</p>
                            </div>
                            <a href="{{ route('dashboard') }}" class="d-flex align-items-center gap-2 px-2 py-2 fs-sm text-dark rounded-3">Dashboard</a>
                            <a href="{{ route('dashboard.wallet') }}" class="d-flex align-items-center gap-2 px-2 py-2 fs-sm text-dark rounded-3">Wallet</a>
                            <a href="{{ route('dashboard.orders') }}" class="d-flex align-items-center gap-2 px-2 py-2 fs-sm text-dark rounded-3">Orders</a>
                            <a href="{{ route('dashboard.profile') }}" class="d-flex align-items-center gap-2 px-2 py-2 fs-sm text-dark rounded-3">Profile</a>
                            @if(auth()->user()->isAdmin())
                                <a href="{{ route('admin.dashboard') }}" class="d-flex align-items-center gap-2 px-2 py-2 fs-sm fw-medium rounded-3" style="color:#10B981">Admin panel</a>
                            @endif
                            <div class="border-top mt-1 pt-1">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="w-100 d-flex align-items-center gap-2 px-2 py-2 fs-sm text-danger rounded-3 text-start">Log out</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="px-3 py-2 fs-sm fw-medium text-dark">Log in</a>
                    <a href="{{ route('register') }}" class="px-3 py-2 fs-sm fw-semibold text-white rounded-3" style="background:#10B981" onmouseover="this.style.background='#059669'" onmouseout="this.style.background='#10B981'">
                        Get Started
                    </a>
                @endauth

                {{-- Mobile menu toggle --}}
                <button @click="mobileMenu = !mobileMenu" class="p-2 text-muted rounded-3 d-md-none" aria-label="Menu">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </div>

        {{-- Mobile menu --}}
        <div x-show="mobileMenu" @click.outside="mobileMenu = false" x-transition x-cloak class="d-md-none border-top py-2 vstack gap-1">
            <form action="{{ route('marketplace.index') }}" method="GET" class="mb-2">
                <div class="position-relative">
                    <svg class="position-absolute h-4 w-4 text-secondary" style="top:50%;left:.75rem;transform:translateY(-50%)" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input name="q" value="{{ request('q') }}" placeholder="Search assets…" class="w-100 pe-3 py-2 fs-sm border border-secondary border-opacity-25 rounded-3 bg-light" style="padding-left:2.25rem">
                </div>
            </form>
            <a href="{{ url('/') }}" class="d-flex align-items-center gap-2 px-2 py-2 fs-sm fw-medium text-dark rounded-3">Home</a>
            <a href="{{ route('marketplace.index') }}" class="d-flex align-items-center gap-2 px-2 py-2 fs-sm fw-medium text-dark rounded-3">Marketplace</a>
            <a href="{{ route('faq') }}" class="d-flex align-items-center gap-2 px-2 py-2 fs-sm fw-medium text-dark rounded-3">Help</a>
            <a href="{{ route('contact') }}" class="d-flex align-items-center gap-2 px-2 py-2 fs-sm fw-medium text-dark rounded-3">Contact</a>
            @auth
                @if(auth()->user()->canSell())
                    <a href="{{ route('dashboard.listings.create') }}" class="d-flex align-items-center gap-2 px-2 py-2 fs-sm fw-semibold rounded-3 text-white mt-2" style="background:#10B981">+ Sell Asset</a>
                @else
                    <a href="{{ route('dashboard.verification') }}" class="d-flex align-items-center gap-2 px-2 py-2 fs-sm fw-semibold text-dark border border-secondary border-opacity-25 rounded-3 mt-2">Become a Seller</a>
                @endif
            @else
                <div class="row row-cols-2 gap-2 mt-2 pt-2 border-top">
                    <a href="{{ route('login') }}" class="text-center px-3 py-2 fs-sm fw-medium text-dark border border-secondary border-opacity-25 rounded-3">Log in</a>
                    <a href="{{ route('register') }}" class="text-center px-3 py-2 fs-sm fw-semibold text-white rounded-3" style="background:#10B981">Get Started</a>
                </div>
            @endauth
        </div>
    </div>
</header>
<div>@include('partials.flash')</div>
