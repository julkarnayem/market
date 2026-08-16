<x-layouts.admin title="Offers" heading="Offer Management">
    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <select name="status" class="select w-auto" onchange="this.form.submit()">
                <option value="all" @selected(request('status','all')==='all')>All</option>
                @foreach(['pending','accepted','rejected','expired'] as $s)
                    <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </form>
    </div>
    <div class="table-wrap d-none d-sm-block">
        <table class="table">
            <thead><tr><th>#</th><th>Asset</th><th>Buyer</th><th>Seller</th><th>Amount</th><th>Status</th><th>Expires</th><th>Created</th></tr></thead>
            <tbody>
            @forelse($offers as $o)
                <tr>
                    <td class="text-secondary font-mono fs-xs">#{{ $o->id }}</td>
                    <td class="fw-medium max-w-[140px] text-truncate">{{ $o->asset->title }}</td>
                    <td class="fs-sm">{{ $o->buyer->name }}</td>
                    <td class="fs-sm">{{ $o->seller->name }}</td>
                    <td class="money fw-semibold">{{ \App\Support\Money::format($o->amount) }}</td>
                    <td><x-status-badge :status="$o->status->value" /></td>
                    <td class="fs-xs text-muted">{{ $o->expires_at->format('d M, H:i') }}</td>
                    <td class="fs-xs text-muted">{{ $o->created_at->format('d M Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center py-4 text-muted">No offers found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="d-sm-none vstack gap-2">
    @foreach($offers as $o)
        <div class="card-p fs-sm">
            <div class="d-flex justify-content-between gap-2">
                <p class="fw-medium text-truncate">{{ $o->asset->title }}</p>
                <x-status-badge :status="$o->status->value" />
            </div>
            <div class="d-flex justify-content-between mt-2">
                <span class="text-muted">{{ $o->buyer->name }}</span>
                <x-money :amount="$o->amount" class="fw-bold" />
            </div>
        </div>
    @endforeach
    </div>
    <div class="mt-3">{{ $offers->withQueryString()->links() }}</div>
</x-layouts.admin>
