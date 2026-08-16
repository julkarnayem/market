<x-layouts.dashboard title="Overview" :heading="'Hi, '.\Illuminate\Support\Str::before(auth()->user()->name, ' ')">
    {{-- Verification nudge --}}
    @unless (auth()->user()->isVerifiedSeller())
        <x-alert type="info" class="mb-3 d-flex align-items-center justify-content-between gap-3">
            <span>Get verified to start selling assets.</span>
            <a href="{{ route('dashboard.verification') }}" class="btn-primary btn-sm flex-shrink-0">Verify now</a>
        </x-alert>
    @endunless

    {{-- Wallet snapshot --}}
    <div class="row row-cols-2 row-cols-4 gap-3 mb-4">
        <x-card>
            <p class="fs-xs text-muted">Available balance</p>
            <x-money :amount="$stats['available']" class="fs-4 fw-bold text-dark d-block mt-1" />
        </x-card>
        <x-card>
            <p class="fs-xs text-muted">Pending (locked)</p>
            <x-money :amount="$stats['pending']" class="fs-4 fw-bold text-warning d-block mt-1" />
        </x-card>
        <x-card>
            <p class="fs-xs text-muted">Active listings</p>
            <p class="fs-4 fw-bold text-dark mt-1">{{ $stats['listings'] }}</p>
        </x-card>
        <x-card>
            <p class="fs-xs text-muted">Orders</p>
            <p class="fs-4 fw-bold text-dark mt-1">{{ $stats['orders'] }}</p>
        </x-card>
    </div>

    <div class="row row-cols-2 gap-3">
        <x-card>
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h2 class="fw-semibold text-dark">Quick actions</h2>
            </div>
            <div class="row row-cols-2 gap-2">
                <a href="{{ route('marketplace.index') }}" class="btn-outline">Browse assets</a>
                <a href="{{ route('dashboard.wallet') }}" class="btn-outline">Open wallet</a>
                @if (auth()->user()->canSell())
                    <a href="{{ route('dashboard.listings.create') }}" class="btn-primary col-span-2">Create a listing</a>
                @else
                    <a href="{{ route('dashboard.verification') }}" class="btn-primary col-span-2">Become a seller</a>
                @endif
            </div>
        </x-card>
        <x-card>
            <h2 class="fw-semibold text-dark mb-2">Recent activity</h2>
            <x-empty-state title="No activity yet" icon="🕓">Your purchases, sales and messages will appear here.</x-empty-state>
        </x-card>
    </div>
</x-layouts.dashboard>
