<x-layouts.admin title="Message Reports" heading="Message Reports">
    <div class="d-flex gap-2 mb-3">
        @foreach(['pending'=>'Pending','reviewed'=>'Reviewed','dismissed'=>'Dismissed','actioned'=>'Actioned','all'=>'All'] as $k=>$l)
            <a href="{{ route('admin.message-reports',['status'=>$k]) }}" class="tab {{ request('status','pending')===$k?'tab-active':'' }}">{{ $l }}</a>
        @endforeach
    </div>
    <div class="table-wrap d-none d-sm-block">
        <table class="table">
            <thead><tr><th>Reporter</th><th>Message</th><th>Sender</th><th>Reason</th><th>Order</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($reports as $r)
                <tr>
                    <td class="fs-sm">{{ $r->reporter->name }}</td>
                    <td class="fs-xs text-muted max-w-[180px] text-truncate">{{ e(Str::limit($r->message?->body, 60)) }}</td>
                    <td class="fs-sm">{{ $r->message?->sender?->name }}</td>
                    <td><span class="badge-rose fs-xs">{{ ucfirst($r->reason) }}</span></td>
                    <td class="font-mono fs-xs text-primary">
                        @if($r->message?->conversation?->order)
                            <a href="{{ route('admin.orders.show', $r->message->conversation->order) }}" class="">{{ $r->message->conversation->order->order_number }}</a>
                        @else —@endif
                    </td>
                    <td><x-status-badge :status="$r->status" /></td>
                    <td class="fs-xs text-muted">{{ $r->created_at->format('d M Y') }}</td>
                    <td>
                        @if($r->status === 'pending')
                            <form method="POST" action="{{ route('admin.message-reports.review',$r) }}" class="d-flex gap-1">
                                @csrf
                                <select name="action" class="select fs-xs w-32">
                                    <option value="dismiss">Dismiss</option>
                                    <option value="delete_message">Delete message</option>
                                </select>
                                <x-button type="submit" size="sm" variant="outline">Review</x-button>
                            </form>
                        @else
                            <span class="fs-xs text-secondary">Reviewed</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center py-4 text-muted">No reports.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $reports->withQueryString()->links() }}</div>
</x-layouts.admin>
