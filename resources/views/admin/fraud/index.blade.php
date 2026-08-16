<x-layouts.admin title="Fraud Review" heading="Fraud Risk Queue">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <p class="section-sub">Users flagged by the anti-fraud system. Review before taking action — scores are advisory.</p>
    </div>
    <div class="tabs mb-3">
        @foreach(['pending'=>'Pending','escalated'=>'Escalated','reviewing'=>'Reviewing','cleared'=>'Cleared','restricted'=>'Restricted','all'=>'All'] as $k=>$l)
            <a href="{{ route('admin.fraud',['status'=>$k]) }}" class="tab {{ request('status','pending')===$k?'tab-active':'' }}">{{ $l }}</a>
        @endforeach
    </div>
    <div class="table-wrap d-none d-sm-block">
        <table class="table">
            <thead><tr><th>User</th><th>Risk Score</th><th>Flags</th><th>Status</th><th>Reviewed by</th><th>Last updated</th><th></th></tr></thead>
            <tbody>
            @forelse($reviews as $r)
                <tr>
                    <td class="fw-medium">{{ $r->user->name }}<p class="fs-xs text-muted">{{ $r->user->email }}</p></td>
                    <td>
                        <span class="font-mono font-bold {{ $r->risk_score >= 70 ? 'text-rose-600' : ($r->risk_score >= 30 ? 'text-amber-600' : 'text-slate-500') }}">
                            {{ $r->risk_score }}
                        </span>
                    </td>
                    <td class="max-w-[200px]">
                        @foreach(($r->risk_flags ?? []) as $f)
                            <span class="badge-rose me-1">{{ str_replace('_',' ',$f) }}</span>
                        @endforeach
                    </td>
                    <td><x-status-badge :status="$r->status" /></td>
                    <td class="fs-sm text-muted">{{ $r->reviewer?->name ?? '—' }}</td>
                    <td class="fs-xs text-muted">{{ $r->updated_at->diffForHumans() }}</td>
                    <td><a href="{{ route('admin.fraud.show',$r->user) }}" class="btn-ghost btn-sm">Review</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center py-4 text-muted">No fraud cases in this queue.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $reviews->withQueryString()->links() }}</div>
</x-layouts.admin>
