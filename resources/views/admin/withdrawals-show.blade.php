<x-layouts.admin :title="'Withdrawal #'.$withdrawal->id" heading="Withdrawal Detail">
    <x-breadcrumb :items="[['label'=>'Withdrawals','url'=>route('admin.withdrawals')],['label'=>'#'.$withdrawal->id]]" />
    <div class="max-w-2xl vstack gap-3">
        <x-card>
            <h2 class="section-title mb-3">Withdrawal #{{ $withdrawal->id }}</h2>
            <dl class="row row-cols-2 gap-3 fs-sm mb-3">
                <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">User</dt><dd class="fw-medium">{{ $withdrawal->user->name }}</dd></div>
                <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Status</dt><dd><x-status-badge :status="$withdrawal->status->value" /></dd></div>
                <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Amount requested</dt><dd class="money fw-bold">{{ \App\Support\Money::format($withdrawal->amount) }}</dd></div>
                <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Withdrawal fee</dt><dd class="money text-danger">{{ \App\Support\Money::format($withdrawal->fee) }}</dd></div>
                <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Net payout</dt><dd class="money fw-bold text-success">{{ \App\Support\Money::format($withdrawal->net_amount) }}</dd></div>
                <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Provider</dt><dd class="text-uppercase fw-semibold">{{ $withdrawal->mfs_provider }}</dd></div>
                <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Account (masked)</dt><dd class="font-mono">{{ $withdrawal->maskedNumber() }}</dd></div>
                <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Requested</dt><dd>{{ $withdrawal->created_at->format('d M Y, H:i') }}</dd></div>
                @if($withdrawal->approved_at)<div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Approved at</dt><dd>{{ $withdrawal->approved_at->format('d M Y, H:i') }}</dd></div>@endif
                @if($withdrawal->rejected_at)<div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Rejected at</dt><dd>{{ $withdrawal->rejected_at->format('d M Y, H:i') }}</dd></div>@endif
                @if($withdrawal->processed_at)<div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Completed at</dt><dd>{{ $withdrawal->processed_at->format('d M Y, H:i') }}</dd></div>@endif
                @if($withdrawal->external_reference)<div class="rounded-3 bg-light p-2 col-span-2"><dt class="fs-xs text-muted">External reference</dt><dd class="font-mono">{{ $withdrawal->external_reference }}</dd></div>@endif
            </dl>
            @if($withdrawal->rejection_reason)
                <div class="rounded-3 bg-danger bg-opacity-10 p-2 fs-sm"><p class="fw-semibold text-rose-800">Rejection reason:</p><p class="text-danger">{{ $withdrawal->rejection_reason }}</p></div>
            @endif
        </x-card>

        {{-- User wallet info --}}
        @if($withdrawal->user->wallet)
            <x-card>
                <h2 class="section-title mb-2">User Wallet Balance</h2>
                <div class="row row-cols-2 gap-3 fs-sm">
                    <div class="rounded-3 bg-success bg-opacity-10 p-2"><p class="fs-xs text-success">Available</p><p class="money fw-bold text-success">{{ \App\Support\Money::format($withdrawal->user->wallet->available_balance) }}</p></div>
                    <div class="rounded-3 bg-warning bg-opacity-10 p-2"><p class="fs-xs text-warning">Pending</p><p class="money fw-bold text-warning">{{ \App\Support\Money::format($withdrawal->user->wallet->pending_balance) }}</p></div>
                </div>
            </x-card>
        @endif

        {{-- Actions --}}
        @can('withdrawals.approve')
            @if($withdrawal->status->value === 'pending')
                <div class="row row-cols-2 gap-3">
                    <x-card>
                        <h2 class="fs-sm fw-semibold text-dark mb-2">Approve</h2>
                        <form method="POST" action="{{ route('admin.withdrawals.approve',$withdrawal) }}">@csrf
                            <x-button type="submit" variant="success" class="w-100">Approve withdrawal</x-button>
                        </form>
                    </x-card>
                    <x-card>
                        <h2 class="fs-sm fw-semibold text-dark mb-2">Reject</h2>
                        <form method="POST" action="{{ route('admin.withdrawals.reject',$withdrawal) }}" class="vstack gap-2">@csrf
                            <input name="reason" required class="input fs-sm" placeholder="Reason for rejection…">
                            <x-button type="submit" variant="danger" class="w-100">Reject</x-button>
                        </form>
                    </x-card>
                </div>
            @elseif($withdrawal->status->value === 'approved')
                <x-card>
                    <h2 class="section-title mb-2">Mark as Completed</h2>
                    <form method="POST" action="{{ route('admin.withdrawals.complete',$withdrawal) }}" class="d-flex gap-3">@csrf
                        <input name="external_reference" class="input flex-grow-1 fs-sm" placeholder="MFS transaction reference (optional)">
                        <x-button type="submit" variant="mint">Mark paid</x-button>
                    </form>
                </x-card>
            @endif
        @endcan
    </div>
</x-layouts.admin>
