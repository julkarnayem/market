<x-layouts.public title="Checkout">
<div class="mx-auto max-w-3xl px-3 px-sm-4 px-lg-4 py-4">
    <h1 class="font-display fs-3 fw-bold text-dark mb-4">Checkout</h1>

    @if (!$gatewayConfigured)
        <x-alert type="warning" class="mb-3">
            <div><p class="fw-semibold">Payment gateway not configured.</p>
            <p class="fs-sm mt-1">The site administrator needs to configure UddoktaPay credentials (<code>UDDOKTAPAY_API_KEY</code> + <code>UDDOKTAPAY_BASE_URL</code>) in the <code>.env</code> file.</p></div>
        </x-alert>
    @endif

    <div class="grid-cols-[1fr_20rem] gap-4 align-items-start">
        {{-- Order summary --}}
        <x-card>
            <h2 class="section-title mb-3">Order Summary</h2>

            {{-- Asset --}}
            <div class="d-flex align-items-center gap-3 pb-3 border-bottom border-light mb-3">
                @if($asset->coverImage)
                    <img src="{{ $asset->coverImage->url() }}" class="h-16 w-16 rounded-3 object-fit-cover flex-shrink-0">
                @else
                    <div class="h-16 w-16 rounded-3 bg-primary bg-opacity-10 d-grid place-items-center fs-3 flex-shrink-0">{{ $asset->category->icon ?? '🧩' }}</div>
                @endif
                <div class="">
                    <p class="fw-semibold text-dark text-truncate">{{ $asset->title }}</p>
                    <p class="fs-xs text-muted">Sold by: {{ $asset->seller->name }}</p>
                    @if($offer)<p class="badge-mint mt-1 d-inline-flex">Offer accepted</p>@endif
                </div>
            </div>

            {{-- Fee breakdown — ALL calculated server-side --}}
            <dl class="vstack gap-2 fs-sm">
                <div class="d-flex justify-content-between"><dt class="text-muted">Unit price</dt><dd class="money fw-medium">{{ \App\Support\Money::format($offer ? $offer->amount : $asset->price) }}</dd></div>
                <div class="d-flex justify-content-between"><dt class="text-muted">Quantity</dt><dd>× {{ $quantity }}</dd></div>
                <div class="d-flex justify-content-between"><dt class="text-muted">Subtotal</dt><dd class="money fw-medium">{{ \App\Support\Money::format($feeSnap['subtotal']) }}</dd></div>
                @if($feeSnap['buyer_fee_enabled'] && $feeSnap['buyer_fee_amount'] > 0)
                    <div class="d-flex justify-content-between"><dt class="text-muted">
                        Buyer fee @if($feeSnap['buyer_fee_type']==='percentage') ({{ number_format($feeSnap['buyer_fee_bp']/100,2) }}%)@endif
                    </dt><dd class="money text-danger">+ {{ \App\Support\Money::format($feeSnap['buyer_fee_amount']) }}</dd></div>
                @endif
                <div class="d-flex justify-content-between pt-2 border-top border-light fw-bold">
                    <dt>Total payable</dt>
                    <dd class="money text-dark">{{ \App\Support\Money::format($feeSnap['buyer_total']) }}</dd>
                </div>
            </dl>

            {{-- Seller earning info (for transparency) --}}
            <div class="mt-2 rounded-3 bg-light px-3 py-2 fs-xs text-muted">
                Platform fee: {{ number_format($feeSnap['seller_fee_bp']/100,2) }}% · Seller receives: <span class="money">{{ \App\Support\Money::format($feeSnap['seller_earning']) }}</span>
            </div>

            {{-- Policy --}}
            <div class="mt-3 rounded-3 bg-success bg-opacity-10 px-3 py-2 fs-sm text-success vstack gap-1">
                <p>🛡 72-hour buyer protection applies after payment.</p>
                <p>⚠ After payment you cannot cancel — use the dispute system if needed.</p>
            </div>
        </x-card>

        {{-- Payment action --}}
        <div class="vstack gap-3">
            <x-card>
                <h2 class="section-title mb-1">Payment</h2>
                <p class="section-sub mb-3">Secure payment via UddoktaPay (BDT)</p>

                <div class="rounded-3 bg-primary bg-opacity-10 p-3 text-center mb-3">
                    <p class="fs-xs text-muted">You will pay</p>
                    <x-money :amount="$feeSnap['buyer_total']" class="fs-2 fw-bold text-dark d-block mt-1" />
                </div>

                <form method="POST" action="{{ route('checkout.initiate') }}">
                    @csrf
                    <input type="hidden" name="asset_id" value="{{ $asset->id }}">
                    <input type="hidden" name="quantity" value="{{ $quantity }}">
                    @if($offer)<input type="hidden" name="offer_id" value="{{ $offer->id }}">@endif
                    <x-button type="submit" class="w-100" size="lg" {{ !$gatewayConfigured ? 'disabled' : '' }}>
                        Pay {{ \App\Support\Money::format($feeSnap['buyer_total']) }} via UddoktaPay →
                    </x-button>
                </form>

                <p class="fs-xs text-secondary mt-2 text-center">You will be redirected to UddoktaPay to complete payment securely in BDT.</p>
            </x-card>

            <x-card :padded="false">
                <div class="p-3 fs-xs text-muted space-y-1.5">
                    <p>✓ Payment is processed by UddoktaPay</p>
                    <p>✓ Funds held until you confirm delivery</p>
                    <p>✓ Seller is notified once payment is verified</p>
                </div>
            </x-card>
        </div>
    </div>
</div>
</x-layouts.public>
