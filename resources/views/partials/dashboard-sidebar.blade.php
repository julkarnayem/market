@php
    $r = Route::currentRouteName();
    $items = [
        ['dashboard','.','Overview','▦'],
        ['dashboard.purchases','.purchases','My Purchases','🛍️'],
        ['dashboard.orders','.orders','My Orders','📦'],
        ['dashboard.listings','.listings','My Listings','🏷️'],
        ['dashboard.listings.create','.listings.create','Create Listing','＋'],
        ['dashboard.offers','.offers','Offers','🤝'],
        ['dashboard.messages','.messages','Messages','✉️'],
        ['dashboard.notifications','.notifications','Notifications','🔔'],
        ['dashboard.wallet','.wallet','Wallet','👛'],
        ['dashboard.withdrawals','.withdrawals','Withdrawals','🏦'],
        ['dashboard.verification','.verification','Verification','✅'],
        ['dashboard.favorites','.favorites','Favorites','★'],
        ['dashboard.tickets','.tickets','Support','🎧'],
        ['dashboard.profile','.profile','Profile','👤'],
        ['dashboard.security','.security','Security','🔒'],
    ];
@endphp
<nav class="card p-2 space-y-0.5 position-sticky">
    @foreach($items as [$route,$name,$label,$icon])
        <a href="{{ route($route) }}" class="nav-link {{ $r === $route ? 'nav-link-active' : '' }}" aria-current="{{ $r===$route?'page':'false' }}">
            <span class="w-5 text-center flex-shrink-0">{{ $icon }}</span>
            <span>{{ $label }}</span>
        </a>
    @endforeach
</nav>
