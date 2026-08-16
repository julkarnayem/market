<x-layouts.admin title="Users" heading="Users">
    <div class="d-flex flex-column flex-sm-row align-items-start align-sm-items-center gap-3 mb-3">
        <form method="GET" class="d-flex gap-2 flex-grow-1">
            <div class="position-relative flex-grow-1 max-w-sm"><span class="position-absolute text-secondary">⌕</span><input name="q" value="{{ request('q') }}" placeholder="Search users…" class="input ps-4"></div>
            <select name="status" class="select w-auto">
                <option value="">All status</option>
                <option value="active" @selected(request('status')==='active')>Active</option>
                <option value="suspended" @selected(request('status')==='suspended')>Suspended</option>
                <option value="restricted" @selected(request('status')==='restricted')>Restricted</option>
            </select>
            <select name="verification" class="select w-auto">
                <option value="">Verification</option>
                <option value="approved" @selected(request('verification')==='approved')>Verified</option>
                <option value="pending" @selected(request('verification')==='pending')>Pending</option>
                <option value="not_submitted" @selected(request('verification')==='not_submitted')>Not submitted</option>
            </select>
            <x-button type="submit" variant="outline">Filter</x-button>
        </form>
    </div>

    <div class="table-wrap d-none d-sm-block">
        <table class="table">
            <thead><tr><th>User</th><th>Status</th><th>Verification</th><th>Joined</th><th>Last login</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($users as $u)
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <span class="h-8 w-8 d-grid place-items-center rounded-pill bg-primary bg-opacity-25 text-primary fs-sm fw-semibold flex-shrink-0">{{ strtoupper(substr($u->name,0,1)) }}</span>
                            <div><p class="fw-medium text-dark">{{ $u->name }}</p><p class="fs-xs text-muted">{{ $u->email }}</p></div>
                        </div>
                    </td>
                    <td><x-status-badge :status="$u->status->value" /></td>
                    <td><x-status-badge :status="$u->verification_status->value" /></td>
                    <td class="text-muted fs-xs">{{ $u->created_at->format('d M Y') }}</td>
                    <td class="text-muted fs-xs">{{ $u->last_login_at?->diffForHumans() ?? '—' }}</td>
                    <td class="text-end">
                        <a href="{{ route('admin.users.show',$u) }}" class="btn-ghost btn-sm">View</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-4 text-muted">No users found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="d-sm-none vstack gap-2">
    @foreach($users as $u)
        <a href="{{ route('admin.users.show',$u) }}" class="card-p d-flex align-items-center gap-3">
            <span class="h-10 w-10 d-grid place-items-center rounded-pill bg-primary bg-opacity-25 text-primary fw-semibold flex-shrink-0">{{ strtoupper(substr($u->name,0,1)) }}</span>
            <div class="flex-grow-1">
                <p class="fw-semibold text-dark text-truncate">{{ $u->name }}</p>
                <p class="fs-xs text-muted text-truncate">{{ $u->email }}</p>
            </div>
            <x-status-badge :status="$u->status->value" />
        </a>
    @endforeach
    </div>
    <div class="mt-3">{{ $users->withQueryString()->links() }}</div>
</x-layouts.admin>
