<x-layouts.admin title="Dashboard" heading="Platform overview">
    {{-- Quick stats row --}}
    <div class="row row-cols-2 row-cols-4 gap-3 mb-3">
        @foreach([
            ['Users',$stats['users'],'👥',null],
            ['Verified sellers',$stats['active_users'],'✅',null],
            ['Published listings',$stats['published_listings'],'🏷️',null],
            ['Orders this month',$stats['orders_month'],'📦',null],
        ] as [$label,$value,$icon,$_])
            <div class="stat-card"><p class="stat-label">{{ $icon }} {{ $label }}</p><p class="stat-value">{{ number_format($value) }}</p></div>
        @endforeach
    </div>

    {{-- Revenue + needs-attention --}}
    <div class="row row-cols-3 gap-3 mb-3">
        <div class="stat-card bg-success bg-opacity-10">
            <p class="stat-label text-success">💰 Commission this month</p>
            <x-money :amount="$stats['revenue_month']" class="stat-value text-success d-block money" />
            <a href="{{ route('admin.reports') }}" class="fs-xs text-success mt-1 d-inline-block">Full report →</a>
        </div>
        <div class="stat-card bg-warning bg-opacity-10">
            <p class="stat-label text-warning">⚠ Active promotions</p>
            <p class="stat-value text-warning">{{ $stats['active_promotions'] }}</p>
        </div>
        <div class="stat-card">
            <p class="stat-label">🎧 Open tickets</p>
            <p class="stat-value">{{ $stats['open_tickets'] }}</p>
            @if($stats['unassigned_tickets'] > 0)
                <p class="fs-xs text-danger mt-1">{{ $stats['unassigned_tickets'] }} unassigned</p>
            @endif
        </div>
    </div>

    {{-- Action queues --}}
    <div class="row row-cols-2 gap-3 mb-3">
        <div class="card-p">
            <h2 class="fw-semibold text-dark mb-2">Needs attention</h2>
            <ul class="fs-sm divide-y">
                <li class="d-flex justify-content-between py-2"><span>Pending verification</span><a href="{{ route('admin.verification') }}" class="text-primary fw-medium">{{ $stats['pending_verifications'] }} →</a></li>
                <li class="d-flex justify-content-between py-2"><span>Pending listings</span><a href="{{ route('admin.listings') }}" class="text-primary fw-medium">{{ $stats['pending_listings'] }} →</a></li>
                <li class="d-flex justify-content-between py-2"><span>Open disputes</span><a href="{{ route('admin.disputes') }}" class="text-primary fw-medium">{{ $stats['open_disputes'] }} →</a></li>
                <li class="d-flex justify-content-between py-2"><span>Pending withdrawals</span><a href="{{ route('admin.withdrawals') }}" class="text-primary fw-medium">{{ $stats['pending_withdrawals'] }} →</a></li>
                <li class="d-flex justify-content-between py-2"><span>Approved withdrawals (to process)</span><a href="{{ route('admin.withdrawals',['status'=>'approved']) }}" class="text-warning fw-medium">{{ $stats['approved_withdrawals'] }} →</a></li>
                <li class="d-flex justify-content-between py-2"><span>Suspended users</span><a href="{{ route('admin.users',['status'=>'suspended']) }}" class="text-dark fw-medium">{{ $stats['suspended_users'] }} →</a></li>
            </ul>
        </div>
        <div class="card-p">
            <h2 class="fw-semibold text-dark mb-2">Recent paid orders</h2>
            @forelse($recentOrders as $o)
                <div class="d-flex align-items-center gap-2 py-1 border-bottom fs-sm">
                    <a href="{{ route('admin.orders.show',$o) }}" class="font-mono fs-xs text-primary">{{ $o->order_number }}</a>
                    <span class="text-muted flex-grow-1 text-truncate">{{ $o->asset->title }}</span>
                    <x-money :amount="$o->buyer_total" class="text-dark" />
                </div>
            @empty
                <p class="fs-sm text-muted">No recent orders.</p>
            @endforelse
        </div>
    </div>

    {{-- Recent tickets --}}
    @if($recentTickets->isNotEmpty())
        <div class="card-p">
            <div class="d-flex justify-content-between mb-2"><h2 class="fw-semibold text-dark">Open support tickets</h2>
                <a href="{{ route('admin.tickets') }}" class="fs-xs text-primary">View all →</a></div>
            <div class="vstack gap-2">
            @foreach($recentTickets as $t)
                <div class="d-flex align-items-center gap-2 fs-sm">
                    <span class="badge-{{ $t->priorityColor() }} text-[10px] shrink-0">{{ ucfirst($t->priority) }}</span>
                    <a href="{{ route('admin.tickets.show',$t) }}" class="text-dark flex-grow-1 text-truncate">{{ $t->subject }}</a>
                    <span class="fs-xs text-secondary">{{ $t->user->name }}</span>
                </div>
            @endforeach
            </div>
        </div>
    @endif
</x-layouts.admin>

