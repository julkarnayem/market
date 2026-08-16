<x-layouts.admin title="Support Tickets" heading="Support Tickets">
    <div class="d-flex flex-wrap gap-2 mb-3">
        <div class="tabs">
            @foreach(['open'=>'Open','in_progress'=>'In Progress','waiting_for_user'=>'Waiting','resolved'=>'Resolved','closed'=>'Closed','all'=>'All'] as $k=>$l)
                <a href="{{ route('admin.tickets',['status'=>$k]) }}" class="tab {{ request('status','open')===$k?'tab-active':'' }}">{{ $l }}</a>
            @endforeach
        </div>
        <form method="GET" class="d-flex gap-2 ms-auto flex-wrap">
            <input type="hidden" name="status" value="{{ request('status','open') }}">
            <input name="q" value="{{ request('q') }}" placeholder="Search tickets…" class="input max-w-xs">
            <select name="priority" class="select w-auto" onchange="this.form.submit()">
                <option value="">All priorities</option>
                @foreach(['low','normal','high','urgent'] as $p)
                    <option value="{{ $p }}" @selected(request('priority')===$p)>{{ ucfirst($p) }}</option>
                @endforeach
            </select>
            <x-button type="submit" variant="outline">Filter</x-button>
        </form>
    </div>

    @if($tickets->isEmpty())
        <x-empty-state icon="🎧" title="No tickets">No support tickets match your filters.</x-empty-state>
    @else
        <div class="table-wrap d-none d-sm-block">
            <table class="table">
                <thead><tr><th>Ref</th><th>User</th><th>Subject</th><th>Priority</th><th>Status</th><th>Assigned</th><th>Last reply</th><th></th></tr></thead>
                <tbody>
                @foreach($tickets as $t)
                    <tr>
                        <td class="font-mono fs-xs text-muted">{{ $t->reference }}</td>
                        <td class="fs-sm">{{ $t->user->name }}</td>
                        <td class="fw-medium max-w-[200px] text-truncate">{{ $t->subject }}</td>
                        <td><span class="badge-{{ $t->priorityColor() }} text-xs">{{ ucfirst($t->priority) }}</span></td>
                        <td><x-status-badge :status="$t->status->value" /></td>
                        <td class="fs-sm text-muted">{{ $t->assignee?->name ?? '—' }}</td>
                        <td class="fs-xs text-muted">{{ $t->last_reply_at?->diffForHumans() ?? '—' }}</td>
                        <td><a href="{{ route('admin.tickets.show',$t) }}" class="btn-ghost btn-sm">View</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="d-sm-none vstack gap-2">
        @foreach($tickets as $t)
            <a href="{{ route('admin.tickets.show',$t) }}" class="card-p d-block">
                <div class="d-flex justify-content-between gap-2">
                    <div><p class="fw-semibold text-dark text-truncate">{{ $t->subject }}</p>
                        <p class="fs-xs text-muted">{{ $t->user->name }} · {{ $t->reference }}</p></div>
                    <x-status-badge :status="$t->status->value" />
                </div>
                <div class="d-flex justify-content-between mt-1 fs-xs text-secondary">
                    <span class="badge-{{ $t->priorityColor() }}">{{ ucfirst($t->priority) }}</span>
                    <span>{{ $t->last_reply_at?->diffForHumans() }}</span>
                </div>
            </a>
        @endforeach
        </div>
        <div class="mt-3">{{ $tickets->withQueryString()->links() }}</div>
    @endif
</x-layouts.admin>
