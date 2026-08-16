<x-layouts.admin title="Withdrawals" heading="Withdrawal Requests">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <form method="GET" class="d-flex flex-wrap gap-2">
            <input name="q" value="{{ request('q') }}" placeholder="User name or email…" class="input max-w-xs">
            <select name="status" class="select w-auto" onchange="this.form.submit()">
                @foreach(['pending'=>'Pending','approved'=>'Approved','completed'=>'Completed','rejected'=>'Rejected','all'=>'All'] as $k=>$l)
                    <option value="{{ $k }}" @selected(request('status','pending')===$k)>{{ $l }}</option>
                @endforeach
            </select>
            <x-button type="submit" variant="outline">Filter</x-button>
        </form>
    </div>
    <div class="table-wrap d-none d-sm-block">
        <table class="table">
            <thead><tr><th>#</th><th>User</th><th>Amount</th><th>Fee</th><th>Net</th><th>Provider</th><th>Account</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($withdrawals as $w)
                <tr>
                    <td class="font-mono fs-xs text-muted">#{{ $w->id }}</td>
                    <td>
                        <div><p class="fs-sm fw-medium text-dark">{{ $w->user->name }}</p>
                        <p class="fs-xs text-muted">{{ $w->user->email }}</p></div>
                    </td>
                    <td class="money fw-semibold">{{ \App\Support\Money::format($w->amount) }}</td>
                    <td class="money text-danger fs-xs">{{ \App\Support\Money::format($w->fee) }}</td>
                    <td class="money fw-bold text-success">{{ \App\Support\Money::format($w->net_amount) }}</td>
                    <td class="text-uppercase fs-xs fw-semibold">{{ $w->mfs_provider }}</td>
                    <td class="font-mono fs-xs">{{ $w->maskedNumber() }}</td>
                    <td><x-status-badge :status="$w->status->value" /></td>
                    <td class="fs-xs text-muted">{{ $w->created_at->format('d M Y') }}</td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            @if($w->status->value==='pending')
                                <form method="POST" action="{{ route('admin.withdrawals.approve',$w) }}">@csrf
                                    <x-button type="submit" variant="success" size="sm">Approve</x-button>
                                </form>
                                <form method="POST" action="{{ route('admin.withdrawals.reject',$w) }}" class="d-flex gap-1">@csrf
                                    <input name="reason" placeholder="Reason" class="input fs-xs py-1 w-32" required>
                                    <x-button type="submit" variant="danger" size="sm">Reject</x-button>
                                </form>
                            @elseif($w->status->value==='approved')
                                <form method="POST" action="{{ route('admin.withdrawals.complete',$w) }}" class="d-flex gap-1">@csrf
                                    <input name="external_reference" placeholder="TXN ref (optional)" class="input fs-xs py-1 w-32">
                                    <x-button type="submit" variant="mint" size="sm">Mark paid</x-button>
                                </form>
                            @else
                                <span class="text-slate-300 fs-xs">—</span>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center py-4 text-muted">No withdrawals found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="d-sm-none vstack gap-2">
    @foreach($withdrawals as $w)
        <div class="card-p vstack gap-2">
            <div class="d-flex justify-content-between"><div><p class="fw-semibold">{{ $w->user->name }}</p><p class="fs-xs text-muted">{{ strtoupper($w->mfs_provider) }} · {{ $w->maskedNumber() }}</p></div><x-status-badge :status="$w->status->value" /></div>
            <div class="d-flex justify-content-between"><x-money :amount="$w->net_amount" class="fw-bold text-success" /><span class="fs-xs text-muted">{{ $w->created_at->format('d M Y') }}</span></div>
        </div>
    @endforeach
    </div>
    <div class="mt-3">{{ $withdrawals->withQueryString()->links() }}</div>
</x-layouts.admin>
