<x-layouts.admin title="Roles" heading="Role Management">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <p class="section-sub">Manage staff roles and their permissions.</p>
    </div>
    {{-- Create new role --}}
    <x-card class="mb-3">
        <h2 class="section-title mb-2">Create New Role</h2>
        <form method="POST" action="{{ route('admin.roles.store') }}" class="d-flex flex-wrap gap-3 align-items-end">
            @csrf
            <div class="flex-grow-1 min-w-40"><label class="label fs-xs">Name (slug)</label>
                <input name="name" class="input fs-sm" placeholder="e.g. content_manager" pattern="[a-z_]+" required></div>
            <div class="flex-grow-1 min-w-40"><label class="label fs-xs">Display Name</label>
                <input name="display_name" class="input fs-sm" placeholder="Content Manager" required></div>
            <div class="flex-grow-1 min-w-40"><label class="label fs-xs">Description</label>
                <input name="description" class="input fs-sm" placeholder="Optional"></div>
            <x-button type="submit">Create role</x-button>
        </form>
    </x-card>

    <div class="vstack gap-2">
    @foreach($roles as $role)
        <div class="card-p">
            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <h3 class="fw-semibold text-dark">{{ $role->display_name }}</h3>
                        @if($role->is_protected)<span class="badge-slate fs-xs">Protected</span>@endif
                        <span class="fs-xs text-muted">{{ $role->users_count }} member(s)</span>
                    </div>
                    <p class="fs-xs text-muted mt-1">Slug: <code>{{ $role->name }}</code> @if($role->description)· {{ $role->description }}@endif</p>
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        @foreach($role->permissions->sortBy('name') as $p)
                            <span class="badge-slate">{{ $p->name }}</span>
                        @endforeach
                    </div>
                </div>
                @if(!$role->is_protected)
                    <a href="{{ route('admin.roles.edit',$role) }}" class="btn-outline btn-sm flex-shrink-0">Edit permissions</a>
                @endif
            </div>
        </div>
    @endforeach
    </div>
</x-layouts.admin>
