<x-layouts.admin title="Wallets" heading="Wallet Overview">
    <form method="GET" class="d-flex gap-2 mb-3 max-w-sm">
        <input name="q" value="{{ request('q') }}" placeholder="Search user…" class="input">
        <x-button type="submit" variant="outline">Filter</x-button>
    </form>
    <div class="table-wrap d-none d-sm-block">
        <table class="table">
            <thead><tr><th>User</th><th>Available</th><th>Pending</th><th>Total</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($wallets as $w)
                <tr>
                    <td><p class="fw-medium text-dark">{{ $w->user->name }}</p><p class="fs-xs text-muted">{{ $w->user->email }}</p></td>
                    <td class="money fw-semibold text-success">{{ \App\Support\Money::format($w->available_balance) }}</td>
                    <td class="money text-warning">{{ \App\Support\Money::format($w->pending_balance) }}</td>
                    <td class="money fw-bold">{{ \App\Support\Money::format($w->totalBalance()) }}</td>
                    <td><a href="{{ route('admin.wallets.show',$w) }}" class="btn-ghost btn-sm">View</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center py-4 text-muted">No wallets.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $wallets->withQueryString()->links() }}</div>
</x-layouts.admin>
