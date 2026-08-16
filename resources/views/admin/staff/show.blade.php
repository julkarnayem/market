<x-layouts.admin :title="$user->name" heading="Staff Detail">
    <x-breadcrumb :items="[['label'=>'Staff','url'=>route('admin.staff')],['label'=>$user->name]]" />
    <div class="grid-cols-[1fr_20rem] gap-4">
        <div class="vstack gap-3">
            <x-card>
                <h2 class="section-title mb-3">Profile</h2>
                <dl class="row row-cols-2 gap-3 fs-sm">
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Name</dt><dd class="fw-medium">{{ $user->name }}</dd></div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Email</dt><dd>{{ $user->email }}</dd></div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Status</dt><dd><x-status-badge :status="$user->status->value" /></dd></div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Joined</dt><dd>{{ $user->created_at->format('d M Y') }}</dd></div>
                </dl>
            </x-card>
            <x-card>
                <h2 class="section-title mb-2">Permissions (via role)</h2>
                @foreach($user->roles as $role)
                    <p class="fs-sm fw-semibold text-dark mb-2">{{ $role->display_name }}</p>
                    <div class="d-flex flex-wrap gap-1 mb-3">
                        @foreach($role->permissions as $p)
                            <span class="badge-slate">{{ $p->name }}</span>
                        @endforeach
                    </div>
                @endforeach
            </x-card>
            <x-card>
                <h2 class="section-title mb-2">Recent Activity</h2>
                @if($logs->isEmpty())
                    <p class="fs-sm text-muted">No activity logged.</p>
                @else
                    <div class="vstack gap-2">
                    @foreach($logs as $log)
                        <div class="d-flex justify-content-between gap-2 fs-sm">
                            <code class="fs-xs bg-light px-2 py-1 rounded">{{ $log->action }}</code>
                            <span class="fs-xs text-secondary">{{ $log->created_at->format('d M, H:i') }}</span>
                        </div>
                    @endforeach
                    </div>
                @endif
            </x-card>
        </div>
        <div class="vstack gap-3">
            <x-card>
                <h2 class="section-title mb-2">Change Role</h2>
                @if($user->id !== auth()->id())
                    <form method="POST" action="{{ route('admin.staff.role',$user) }}" class="vstack gap-2">
                        @csrf
                        <select name="role_id" class="select fs-sm">
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" @selected($user->roles->contains($role))>{{ $role->display_name }}</option>
                            @endforeach
                        </select>
                        <x-button type="submit" variant="outline" class="w-100" size="sm" onclick="return confirm('Change this staff member role?')">Update role</x-button>
                    </form>
                    <div class="pt-3 border-top border-light mt-3">
                        @if($user->status->value === 'active')
                            <form method="POST" action="{{ route('admin.staff.suspend',$user) }}">@csrf
                                <x-button type="submit" variant="danger" class="w-100" size="sm" onclick="return confirm('Suspend this staff account?')">Suspend access</x-button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.staff.restore',$user) }}">@csrf
                                <x-button type="submit" variant="success" class="w-100" size="sm">Restore access</x-button>
                            </form>
                        @endif
                    </div>
                @else
                    <p class="fs-xs text-muted">You cannot modify your own account here.</p>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts.admin>
