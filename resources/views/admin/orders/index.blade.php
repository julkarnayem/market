<x-layouts.admin title="Orders" heading="Order Management">
    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        <form method="GET" class="d-flex flex-wrap gap-2">
            <input name="q" value="{{ request('q') }}" placeholder="Order number…" class="input max-w-xs">
            <select name="status" class="select w-auto" onchange="this.form.submit()">
                <option value="all" @selected(request('status','all')==='all')>All status</option>
                @foreach(['pending_payment','delivery_pending','delivered','completed','disputed','refunded'] as $s)
                    <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucwords(str_replace('_',' ',$s)) }}</option>
                @endforeach
            </select>
            <x-button type="submit" variant="outline">Filter</x-button>
        </form>
    </div>
    <div class="table-wrap d-none d-sm-block">
        <table class="table">
            <thead><tr><th>Order #</th><th>Asset</th><th>Buyer</th><th>Seller</th><th>Total</th><th>Status</th><th>Payment</th><th>Date</th><th></th></tr></thead>
            <tbody>
            @forelse($orders as $o)
                <tr>
                    <td class="font-mono fs-xs">{{ $o->order_number }}</td>
                    <td class="fw-medium max-w-[140px] text-truncate">{{ $o->asset->title }}</td>
                    <td class="fs-sm">{{ $o->buyer->name }}</td>
                    <td class="fs-sm">{{ $o->seller->name }}</td>
                    <td class="money fw-semibold">{{ \App\Support\Money::format($o->buyer_total) }}</td>
                    <td><x-status-badge :status="$o->status->value" /></td>
                    <td><x-status-badge :status="$o->payment_status" /></td>
                    <td class="fs-xs text-muted">{{ $o->created_at->format('d M Y') }}</td>
                    <td><a href="{{ route('admin.orders.show',$o) }}" class="btn-ghost btn-sm">View</a></td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center py-4 text-muted">No orders found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="d-sm-none vstack gap-2">
    @foreach($orders as $o)
        <a href="{{ route('admin.orders.show',$o) }}" class="card-p d-block">
            <div class="d-flex justify-content-between gap-2"><p class="font-mono fs-xs text-muted">{{ $o->order_number }}</p><x-status-badge :status="$o->status->value" /></div>
            <p class="fw-semibold text-dark fs-sm mt-1 text-truncate">{{ $o->asset->title }}</p>
            <div class="d-flex justify-content-between mt-1 fs-xs text-muted"><span>{{ $o->buyer->name }}</span><x-money :amount="$o->buyer_total" /></div>
        </a>
    @endforeach
    </div>
    <div class="mt-3">{{ $orders->withQueryString()->links() }}</div>
</x-layouts.admin>
