<x-layouts.dashboard title="My Purchases" heading="My Purchases">
    @php
        $tab = request('tab', 'all');
        $statuses = ['all'=>'All','delivery_pending'=>'Awaiting Delivery','delivered'=>'Delivered','completed'=>'Completed','disputed'=>'Disputed'];
    @endphp
    <div class="tabs overflow-x-auto text-nowrap mb-3">
        @foreach($statuses as $v => $l)
            <a href="{{ route('dashboard.purchases', ['tab' => $v]) }}" class="tab {{ $tab === $v ? 'tab-active' : '' }}">{{ $l }}</a>
        @endforeach
    </div>

    @if($orders->isEmpty())
        <x-empty-state icon="🛍️" title="No purchases yet">
            Browse the marketplace to find digital assets to purchase.
            <x-slot:slot><a href="{{ route('marketplace.index') }}" class="btn-primary mt-3">Browse marketplace</a></x-slot:slot>
        </x-empty-state>
    @else
        <div class="table-wrap d-none d-sm-block">
            <table class="table">
                <thead><tr><th>Order</th><th>Asset</th><th>Seller</th><th>Total paid</th><th>Status</th><th>Date</th><th></th></tr></thead>
                <tbody>
                @foreach($orders as $o)
                    <tr>
                        <td class="font-mono fs-xs text-muted">{{ $o->order_number }}</td>
                        <td class="fw-medium max-w-[160px] text-truncate">{{ $o->asset->title }}</td>
                        <td class="fs-sm text-muted">{{ $o->seller->name }}</td>
                        <td class="money fw-semibold">{{ \App\Support\Money::format($o->buyer_total) }}</td>
                        <td><x-status-badge :status="$o->status->value" /></td>
                        <td class="fs-xs text-muted">{{ $o->created_at->format('d M Y') }}</td>
                        <td><a href="{{ route('dashboard.orders.show', $o) }}" class="btn-ghost btn-sm">View</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="d-sm-none vstack gap-2">
        @foreach($orders as $o)
            <a href="{{ route('dashboard.orders.show', $o) }}" class="card-p d-block">
                <div class="d-flex align-items-start justify-content-between gap-2">
                    <div class="">
                        <p class="fw-semibold text-dark text-truncate">{{ $o->asset->title }}</p>
                        <p class="fs-xs font-mono text-muted mt-1">{{ $o->order_number }}</p>
                    </div>
                    <x-status-badge :status="$o->status->value" />
                </div>
                <div class="d-flex justify-content-between mt-2 fs-sm">
                    <span class="text-muted">{{ $o->seller->name }}</span>
                    <x-money :amount="$o->buyer_total" class="fw-bold" />
                </div>
            </a>
        @endforeach
        </div>
        <div class="mt-3">{{ $orders->withQueryString()->links() }}</div>
    @endif
</x-layouts.dashboard>
