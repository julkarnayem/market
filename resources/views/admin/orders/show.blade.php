<x-layouts.admin :title="'Order '.$order->order_number" heading="Order Details">
    <x-breadcrumb :items="[['label'=>'Orders','url'=>route('admin.orders')],['label'=>$order->order_number]]" />
    <div class="grid-cols-[1fr_20rem] gap-4">
        <div class="vstack gap-3">
            <x-card>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <x-status-badge :status="$order->status->value" />
                    <x-status-badge :status="$order->payment_status" />
                </div>
                <dl class="row row-cols-2 gap-3 fs-sm">
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Order #</dt><dd class="font-mono fw-medium">{{ $order->order_number }}</dd></div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Asset</dt><dd class="fw-medium text-truncate">{{ $order->asset->title }}</dd></div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Buyer</dt><dd>{{ $order->buyer->name }}</dd></div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Seller</dt><dd>{{ $order->seller->name }}</dd></div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Qty</dt><dd>{{ $order->quantity }}</dd></div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Total paid</dt><dd class="money fw-bold">{{ \App\Support\Money::format($order->buyer_total) }}</dd></div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Seller earning</dt><dd class="money fw-bold text-success">{{ \App\Support\Money::format($order->seller_earning) }}</dd></div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Platform commission</dt><dd class="money">{{ \App\Support\Money::format($order->platform_commission) }}</dd></div>
                </dl>
            </x-card>
            @if($order->delivery)
                <x-card>
                    <h2 class="section-title mb-2">Delivery <span class="badge-rose ms-2">Admin-only view</span></h2>
                    <div class="rounded-3 bg-warning bg-opacity-10 p-3 fs-sm text-warning">{{ $order->delivery->delivery_note }}</div>
                </x-card>
            @endif
            <x-card>
                <h2 class="section-title mb-2">Timeline</h2>
                <ol class="position-relative border-start border-secondary border-opacity-25 ms-2 vstack gap-2">
                @foreach($order->statusHistory->sortBy('created_at') as $h)
                    <li class="ms-3">
                        <div class="position-absolute mt-1 h-3 w-3 rounded-pill border border-2 border-white bg-brand-500"></div>
                        <div class="d-flex justify-content-between gap-2">
                            <div><x-status-badge :status="$h->to_status" /><p class="fs-xs text-muted mt-1">{{ $h->note }}</p></div>
                            <span class="fs-xs text-secondary flex-shrink-0">{{ $h->created_at?->format('d M, H:i') }}</span>
                        </div>
                    </li>
                @endforeach
                </ol>
            </x-card>
        </div>
        <div class="vstack gap-3">
            @if($order->latestPayment)
                <x-card>
                    <h2 class="section-title mb-2">Payment</h2>
                    <dl class="fs-xs text-muted space-y-1.5">
                        <div class="d-flex justify-content-between"><dt>Gateway</dt><dd>{{ $order->latestPayment->gateway }}</dd></div>
                        <div class="d-flex justify-content-between"><dt>Status</dt><dd>{{ $order->latestPayment->status }}</dd></div>
                        <div class="d-flex justify-content-between"><dt>Amount</dt><dd class="money">{{ \App\Support\Money::format($order->latestPayment->amount) }}</dd></div>
                        @if($order->latestPayment->gateway_transaction_id)
                            <div class="d-flex justify-content-between"><dt>TXN ID</dt><dd class="font-mono text-truncate max-w-[120px]">{{ $order->latestPayment->gateway_transaction_id }}</dd></div>
                        @endif
                        <div class="d-flex justify-content-between"><dt>Paid at</dt><dd>{{ $order->paid_at?->format('d M Y, H:i') ?? '—' }}</dd></div>
                    </dl>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.admin>
