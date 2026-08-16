<x-layouts.admin title="Disputes" heading="Dispute Management">
    <div class="tabs mb-3">
        @foreach(['open'=>'Open','under_review'=>'Under Review','waiting_for_buyer'=>'Waiting Buyer','waiting_for_seller'=>'Waiting Seller','resolved'=>'Resolved','all'=>'All'] as $k=>$l)
            <a href="{{ route('admin.disputes',['status'=>$k]) }}" class="tab {{ request('status','open')===$k?'tab-active':'' }}">{{ $l }}</a>
        @endforeach
    </div>
    <div class="table-wrap d-none d-sm-block">
        <table class="table">
            <thead><tr><th>#</th><th>Order</th><th>Buyer</th><th>Seller</th><th>Order total</th><th>Status</th><th>Opened</th><th></th></tr></thead>
            <tbody>
            @forelse($disputes as $d)
                <tr>
                    <td class="text-muted font-mono fs-xs">#{{ $d->id }}</td>
                    <td class="font-mono fs-xs"><a href="{{ route('admin.orders.show',$d->order) }}" class="text-primary">{{ $d->order->order_number }}</a></td>
                    <td class="fs-sm">{{ $d->order->buyer->name }}</td>
                    <td class="fs-sm">{{ $d->order->seller->name }}</td>
                    <td class="money fw-semibold">{{ \App\Support\Money::format($d->order->buyer_total) }}</td>
                    <td><x-status-badge :status="$d->status->value" /></td>
                    <td class="fs-xs text-muted">{{ $d->created_at->diffForHumans() }}</td>
                    <td><a href="{{ route('admin.disputes.show',$d) }}" class="btn-ghost btn-sm">Review</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center py-4 text-muted">No disputes.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="d-sm-none vstack gap-2">
    @foreach($disputes as $d)
        <a href="{{ route('admin.disputes.show',$d) }}" class="card-p d-block">
            <div class="d-flex justify-content-between gap-2">
                <p class="font-mono fs-xs text-muted">{{ $d->order->order_number }}</p>
                <x-status-badge :status="$d->status->value" />
            </div>
            <p class="fs-sm text-dark mt-1">{{ $d->order->buyer->name }} vs {{ $d->order->seller->name }}</p>
            <div class="d-flex justify-content-between mt-1 fs-xs text-muted">
                <span>{{ $d->created_at->diffForHumans() }}</span>
                <x-money :amount="$d->order->buyer_total" />
            </div>
        </a>
    @endforeach
    </div>
    <div class="mt-3">{{ $disputes->withQueryString()->links() }}</div>
</x-layouts.admin>
