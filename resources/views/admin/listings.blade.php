<x-layouts.admin title="Listings" heading="Listings">
    @php $tab=request('tab','pending_review'); @endphp
    <div class="tabs overflow-x-auto text-nowrap mb-3">
        @foreach(['pending_review'=>'Pending','published'=>'Published','pending_edit_approval'=>'Edit Pending','rejected'=>'Rejected','suspended'=>'Suspended'] as $k=>$l)
            <a href="{{ route('admin.listings',['tab'=>$k]) }}" class="tab {{ $tab===$k?'tab-active':'' }}">{{ $l }}</a>
        @endforeach
    </div>
    <div class="table-wrap d-none d-sm-block">
        <table class="table">
            <thead><tr><th>Asset</th><th>Seller</th><th>Price</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($listings as $l)
                <tr>
                    <td class="fw-medium max-w-xs text-truncate">{{ $l->title }}</td>
                    <td>{{ $l->seller->name }}</td>
                    <td class="money">{{ \App\Support\Money::format($l->price) }}</td>
                    <td><x-status-badge :status="$l->status->value" /></td>
                    <td class="text-muted fs-xs">{{ $l->created_at->format('d M Y') }}</td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="{{ route('marketplace.show',$l->slug) }}" class="btn-ghost btn-sm">View</a>
                            @if($tab==='pending_review')
                                @can('listings.approve')
                                    <form method="POST" action="{{ route('admin.listings.approve',$l) }}">@csrf<x-button type="submit" variant="success" size="sm">Approve</x-button></form>
                                    <form method="POST" action="{{ route('admin.listings.reject',$l) }}">@csrf<x-button type="submit" variant="danger" size="sm">Reject</x-button></form>
                                @endcan
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-4 text-muted">No listings in this tab.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $listings->withQueryString()->links() }}</div>
</x-layouts.admin>
