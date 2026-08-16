<x-layouts.admin title="Payments" heading="Payment Records">
    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <input name="q" value="{{ request('q') }}" placeholder="Order number…" class="input max-w-xs">
            <select name="status" class="select w-auto" onchange="this.form.submit()">
                <option value="all" @selected(request('status','all')==='all')>All status</option>
                @foreach(['pending','paid','failed','cancelled','refunded'] as $s)
                    <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <x-button type="submit" variant="outline">Filter</x-button>
        </form>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>ID</th><th>Order #</th><th>Buyer</th><th>Amount</th><th>Gateway</th><th>TXN ID</th><th>Status</th><th>Paid at</th></tr></thead>
            <tbody>
            @forelse($payments as $p)
                <tr>
                    <td class="font-mono fs-xs text-muted">#{{ $p->id }}</td>
                    <td class="font-mono fs-xs"><a href="{{ route('admin.orders.show',$p->order) }}" class="text-primary">{{ $p->order->order_number }}</a></td>
                    <td class="fs-sm">{{ $p->order->buyer->name }}</td>
                    <td class="money fw-semibold">{{ \App\Support\Money::format($p->amount) }}</td>
                    <td>{{ $p->gateway }}</td>
                    <td class="font-mono fs-xs text-muted max-w-[100px] text-truncate">{{ $p->gateway_transaction_id ?? '—' }}</td>
                    <td><x-status-badge :status="$p->status" /></td>
                    <td class="fs-xs text-muted">{{ $p->paid_at?->format('d M Y, H:i') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center py-4 text-muted">No payments found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $payments->withQueryString()->links() }}</div>
</x-layouts.admin>
