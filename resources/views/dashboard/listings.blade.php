<x-layouts.dashboard title="My Listings" heading="My Listings">
    <x-slot:actions>
        @if(auth()->user()->canSell())
            <x-button :href="route('dashboard.listings.create')">+ New Listing</x-button>
        @endif
    </x-slot:actions>

    {{-- Tabs --}}
    @php
    $statuses=[
        'all'=>'All','draft'=>'Draft','pending_review'=>'Pending Review','published'=>'Published',
        'pending_edit_approval'=>'Edit Pending','rejected'=>'Rejected','paused'=>'Paused',
        'sold_out'=>'Sold Out','suspended'=>'Suspended',
    ];
    $current=request('status','all');
    @endphp
    <div class="tabs overflow-x-auto flex-nowrap text-nowrap">
        @foreach($statuses as $val=>$lbl)
            <a href="{{ route('dashboard.listings',['status'=>$val]) }}"
               class="tab {{ $current===$val?'tab-active':'' }}">{{ $lbl }}</a>
        @endforeach
    </div>

    @if($listings->isEmpty())
        <x-empty-state icon="🏷️" title="No listings yet">
            <x-slot:slot>Create your first listing to start selling digital assets.</x-slot:slot>
            @if(auth()->user()->canSell())
                <x-button :href="route('dashboard.listings.create')" class="mt-3">Create a listing</x-button>
            @else
                <x-button :href="route('dashboard.verification')" variant="outline" class="mt-3">Get verified to sell</x-button>
            @endif
        </x-empty-state>
    @else
        {{-- Desktop table --}}
        <div class="table-wrap d-none d-sm-block">
            <table class="table">
                <thead><tr>
                    <th>Asset</th><th>Price</th><th>Qty</th>
                    <th>Status</th><th>Featured</th><th>Created</th><th class="text-end">Actions</th>
                </tr></thead>
                <tbody>
                @foreach($listings as $l)
                    <tr>
                        <td class="fw-medium text-dark max-w-xs text-truncate">{{ $l->title }}</td>
                        <td><x-money :amount="$l->price" /></td>
                        <td>{{ $l->available_quantity }}/{{ $l->quantity }}</td>
                        <td><x-status-badge :status="$l->status->value" /></td>
                        <td>{{ $l->is_featured ? '⭐ Yes' : '—' }}</td>
                        <td class="text-muted">{{ $l->created_at->format('d M Y') }}</td>
                        <td class="text-end">
                            <a href="{{ route('marketplace.show',$l->slug) }}" class="btn-ghost btn-sm">View</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{-- Mobile cards --}}
        <div class="d-sm-none vstack gap-2">
            @foreach($listings as $l)
                <div class="card-p">
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div class="">
                            <p class="fw-semibold text-dark text-truncate">{{ $l->title }}</p>
                            <p class="fs-xs text-muted mt-1">{{ $l->created_at->format('d M Y') }}</p>
                        </div>
                        <x-status-badge :status="$l->status->value" />
                    </div>
                    <div class="d-flex align-items-center justify-content-between mt-2">
                        <x-money :amount="$l->price" class="fw-bold text-dark" />
                        <a href="{{ route('marketplace.show',$l->slug) }}" class="btn-ghost btn-sm">View</a>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-3">{{ $listings->withQueryString()->links() }}</div>
    @endif
</x-layouts.dashboard>
