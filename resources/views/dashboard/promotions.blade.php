<x-layouts.dashboard title="Promotions" heading="My Promotions">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <p class="section-sub">Purchase paid feature slots for your published listings.</p>
        <a href="{{ route('dashboard.listings') }}" class="btn-outline btn-sm">← My listings</a>
    </div>

    @if($promotions->isEmpty())
        <x-empty-state icon="⭐" title="No promotions yet">
            Promote your listings to appear at the top of the marketplace.
            <x-slot:slot><a href="{{ route('dashboard.listings') }}" class="btn-outline mt-3">Browse my listings</a></x-slot:slot>
        </x-empty-state>
    @else
        <div class="table-wrap d-none d-sm-block">
            <table class="table">
                <thead><tr><th>Listing</th><th>Type</th><th>Days</th><th>Amount</th><th>Start</th><th>End</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($promotions as $p)
                    <tr>
                        <td class="fw-medium max-w-[160px] text-truncate">{{ $p->asset->title ?? '—' }}</td>
                        <td><span class="badge-{{ $p->is_manual?'brand':'mint' }} text-xs">{{ $p->is_manual?'Admin featured':'Paid' }}</span></td>
                        <td>{{ $p->days ?: '—' }}</td>
                        <td class="money">{{ $p->price > 0 ? \App\Support\Money::format($p->price) : '৳0 (free)' }}</td>
                        <td class="fs-xs text-muted">{{ $p->starts_at?->format('d M Y, H:i') }}</td>
                        <td class="fs-xs text-muted">{{ $p->ends_at?->format('d M Y, H:i') }}</td>
                        <td><x-status-badge :status="$p->status" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="d-sm-none vstack gap-2">
        @foreach($promotions as $p)
            <div class="card-p">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <p class="fw-semibold text-dark text-truncate">{{ $p->asset->title ?? '—' }}</p>
                    <x-status-badge :status="$p->status" />
                </div>
                <div class="d-flex justify-content-between mt-2 fs-sm text-muted">
                    <span>{{ $p->starts_at?->format('d M') }} – {{ $p->ends_at?->format('d M Y') }}</span>
                    <x-money :amount="$p->price" />
                </div>
            </div>
        @endforeach
        </div>
        <div class="mt-3">{{ $promotions->withQueryString()->links() }}</div>
    @endif
</x-layouts.dashboard>
