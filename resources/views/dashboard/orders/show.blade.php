<x-layouts.dashboard :title="'Order '.$order->order_number">
    <x-breadcrumb :items="[['label'=>'Orders','url'=>route('dashboard.orders')],['label'=>$order->order_number]]" />

    <div class="grid-cols-[1fr_20rem] gap-4 align-items-start">
        {{-- Main --}}
        <div class="vstack gap-3">
            {{-- Header --}}
            <x-card>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                    <div>
                        <p class="font-mono fs-sm text-muted">{{ $order->order_number }}</p>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <x-status-badge :status="$order->status->value" />
                            <x-status-badge :status="$order->payment_status" />
                            @if($order->delivery_status !== 'not_started')<x-status-badge :status="$order->delivery_status" />@endif
                        </div>
                    </div>
                    <p class="fs-xs text-muted">{{ $order->created_at->format('d M Y, H:i') }}</p>
                </div>

                {{-- Asset summary --}}
                <div class="d-flex align-items-center gap-3 p-3 rounded-3 bg-light">
                    @if($order->asset->coverImage)
                        <img src="{{ $order->asset->coverImage->url() }}" class="h-14 w-14 rounded-3 object-fit-cover flex-shrink-0">
                    @else
                        <div class="h-14 w-14 rounded-3 bg-primary bg-opacity-10 d-grid place-items-center fs-3 flex-shrink-0">🧩</div>
                    @endif
                    <div class="">
                        <p class="fw-semibold text-dark">{{ $order->asset->title }}</p>
                        <p class="fs-xs text-muted">{{ $order->asset->category->name }}</p>
                    </div>
                </div>

                {{-- Fee breakdown --}}
                <dl class="mt-3 vstack gap-2 fs-sm">
                    <div class="d-flex justify-content-between"><dt class="text-muted">Unit price</dt><dd class="money">{{ \App\Support\Money::format($order->unit_price) }}</dd></div>
                    <div class="d-flex justify-content-between"><dt class="text-muted">Quantity</dt><dd>× {{ $order->quantity }}</dd></div>
                    <div class="d-flex justify-content-between"><dt class="text-muted">Subtotal</dt><dd class="money">{{ \App\Support\Money::format($order->subtotal) }}</dd></div>
                    @if($order->buyer_fee_amount > 0)
                        <div class="d-flex justify-content-between"><dt class="text-muted">Buyer fee</dt><dd class="money text-danger">+ {{ \App\Support\Money::format($order->buyer_fee_amount) }}</dd></div>
                    @endif
                    <div class="d-flex justify-content-between border-top border-light pt-2 fw-semibold"><dt>Total paid</dt><dd class="money">{{ \App\Support\Money::format($order->buyer_total) }}</dd></div>
                    @if($isSeller)
                        <div class="d-flex justify-content-between fs-xs"><dt class="text-muted">Platform fee ({{ number_format($order->seller_fee_bp/100,2) }}%)</dt><dd class="money text-danger">— {{ \App\Support\Money::format($order->seller_fee_amount) }}</dd></div>
                        <div class="flex justify-between font-bold {{ $order->earningIsLocked() ? 'text-amber-700' : 'text-mint-700' }}">
                            <dt>Your earning {{ $order->earningIsLocked() ? '(locked)' : '(available)' }}</dt>
                            <dd class="money">{{ \App\Support\Money::format($order->seller_earning) }}</dd>
                        </div>
                        @if($order->earningIsLocked())
                            <p class="fs-xs text-warning">Available at {{ $order->seller_earning_available_at?->format('d M Y, H:i') }}</p>
                        @endif
                    @endif
                </dl>
            </x-card>

            {{-- Delivery info (visible to buyer after delivery) --}}
            @if($order->delivery && ($isbuyer || $isSeller))
                <x-card>
                    <h2 class="section-title mb-2">Delivery</h2>
                    <div class="rounded-3 bg-success bg-opacity-10 p-3">
                        <p class="fs-sm text-success">{{ $order->delivery->delivery_note }}</p>
                    </div>
                    @if($order->delivery->attachment_path)
                        <a href="{{ route('orders.delivery.attachment',$order) }}" class="btn-outline btn-sm mt-2 d-inline-flex">📎 Download delivery file</a>
                    @endif
                    <p class="fs-xs text-secondary mt-2">Delivered {{ $order->delivered_at?->diffForHumans() }}</p>
                </x-card>
            @endif

            {{-- Order timeline --}}
            <x-card>
                <h2 class="section-title mb-3">Order Timeline</h2>
                <ol class="position-relative border-start border-secondary border-opacity-25 ms-2 vstack gap-3">
                    @foreach($order->statusHistory->sortBy('created_at') as $h)
                        <li class="ms-3">
                            <div class="position-absolute mt-1 h-3 w-3 rounded-pill border border-white bg-brand-500"></div>
                            <div class="d-flex align-items-start justify-content-between gap-2">
                                <div>
                                    <x-status-badge :status="$h->to_status" />
                                    @if($h->note)<p class="fs-xs text-muted mt-1">{{ $h->note }}</p>@endif
                                </div>
                                <span class="fs-xs text-secondary flex-shrink-0">{{ $h->created_at?->format('d M, H:i') }}</span>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </x-card>

            {{-- Order chat --}}
            @if($order->conversation)
                <x-card>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h2 class="section-title">Order Messages</h2>
                        <a href="{{ route('dashboard.messages', ['conversation'=>$order->conversation->id]) }}" class="btn-ghost btn-sm">Open full chat</a>
                    </div>
                    <div class="vstack gap-2 max-h-64 overflow-y-auto" id="chat-preview">
                        @forelse($order->conversation->messages->take(5) as $msg)
                            @php $mine=$msg->sender_user_id===auth()->id(); @endphp
                            <div class="flex {{ $mine?'justify-end':'' }}">
                                <div class="max-w-sm {{ $mine?'bg-brand-600 text-white':'bg-slate-100 text-slate-800' }} rounded-2xl px-3 py-2 text-sm">
                                    {{ $msg->body }}
                                    <span class="d-block mt-1">{{ $msg->created_at->format('H:i') }}</span>
                                </div>
                            </div>
                        @empty
                            <p class="fs-sm text-muted text-center py-3">No messages yet.</p>
                        @endforelse
                    </div>
                    <form method="POST" action="{{ route('dashboard.messages.send',$order->conversation) }}" class="d-flex gap-2 mt-2">
                        @csrf
                        <input name="body" placeholder="Type a message…" class="input flex-grow-1 fs-sm" autocomplete="off" required>
                        <x-button type="submit" size="sm">Send</x-button>
                    </form>
                </x-card>
            @endif
        </div>

        {{-- Sidebar actions --}}
        <div class="vstack gap-3">
            {{-- Seller: Deliver --}}
            @if($isSeller && $order->status->canBeDelivered())
                <x-card>
                    <a href="{{ route('dashboard.orders.deliver',$order) }}" class="btn-primary w-100">📦 Deliver asset</a>
                </x-card>
            @endif

            {{-- Buyer: Complete / Dispute --}}
            @if($isbuyer)
                @if($order->status->canBeCompleted())
                    <x-card>
                        <h2 class="section-title mb-2">Actions</h2>
                        <form method="POST" action="{{ route('dashboard.orders.complete',$order) }}">
                            @csrf
                            <x-button type="submit" variant="success" class="w-100">✓ Complete order</x-button>
                        </form>
                        <p class="fs-xs text-secondary mt-2">Auto-completes {{ $order->auto_complete_at?->diffForHumans() }}</p>

                        {{-- Leave a review --}}
                        @php $alreadyReviewed = \App\Models\Review::where('order_id',$order->id)->exists(); @endphp
                        @if($order->buyer_user_id === auth()->id())
                            @if($alreadyReviewed)
                                <div class="mt-2 d-flex align-items-center gap-2 fs-sm fw-medium" style="color:#10B981">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    Review submitted
                                </div>
                            @else
                                <a href="{{ route('dashboard.orders.review', $order) }}"
                                   class="mt-2 w-100 d-flex align-items-center justify-content-center gap-2 py-2 fs-sm fw-semibold rounded-3 border border-2"
                                   style="border-color:#10B981;color:#10B981"
                                   onmouseover="this.style.background='#ECFDF5'"
                                   onmouseout="this.style.background='transparent'">
                                    ⭐ Leave a Review
                                </a>
                            @endif
                        @endif
                    </x-card>
                @endif
                @if($order->status->canOpenDispute())
                    <x-card>
                        <a href="{{ route('dashboard.orders.dispute',$order) }}" class="btn-danger w-100 d-block text-center fs-sm py-2 rounded-3 fw-semibold">⚑ Open dispute</a>
                    </x-card>
                @endif
            @endif

            {{-- Participants --}}
            <x-card>
                <h2 class="section-title mb-2">Participants</h2>
                @foreach(['Buyer'=>$order->buyer,'Seller'=>$order->seller] as $role=>$person)
                    <div class="flex items-center gap-3 {{ !$loop->last?'mb-3 pb-3 border-b border-slate-100':'' }}">
                        <span class="h-9 w-9 d-grid place-items-center rounded-pill bg-primary bg-opacity-25 text-primary fw-semibold fs-sm flex-shrink-0">{{ strtoupper(substr($person->name,0,1)) }}</span>
                        <div><p class="fs-sm fw-medium text-dark">{{ $person->name }}</p><p class="fs-xs text-muted">{{ $role }}</p></div>
                    </div>
                @endforeach
            </x-card>

            {{-- Payment info --}}
            @if($order->latestPayment)
                <x-card>
                    <h2 class="section-title mb-2">Payment</h2>
                    <dl class="fs-xs text-muted space-y-1.5">
                        <div class="d-flex justify-content-between"><dt>Gateway</dt><dd>UddoktaPay</dd></div>
                        <div class="d-flex justify-content-between"><dt>Status</dt><dd><x-status-badge :status="$order->payment_status" /></dd></div>
                        @if($order->paid_at)<div class="d-flex justify-content-between"><dt>Paid at</dt><dd>{{ $order->paid_at->format('d M Y, H:i') }}</dd></div>@endif
                    </dl>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.dashboard>
