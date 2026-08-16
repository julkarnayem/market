<x-layouts.admin :title="'Dispute #'.$dispute->id" heading="Dispute Review">
    <x-breadcrumb :items="[['label'=>'Disputes','url'=>route('admin.disputes')],['label'=>'#'.$dispute->id]]" />
    <div class="grid-cols-[1fr_22rem] gap-4">
        <div class="vstack gap-3">
            <x-card>
                <h2 class="section-title mb-3">Dispute Details</h2>
                <dl class="row row-cols-2 gap-3 fs-sm mb-3">
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Order</dt><dd class="font-mono fw-medium"><a href="{{ route('admin.orders.show',$dispute->order) }}" class="text-primary">{{ $dispute->order->order_number }}</a></dd></div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Status</dt><dd><x-status-badge :status="$dispute->status->value" /></dd></div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Buyer</dt><dd>{{ $dispute->order->buyer->name }}</dd></div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Seller</dt><dd>{{ $dispute->order->seller->name }}</dd></div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Order Total</dt><dd class="money fw-bold">{{ \App\Support\Money::format($dispute->order->buyer_total) }}</dd></div>
                    <div class="rounded-3 bg-light p-2"><dt class="fs-xs text-muted">Seller Earning (at risk)</dt><dd class="money text-warning fw-bold">{{ \App\Support\Money::format($dispute->order->seller_earning) }}</dd></div>
                </dl>
                <div class="rounded-3 bg-danger bg-opacity-10 p-3">
                    <p class="fw-semibold text-rose-900 mb-1">Buyer's Reason</p>
                    <p class="fs-sm text-rose-800">{{ $dispute->reason }}</p>
                </div>
            </x-card>

            {{-- Evidence --}}
            @if($dispute->evidence->isNotEmpty())
                <x-card>
                    <h2 class="section-title mb-2">Evidence Submitted</h2>
                    <div class="vstack gap-2">
                        @foreach($dispute->evidence as $ev)
                            <div class="rounded-3 border border-light p-2">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="badge-{{ $ev->role==='buyer'?'rose':'brand' }} text-xs">{{ ucfirst($ev->role) }}</span>
                                    <span class="fs-xs text-muted">{{ $ev->submitter->name }} · {{ $ev->created_at->diffForHumans() }}</span>
                                </div>
                                @if($ev->message)<p class="fs-sm text-dark">{{ $ev->message }}</p>@endif
                                @if($ev->hasFile())<a href="#" class="btn-outline btn-sm mt-1 d-inline-flex">📎 {{ $ev->file_original_name }}</a>@endif
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif

            {{-- Order conversation snapshot --}}
            @if($dispute->order->conversation)
                <x-card>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h2 class="section-title">Order Messages</h2>
                        <span class="badge-slate">{{ $dispute->order->conversation->messages->count() }} messages</span>
                    </div>
                    <div class="vstack gap-2 max-h-48 overflow-y-auto">
                        @foreach($dispute->order->conversation->messages->sortBy('created_at')->take(10) as $msg)
                            <div class="d-flex align-items-start gap-2">
                                <span class="fs-xs fw-medium text-muted flex-shrink-0 w-20 text-truncate">{{ $msg->sender->name }}</span>
                                <p class="fs-xs text-dark bg-light rounded px-2 py-1 flex-grow-1">{{ $msg->body }}</p>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif
        </div>

        {{-- Resolution panel --}}
        <div class="vstack gap-3">
            {{-- Status update --}}
            @if($dispute->status->isOpen())
                <x-card>
                    <h2 class="section-title mb-2">Update Status</h2>
                    <form method="POST" action="{{ route('admin.disputes.status',$dispute) }}" class="vstack gap-2">
                        @csrf @method('PATCH')
                        <select name="status" class="select fs-sm">
                            <option value="under_review">Under Review</option>
                            <option value="waiting_for_buyer">Waiting for Buyer</option>
                            <option value="waiting_for_seller">Waiting for Seller</option>
                        </select>
                        <textarea name="note" rows="2" class="textarea fs-sm" placeholder="Admin note…"></textarea>
                        <x-button type="submit" variant="outline" class="w-100" size="sm">Update status</x-button>
                    </form>
                </x-card>

                {{-- Full refund --}}
                <x-card>
                    <h2 class="section-title mb-1">Full Refund</h2>
                    <p class="section-sub mb-2">Buyer receives <span class="money fw-semibold">{{ \App\Support\Money::format($dispute->order->buyer_total) }}</span>. Seller loses full earning.</p>
                    <form method="POST" action="{{ route('admin.disputes.full-refund',$dispute) }}" class="vstack gap-2">
                        @csrf
                        <textarea name="note" rows="2" class="textarea fs-sm" required placeholder="Reason for full refund (required)…"></textarea>
                        <x-button type="submit" variant="danger" class="w-100" onclick="return confirm('Issue full refund of {{ \App\Support\Money::format($dispute->order->buyer_total) }}?')">Issue full refund</x-button>
                    </form>
                </x-card>

                {{-- Partial refund --}}
                <x-card>
                    <h2 class="section-title mb-1">Partial Refund</h2>
                    <form method="POST" action="{{ route('admin.disputes.partial-refund',$dispute) }}" class="vstack gap-2">
                        @csrf
                        <div class="position-relative"><span class="position-absolute text-secondary font-mono fs-sm">৳</span>
                            <input type="number" name="refund_bdt" class="input ps-4 fs-sm" min="1" step="1"
                                   max="{{ \App\Support\Money::toBdt($dispute->order->buyer_total) }}" required placeholder="0"></div>
                        <textarea name="note" rows="2" class="textarea fs-sm" required placeholder="Reason…"></textarea>
                        <x-button type="submit" variant="warning" class="w-100">Issue partial refund</x-button>
                    </form>
                </x-card>

                {{-- Release to seller --}}
                <x-card>
                    <h2 class="section-title mb-1">Release to Seller</h2>
                    <p class="section-sub mb-2">Seller receives <span class="money fw-semibold text-success">{{ \App\Support\Money::format($dispute->order->seller_earning) }}</span>. No refund to buyer.</p>
                    <form method="POST" action="{{ route('admin.disputes.release-seller',$dispute) }}" class="vstack gap-2">
                        @csrf
                        <textarea name="note" rows="2" class="textarea fs-sm" required placeholder="Reason for releasing to seller…"></textarea>
                        <x-button type="submit" variant="success" class="w-100" onclick="return confirm('Release earning to seller?')">Release to seller</x-button>
                    </form>
                </x-card>
            @else
                <x-card>
                    <h2 class="section-title mb-2">Resolution</h2>
                    <div class="vstack gap-2 fs-sm">
                        <div class="d-flex justify-content-between"><span class="text-muted">Type</span><span class="fw-medium">{{ $dispute->resolution_type ? ucwords(str_replace('_',' ',$dispute->resolution_type)) : '—' }}</span></div>
                        @if($dispute->resolution_amount)<div class="d-flex justify-content-between"><span class="text-muted">Amount</span><x-money :amount="$dispute->resolution_amount" class="fw-bold" /></div>@endif
                        <div class="d-flex justify-content-between"><span class="text-muted">Resolved by</span><span>{{ $dispute->resolver?->name ?? '—' }}</span></div>
                        <div class="d-flex justify-content-between"><span class="text-muted">Resolved at</span><span>{{ $dispute->resolved_at?->format('d M Y, H:i') ?? '—' }}</span></div>
                    </div>
                    @if($dispute->resolution_note)
                        <div class="mt-2 rounded-3 bg-light p-2 fs-xs text-muted">{{ $dispute->resolution_note }}</div>
                    @endif
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.admin>
