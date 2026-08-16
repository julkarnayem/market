<x-layouts.admin title="Promotions" heading="Promotion Management">
    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        <div class="tabs">
            @foreach(['active'=>'Active','expired'=>'Expired','cancelled'=>'Cancelled','all'=>'All'] as $k=>$l)
                <a href="{{ route('admin.promotions',['status'=>$k]) }}" class="tab {{ request('status','active')===$k?'tab-active':'' }}">{{ $l }}</a>
            @endforeach
        </div>
    </div>
    <div class="table-wrap d-none d-sm-block">
        <table class="table">
            <thead><tr><th>Listing</th><th>Seller</th><th>Type</th><th>Days</th><th>Amount</th><th>Start</th><th>End</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($promotions as $p)
                <tr>
                    <td class="fw-medium max-w-[140px] text-truncate">{{ $p->asset->title }}</td>
                    <td class="fs-sm">{{ $p->seller?->name }}</td>
                    <td><span class="badge-{{ $p->is_manual?'brand':'mint' }} text-xs">{{ $p->is_manual?'Admin':'Paid' }}</span></td>
                    <td>{{ $p->days ?: '—' }}</td>
                    <td class="money">{{ $p->price > 0 ? \App\Support\Money::format($p->price) : '—' }}</td>
                    <td class="fs-xs text-muted">{{ $p->starts_at?->format('d M Y, H:i') }}</td>
                    <td class="fs-xs text-muted">{{ $p->ends_at?->format('d M Y, H:i') }}</td>
                    <td><x-status-badge :status="$p->status" /></td>
                    <td>
                        @if($p->status==='active')
                            <form method="POST" action="{{ route('admin.promotions.unfeature',$p) }}">@csrf
                                <x-button type="submit" variant="danger" size="sm" onclick="return confirm('End this promotion?')">End</x-button>
                            </form>
                        @else<span class="text-slate-300 fs-xs">—</span>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center py-4 text-muted">No promotions.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="d-sm-none vstack gap-2">
    @foreach($promotions as $p)
        <div class="card-p">
            <div class="d-flex justify-content-between gap-2"><p class="fw-semibold text-truncate">{{ $p->asset->title }}</p><x-status-badge :status="$p->status" /></div>
            <div class="d-flex justify-content-between mt-1 fs-xs text-muted">
                <span>{{ $p->seller?->name }}</span>
                <x-money :amount="$p->price" />
            </div>
        </div>
    @endforeach
    </div>
    <div class="mt-3">{{ $promotions->withQueryString()->links() }}</div>
</x-layouts.admin>
