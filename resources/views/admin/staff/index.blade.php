<x-layouts.admin title="Staff Management" heading="Staff Management">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <p class="section-sub">Manage admin, moderator, support and finance staff accounts.</p>
        <a href="{{ route('admin.staff.create') }}" class="btn-primary btn-sm">+ Add Staff</a>
    </div>
    <div class="table-wrap d-none d-sm-block">
        <table class="table">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($staff as $s)
                <tr>
                    <td class="fw-medium">{{ $s->name }}</td>
                    <td class="fs-sm text-muted">{{ $s->email }}</td>
                    <td>
                        @foreach($s->roles as $role)
                            <span class="badge-brand fs-xs">{{ $role->display_name }}</span>
                        @endforeach
                    </td>
                    <td><x-status-badge :status="$s->status->value" /></td>
                    <td class="fs-xs text-muted">{{ $s->created_at->format('d M Y') }}</td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="{{ route('admin.staff.show',$s) }}" class="btn-ghost btn-sm">View</a>
                            @if($s->id !== auth()->id())
                                @if($s->status->value === 'active')
                                    <form method="POST" action="{{ route('admin.staff.suspend',$s) }}">@csrf
                                        <x-button type="submit" variant="danger" size="sm" onclick="return confirm('Suspend {{ $s->name }}?')">Suspend</x-button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.staff.restore',$s) }}">@csrf
                                        <x-button type="submit" variant="success" size="sm">Restore</x-button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-4 text-muted">No staff accounts found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="d-sm-none vstack gap-2">
    @foreach($staff as $s)
        <div class="card-p">
            <div class="d-flex justify-content-between gap-2">
                <div><p class="fw-semibold">{{ $s->name }}</p><p class="fs-xs text-muted">{{ $s->email }}</p></div>
                <x-status-badge :status="$s->status->value" />
            </div>
            <div class="d-flex gap-1 mt-2 flex-wrap">
                @foreach($s->roles as $r)<span class="badge-brand fs-xs">{{ $r->display_name }}</span>@endforeach
            </div>
        </div>
    @endforeach
    </div>
    <div class="mt-3">{{ $staff->links() }}</div>
</x-layouts.admin>
