<x-layouts.dashboard title="Offers" heading="Offers">
    @php $tab = request('tab','received'); @endphp
    <div class="tabs overflow-x-auto text-nowrap mb-3">
        @foreach(['received'=>'Received','sent'=>'Sent','accepted'=>'Accepted','rejected'=>'Rejected','expired'=>'Expired'] as $k=>$l)
            <a href="{{ route('dashboard.offers',['tab'=>$k]) }}" class="tab {{ $tab===$k?'tab-active':'' }}">{{ $l }}</a>
        @endforeach
    </div>

    @if($offers->isEmpty())
        <x-empty-state icon="🤝" title="No offers">
            @if($tab==='sent')You haven't made any offers yet.
            @elseif($tab==='received')No offers on your listings.
            @else No {{ $tab }} offers.@endif
            @if($tab==='sent')<x-slot:slot><a href="{{ route('marketplace.index') }}" class="btn-outline mt-3">Browse marketplace</a></x-slot:slot>@endif
        </x-empty-state>
    @else
        {{-- Desktop table --}}
        <div class="table-wrap d-none d-sm-block">
            <table class="table">
                <thead><tr>
                    <th>Asset</th>
                    <th>{{ in_array($tab,['sent','expired']) ? 'Seller' : 'Buyer' }}</th>
                    <th>Offer</th><th>List price</th>
                    <th>Status</th><th>{{ $tab==='received' ? 'Expires' : 'Date' }}</th>
                    <th class="text-end">Actions</th>
                </tr></thead>
                <tbody>
                @foreach($offers as $o)
                    <tr>
                        <td>
                            <a href="{{ route('marketplace.show',$o->asset->slug) }}" class="fw-medium text-dark text-truncate d-block max-w-[180px]">{{ $o->asset->title }}</a>
                        </td>
                        <td class="fs-sm">{{ in_array($tab,['sent','expired']) ? $o->seller->name : $o->buyer->name }}</td>
                        <td class="money fw-semibold text-dark">{{ \App\Support\Money::format($o->amount) }}</td>
                        <td class="money text-muted">{{ \App\Support\Money::format($o->asset->price) }}</td>
                        <td><x-status-badge :status="$o->status->value" /></td>
                        <td class="fs-xs text-muted">
                            @if($tab==='received' && $o->isPending())
                                <span x-data="{ secs: {{ $o->timeRemainingSeconds() }} }"
                                      x-init="setInterval(()=>{ if(secs>0)secs-- },1000)"
                                      x-text="Math.floor(secs/3600).toString().padStart(2,'0')+':'+Math.floor((secs%3600)/60).toString().padStart(2,'0')+':'+(secs%60).toString().padStart(2,'0')"
                                      class="font-mono text-warning"></span>
                            @else
                                {{ $o->created_at->format('d M, H:i') }}
                            @endif
                        </td>
                        <td class="text-end">
                            @if($tab==='received' && $o->isPending() && !$o->isExpired())
                                <form method="POST" action="{{ route('offers.accept',$o) }}" class="d-inline">@csrf
                                    <button class="btn-success btn-sm">Accept</button>
                                </form>
                                <form method="POST" action="{{ route('offers.reject',$o) }}" class="d-inline ms-1">@csrf
                                    <button class="btn-danger btn-sm">Reject</button>
                                </form>
                            @elseif($o->status->value==='accepted' && $tab==='sent')
                                <span class="badge-mint fs-xs">Payment required</span>
                            @else
                                <span class="text-slate-300 fs-xs">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile cards --}}
        <div class="d-sm-none vstack gap-2">
        @foreach($offers as $o)
            <div class="card-p vstack gap-2">
                <div class="d-flex align-items-start justify-content-between gap-2">
                    <div class="">
                        <a href="{{ route('marketplace.show',$o->asset->slug) }}" class="fw-semibold text-dark fs-sm text-truncate d-block">{{ $o->asset->title }}</a>
                        <p class="fs-xs text-muted">{{ in_array($tab,['sent','expired']) ? $o->seller->name : $o->buyer->name }}</p>
                    </div>
                    <x-status-badge :status="$o->status->value" />
                </div>
                <div class="d-flex align-items-center justify-content-between fs-sm">
                    <div><p class="fs-xs text-muted">Your offer</p><x-money :amount="$o->amount" class="fw-bold text-dark" /></div>
                    <div class="text-end"><p class="fs-xs text-muted">List price</p><x-money :amount="$o->asset->price" class="text-muted" /></div>
                </div>
                @if($tab==='received' && $o->isPending() && !$o->isExpired())
                    <div class="d-flex gap-2 pt-2 border-top border-light">
                        <form method="POST" action="{{ route('offers.accept',$o) }}" class="flex-grow-1">@csrf
                            <button class="btn-success w-100 btn-sm">Accept</button>
                        </form>
                        <form method="POST" action="{{ route('offers.reject',$o) }}" class="flex-grow-1">@csrf
                            <button class="btn-danger w-100 btn-sm">Reject</button>
                        </form>
                    </div>
                @endif
                @if($o->status->value==='accepted' && in_array($tab,['sent','accepted']))
                    <p class="fs-sm fw-semibold text-success text-center pt-2 border-top border-light">✓ Accepted — payment required</p>
                @endif
            </div>
        @endforeach
        </div>
        <div class="mt-3">{{ $offers->withQueryString()->links() }}</div>
    @endif
</x-layouts.dashboard>
