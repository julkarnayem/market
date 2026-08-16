<x-layouts.dashboard title="Wallet" heading="My Wallet">
    {{-- Balance cards --}}
    <div class="row row-cols-2 row-cols-4 gap-3 mb-4">
        <div class="stat-card bg-success bg-opacity-10">
            <p class="stat-label text-success">Available</p>
            <p class="stat-value text-success money">{{ \App\Support\Money::format($wallet?->available_balance ?? 0) }}</p>
            <a href="{{ route('dashboard.withdrawals') }}" class="fs-xs text-success mt-1 d-inline-block">Withdraw →</a>
        </div>
        <div class="stat-card bg-warning bg-opacity-10">
            <p class="stat-label text-warning">Pending (locked)</p>
            <p class="stat-value text-warning money">{{ \App\Support\Money::format($totalPending) }}</p>
            <p class="fs-xs text-warning mt-1">Released 8h after order</p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Total earned</p>
            <p class="stat-value money">{{ \App\Support\Money::format($totalEarned) }}</p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Total withdrawn</p>
            <p class="stat-value money">{{ \App\Support\Money::format($totalWithdrawn) }}</p>
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="section-title">Transaction History</h2>
        <a href="{{ route('dashboard.withdrawals') }}" class="btn-outline btn-sm">Request withdrawal</a>
    </div>

    @if($transactions->isEmpty())
        <x-empty-state icon="💳" title="No transactions yet">
            Your wallet activity — earnings, purchases and withdrawals — will appear here.
        </x-empty-state>
    @else
        <div class="table-wrap d-none d-sm-block">
            <table class="table">
                <thead><tr><th>Date</th><th>Type</th><th>Description</th><th class="text-end">Amount</th><th class="text-end">Balance after</th></tr></thead>
                <tbody>
                @foreach($transactions as $tx)
                    <tr>
                        <td class="text-muted fs-xs">{{ $tx->created_at->format('d M Y, H:i') }}</td>
                        <td><span class="badge-{{ $tx->amount > 0 ? 'mint' : 'rose' }} text-xs">{{ ucwords(str_replace('_',' ',$tx->type instanceof \App\Enums\TransactionType ? $tx->type->value : $tx->type)) }}</span></td>
                        <td class="fs-sm text-muted max-w-xs text-truncate">{{ $tx->description ?? '—' }}</td>
                        <td class="text-right money font-semibold {{ $tx->amount > 0 ? 'text-mint-700' : 'text-rose-600' }}">
                            {{ $tx->amount > 0 ? '+' : '' }}{{ \App\Support\Money::format($tx->amount) }}
                        </td>
                        <td class="text-end money text-muted">{{ \App\Support\Money::format($tx->available_after) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="d-sm-none vstack gap-2">
        @foreach($transactions as $tx)
            <div class="card-p">
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <div>
                        <span class="badge-{{ $tx->amount > 0 ? 'mint' : 'rose' }} text-xs">{{ ucwords(str_replace('_',' ',$tx->type instanceof \App\Enums\TransactionType ? $tx->type->value : $tx->type)) }}</span>
                        <p class="fs-xs text-muted mt-1">{{ $tx->created_at->format('d M Y') }}</p>
                    </div>
                    <span class="money font-bold {{ $tx->amount > 0 ? 'text-mint-700' : 'text-rose-600' }}">
                        {{ $tx->amount > 0 ? '+' : '' }}{{ \App\Support\Money::format($tx->amount) }}
                    </span>
                </div>
                @if($tx->description)<p class="fs-xs text-muted mt-1">{{ $tx->description }}</p>@endif
            </div>
        @endforeach
        </div>
        <div class="mt-3">{{ $transactions->withQueryString()->links() }}</div>
    @endif
</x-layouts.dashboard>
