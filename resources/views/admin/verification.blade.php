<x-layouts.admin title="Verification" heading="Seller Verification">
    @php $tab=request('tab','pending'); @endphp
    <div class="tabs mb-3">
        @foreach(['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $k=>$l)
            <a href="{{ route('admin.verification',['tab'=>$k]) }}" class="tab {{ $tab===$k?'tab-active':'' }}">{{ $l }}</a>
        @endforeach
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>User</th><th>Type</th><th>Submitted</th><th>Status</th><th>Reviewer</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($verifications as $v)
                <tr>
                    <td class="fw-medium">{{ $v->user->name }}</td>
                    <td class="text-capitalize">{{ match($v->document_type) { 'nid'=>'NID','passport'=>'Passport','dob'=>'Date of Birth','driving_license'=>'Driving License',default=>strtoupper($v->document_type) } }}</td>
                    <td class="text-muted fs-xs">{{ $v->created_at->format('d M Y') }}</td>
                    <td><x-status-badge :status="$v->status" /></td>
                    <td class="text-muted fs-xs">{{ $v->reviewer?->name ?? '—' }}</td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            <a href="{{ route('admin.verification.show',$v) }}" class="btn-ghost btn-sm">View</a>
                            @if($v->status==='pending')
                                @can('verification.review')
                                    <form method="POST" action="{{ route('admin.verification.approve',$v) }}">@csrf<x-button type="submit" variant="success" size="sm">✓ Approve</x-button></form>
                                    <form method="POST" action="{{ route('admin.verification.reject',$v) }}">@csrf<x-button type="submit" variant="danger" size="sm">✕ Reject</x-button></form>
                                @endcan
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-4 text-muted">No verification submissions.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $verifications->withQueryString()->links() }}</div>
</x-layouts.admin>
