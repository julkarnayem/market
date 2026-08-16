<x-layouts.dashboard :title="'Ticket '.$ticket->reference" :heading="$ticket->subject">
    <x-breadcrumb :items="[['label'=>'Support','url'=>route('dashboard.tickets')],['label'=>$ticket->reference]]" />
    <div class="max-w-3xl vstack gap-3">
        {{-- Context links (order / asset / withdrawal) --}}
        @if($ticket->order_id || $ticket->asset_id || $ticket->withdrawal_id)
        <div class="card-p bg-primary bg-opacity-10 d-flex flex-wrap gap-3 fs-sm">
            <span class="text-primary fw-semibold">Linked to:</span>
            @if($ticket->order)<a href="{{ route('dashboard.orders.show',$ticket->order) }}" class="badge-brand">📦 Order {{ $ticket->order->order_number }}</a>@endif
            @if($ticket->asset)<a href="{{ route('marketplace.show',$ticket->asset->slug) }}" class="badge-slate">🏷️ {{ Str::limit($ticket->asset->title,40) }}</a>@endif
            @if($ticket->withdrawal)<a href="{{ route('dashboard.withdrawals') }}" class="badge-mint">🏦 Withdrawal #{{ $ticket->withdrawal_id }}</a>@endif
        </div>
        @endif
        {{-- Status bar --}}
        <div class="card-p d-flex flex-wrap align-items-center gap-3 justify-content-between">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <x-status-badge :status="$ticket->status->value" />
                <span class="badge-{{ $ticket->priorityColor() }} text-xs">{{ ucfirst($ticket->priority) }}</span>
                <span class="fs-xs text-muted">{{ $ticket->reference }}</span>
            </div>
            @if($ticket->assignee)<p class="fs-xs text-muted">Assigned to: <strong>{{ $ticket->assignee->name }}</strong></p>@endif
        </div>

        {{-- Message thread --}}
        <div class="vstack gap-3">
            @foreach($ticket->messages as $msg)
                @php $isStaff = $msg->is_staff_reply; @endphp
                <div class="flex gap-3 {{ $isStaff ? '' : 'flex-row-reverse' }}">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-sm font-bold
                        {{ $isStaff ? 'bg-brand-600 text-white' : 'bg-slate-200 text-slate-700' }}">
                        {{ strtoupper(substr($msg->user->name,0,1)) }}
                    </span>
                    <div class="flex-grow-1 max-w-xl">
                        <div class="{{ $isStaff ? 'bg-brand-50 ring-1 ring-brand-200' : 'bg-white ring-1 ring-slate-200' }} rounded-2xl px-4 py-3">
                            <div class="d-flex justify-content-between gap-2 mb-1">
                                <span class="text-xs font-semibold {{ $isStaff ? 'text-brand-800' : 'text-slate-700' }}">
                                    {{ $isStaff ? 'Support Team' : $msg->user->name }}
                                </span>
                                <span class="fs-xs text-secondary">{{ $msg->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="fs-sm text-dark">{{ $msg->body }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Reply form --}}
        @if($ticket->isOpen())
            <x-card>
                <h2 class="fs-sm fw-semibold text-dark mb-2">Send a reply</h2>
                <form method="POST" action="{{ route('dashboard.tickets.reply',$ticket) }}" enctype="multipart/form-data" class="vstack gap-2">
                    @csrf
                    <textarea name="body" rows="4" class="textarea" required placeholder="Type your reply…"></textarea>
                    <input type="file" name="attachment" class="input fs-sm" accept=".jpg,.jpeg,.png,.pdf,.txt,.zip">
                    <div class="d-flex gap-3">
                        <x-button type="submit">Send reply</x-button>
                    </div>
                </form>
            </x-card>
        @else
            <x-alert type="info">This ticket is {{ $ticket->status->value }}. No further replies can be submitted.</x-alert>
        @endif
    </div>
</x-layouts.dashboard>
