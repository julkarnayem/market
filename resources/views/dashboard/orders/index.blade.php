<x-layouts.dashboard title="Orders" heading="Orders">
    {{-- Role toggle --}}
    <div class="d-flex align-items-center gap-2 mb-3">
        @foreach(['buyer'=>'My Purchases','seller'=>'My Sales'] as $r=>$l)
            <a href="{{ route('dashboard.orders',['role'=>$r,'tab'=>request('tab','all')]) }}"
               class="{{ request('role','buyer')===$r ? 'btn-primary btn-sm' : 'btn-outline btn-sm' }}">{{ $l }}</a>
        @endforeach
    </div>

    {{-- Status tabs --}}
    @php $statuses=['all'=>'All','pending_payment'=>'Pending','delivery_pending'=>'Delivery Pending','delivered'=>'Delivered','completed'=>'Completed','disputed'=>'Disputed','refunded'=>'Refunded']; $tab=request('tab','all'); @endphp
    <div class="tabs overflow-x-auto text-nowrap mb-3">
        @foreach($statuses as $v=>$l)
            <a href="{{ route('dashboard.orders',['role'=>request('role','buyer'),'tab'=>$v]) }}" class="tab {{ $tab===$v?'tab-active':'' }}">{{ $l }}</a>
        @endforeach
    </div>

    @if($orders->isEmpty())
        <x-empty-state icon="{{ request('role','buyer')==='seller' ? '📦' : '🛍️' }}" title="{{ request('role','buyer')==='seller' ? 'No sales yet' : 'No purchases yet' }}">
            @if(request('role','buyer')==='buyer')<x-slot:slot><a href="{{ route('marketplace.index') }}" class="btn-outline mt-3">Browse marketplace</a></x-slot:slot>@endif
        </x-empty-state>
    @else
        {{-- Desktop table --}}
        <div class="table-wrap d-none d-sm-block">
            <table class="table">
                <thead><tr>
                    <th>Order</th><th>Asset</th>
                    <th>{{ request('role','buyer')==='seller' ? 'Buyer' : 'Seller' }}</th>
                    <th>Qty</th><th>Total</th><th>Status</th><th>Payment</th><th>Date</th><th></th>
                </tr></thead>
                <tbody>
                @foreach($orders as $o)
                    <tr>
                        <td class="font-mono fs-xs text-muted">{{ $o->order_number }}</td>
                        <td class="fw-medium max-w-[160px] text-truncate">{{ $o->asset->title }}</td>
                        <td class="fs-sm">{{ request('role','buyer')==='seller' ? $o->buyer->name : $o->seller->name }}</td>
                        <td>{{ $o->quantity }}</td>
                        <td class="money fw-semibold">{{ \App\Support\Money::format($o->buyer_total) }}</td>
                        <td><x-status-badge :status="$o->status->value" /></td>
                        <td><x-status-badge :status="$o->payment_status" /></td>
                        <td class="fs-xs text-muted">{{ $o->created_at->format('d M Y') }}</td>
                        <td><a href="{{ route('dashboard.orders.show',$o) }}" class="btn-ghost btn-sm">View</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{-- Mobile --}}
        <div class="d-sm-none vstack gap-2">
        @foreach($orders as $o)
            <a href="{{ route('dashboard.orders.show',$o) }}" class="card-p d-block">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="">
                        <p class="fw-semibold text-dark text-truncate">{{ $o->asset->title }}</p>
                        <p class="fs-xs font-mono text-muted mt-1">{{ $o->order_number }}</p>
                    </div>
                    <x-status-badge :status="$o->status->value" />
                </div>
                <div class="d-flex justify-content-between mt-2 fs-sm">
                    <span class="text-muted">{{ $o->created_at->format('d M Y') }}</span>
                    <x-money :amount="$o->buyer_total" class="fw-bold text-dark" />
                </div>
            </a>
        @endforeach
        </div>
        <div class="mt-3">{{ $orders->withQueryString()->links() }}</div>
    @endif
</x-layouts.dashboard>
