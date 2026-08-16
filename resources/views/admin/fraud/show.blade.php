<x-layouts.admin :title="'Fraud — '.$user->name" heading="Fraud Review">
    <x-breadcrumb :items="[['label'=>'Fraud Queue','url'=>route('admin.fraud')],['label'=>$user->name]]" />
    <div class="grid-cols-[1fr_20rem] gap-4">
        <div class="vstack gap-3">
            <x-card>
                <h2 class="section-title mb-3">User Profile</h2>
                <dl class="row row-cols-2 gap-3 fs-sm">
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Name</dt><dd class="fw-medium">{{ $user->name }}</dd></div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Email</dt><dd>{{ $user->email }}</dd></div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Risk Score</dt>
                        <dd class="font-bold text-lg {{ $user->risk_score >= 70 ? 'text-rose-600' : ($user->risk_score >= 30 ? 'text-amber-600' : 'text-mint-600') }}">
                            {{ $user->risk_score }}
                        </dd>
                    </div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Status</dt><dd><x-status-badge :status="$user->status->value" /></dd></div>
                </dl>
                <div class="mt-2 d-flex flex-wrap gap-1">
                    @foreach(($user->risk_flags ?? []) as $f)
                        <span class="badge-rose fs-xs">{{ str_replace('_',' ',$f) }}</span>
                    @endforeach
                </div>
            </x-card>
            <x-card>
                <h2 class="section-title mb-2">Recent Fraud Events (30 days)</h2>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Signal</th><th>Score impact</th><th>IP</th><th>Date</th></tr></thead>
                        <tbody>
                        @forelse($events as $e)
                            <tr>
                                <td><span class="badge-rose fs-xs">{{ str_replace('_',' ',$e->signal) }}</span></td>
                                <td class="font-mono text-warning">+{{ $e->score_impact }}</td>
                                <td class="font-mono fs-xs text-muted">{{ $e->ip_address ?? '—' }}</td>
                                <td class="fs-xs text-muted">{{ $e->created_at->format('d M Y, H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center py-3 text-muted">No events.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
        <div class="vstack gap-3">
            @if($review)
                <x-card>
                    <h2 class="section-title mb-1">Review Status</h2>
                    <p class="section-sub mb-2">Current: <x-status-badge :status="$review->status" /></p>
                    @if($review->admin_notes)<p class="fs-xs text-muted mb-2 bg-light rounded-3 p-2">{{ $review->admin_notes }}</p>@endif
                </x-card>
            @endif
            <x-card>
                <h2 class="section-title mb-2">Clear Risk Score</h2>
                <p class="fs-xs text-muted mb-2">Clears the risk score and flags. Use when satisfied this is a false positive.</p>
                <form method="POST" action="{{ route('admin.fraud.clear',$user) }}" class="vstack gap-2">
                    @csrf
                    <textarea name="admin_notes" rows="3" class="textarea fs-sm" required placeholder="Note explaining the review decision…"></textarea>
                    <x-button type="submit" variant="success" class="w-100" size="sm" onclick="return confirm('Clear fraud score?')">✓ Clear — false positive</x-button>
                </form>
            </x-card>
            <x-card>
                <h2 class="section-title mb-2">Restrict Account</h2>
                <form method="POST" action="{{ route('admin.fraud.restrict',$user) }}" class="vstack gap-2">
                    @csrf
                    <textarea name="reason" rows="3" class="textarea fs-sm" required placeholder="Restriction reason…"></textarea>
                    <x-button type="submit" variant="danger" class="w-100" size="sm" onclick="return confirm('Mark as restricted?')">⚑ Restrict</x-button>
                </form>
            </x-card>
            <a href="{{ route('admin.users.show',$user) }}" class="btn-outline w-100 d-block text-center fs-sm">View full user profile →</a>
        </div>
    </div>
</x-layouts.admin>
