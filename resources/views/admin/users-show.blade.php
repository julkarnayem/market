<x-layouts.admin :title="$user->name" :heading="$user->name">
    <x-breadcrumb :items="[['label'=>'Users','url'=>route('admin.users')],['label'=>$user->name]]" />
    <div class="grid-cols-[1fr_18rem] gap-4">
        <div class="vstack gap-3">
            <x-card>
                <h2 class="section-title mb-3">Profile</h2>
                <dl class="row row-cols-2 gap-3 fs-sm">
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted mb-1">Name</dt><dd class="fw-medium">{{ $user->name }}</dd></div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted mb-1">Username</dt><dd class="fw-medium">{{ $user->username }}</dd></div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted mb-1">Email</dt><dd class="fw-medium">{{ $user->email }}</dd></div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted mb-1">Phone</dt><dd class="fw-medium">{{ $user->phone ?? '—' }}</dd></div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted mb-1">Joined</dt><dd class="fw-medium">{{ $user->created_at->format('d M Y') }}</dd></div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted mb-1">Last login</dt><dd class="fw-medium">{{ $user->last_login_at?->diffForHumans() ?? '—' }}</dd></div>
                </dl>
            </x-card>
            <x-card>
                <h2 class="section-title mb-2">Wallet Summary</h2>
                <div class="row row-cols-2 gap-3">
                    <div class="rounded-3 bg-success bg-opacity-10 p-2"><dt class="fs-xs text-muted">Available</dt><dd class="money fw-bold text-success">{{ \App\Support\Money::format($user->wallet?->available_balance??0) }}</dd></div>
                    <div class="rounded-3 bg-warning bg-opacity-10 p-2"><dt class="fs-xs text-muted">Pending</dt><dd class="money fw-bold text-warning">{{ \App\Support\Money::format($user->wallet?->pending_balance??0) }}</dd></div>
                </div>
            </x-card>
        </div>
        <div class="vstack gap-3">
            <x-card>
                <h2 class="section-title mb-2">Status</h2>
                <x-status-badge :status="$user->status->value" class="mb-2" />
                <x-status-badge :status="$user->verification_status->value" />
                @can('users.edit')
                    <div class="mt-3 pt-3 border-top border-light vstack gap-2">
                        @if($user->status->value==='active')
                            <form method="POST" action="{{ route('admin.users.suspend',$user) }}">@csrf<x-button type="submit" variant="danger" class="w-100" size="sm">Suspend user</x-button></form>
                        @else
                            <form method="POST" action="{{ route('admin.users.restore',$user) }}">@csrf<x-button type="submit" variant="success" class="w-100" size="sm">Restore account</x-button></form>
                        @endif
                    </div>
                @endcan
            </x-card>
            <x-card>
                <p class="fs-sm text-muted">Listings: <strong>{{ $user->listings()->count() }}</strong></p>
                <p class="fs-sm text-muted mt-1">Purchases: <strong>{{ $user->purchases()->count() }}</strong></p>
                <p class="fs-sm text-muted mt-1">Sales: <strong>{{ $user->sales()->count() }}</strong></p>
            </x-card>
        </div>
    </div>
</x-layouts.admin>
