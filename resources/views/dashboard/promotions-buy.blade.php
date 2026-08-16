<x-layouts.dashboard title="Promote Listing" heading="Promote a Listing">
    <div class="max-w-lg">
        <div class="card-p mb-3 d-flex align-items-center gap-3">
            @if($asset->coverImage)<img src="{{ $asset->coverImage->url() }}" class="h-14 w-14 rounded-3 object-fit-cover flex-shrink-0">@endif
            <div class="">
                <p class="fw-semibold text-dark text-truncate">{{ $asset->title }}</p>
                <p class="fs-xs text-muted">{{ $asset->category->name }}</p>
                <p class="fs-xs mt-1">Listed price: <span class="money fw-medium">{{ \App\Support\Money::format($asset->price) }}</span></p>
            </div>
        </div>

        @if($activePromo)
            <x-alert type="warning" class="mb-3">This listing already has an active promotion until <strong>{{ $activePromo->ends_at->format('d M Y, H:i') }}</strong>.</x-alert>
        @else
            <x-card>
                <h2 class="section-title mb-1">Choose promotion duration</h2>
                <p class="section-sub mb-3">Deducted from your available wallet balance.</p>

                <form method="POST" action="{{ route('dashboard.promotions.store') }}" x-data="{ selected: 1 }">
                    @csrf
                    <input type="hidden" name="asset_id" value="{{ $asset->id }}">
                    <input type="hidden" name="days" :value="selected">

                    <div class="row row-cols-5 gap-2 mb-3">
                        @foreach($prices as $days => $poisha)
                            <button type="button" @click="selected = {{ $days }}"
                                    :class="selected === {{ $days }} ? 'ring-2 ring-brand-500 bg-brand-50' : 'ring-1 ring-slate-200 hover:ring-brand-300'"
                                    class="rounded-3 p-2 text-center">
                                <p class="fw-bold text-dark fs-5">{{ $days }}</p>
                                <p class="fs-xs text-muted">day{{ $days>1?'s':'' }}</p>
                                <p class="money fw-semibold text-primary fs-sm mt-1">{{ \App\Support\Money::format($poisha) }}</p>
                            </button>
                        @endforeach
                    </div>

                    {{-- Balance check --}}
                    @php
                        $walletBal = $wallet?->available_balance ?? 0;
                    @endphp
                    <div class="rounded-3 bg-light p-3 fs-sm vstack gap-2 mb-3"
                         x-data="{ prices: {{ json_encode(array_values($prices)) }}, get price() { return this.prices[selected-1]; }, get balAfter() { return {{ $walletBal }} - this.price; } }">
                        <div class="d-flex justify-content-between"><span class="text-muted">Promotion price</span>
                            <span class="money fw-semibold" x-text="'৳'+(prices[selected-1]/100).toFixed(2)"></span></div>
                        <div class="d-flex justify-content-between"><span class="text-muted">Current balance</span>
                            <span class="money">{{ \App\Support\Money::format($walletBal) }}</span></div>
                        <div class="d-flex justify-content-between fw-bold border-top border-secondary border-opacity-25 pt-2">
                            <span>Balance after</span>
                            <span class="money" :class="balAfter < 0 ? 'text-rose-600' : 'text-mint-700'" x-text="'৳'+(balAfter/100).toFixed(2)"></span>
                        </div>
                        <p x-show="balAfter < 0" class="text-danger fs-xs mt-1">⚠ Insufficient wallet balance for this duration. Please choose a shorter duration or add funds.</p>
                    </div>

                    <label class="d-flex align-items-start gap-2 fs-sm text-dark mb-3">
                        <input type="checkbox" required class="checkbox mt-1">
                        <span>I understand that promotion fees are non-refundable unless platform policy explicitly permits a refund.</span>
                    </label>

                    <div class="d-flex gap-3">
                        <x-button type="submit" size="lg">Activate promotion →</x-button>
                        <x-button variant="outline" :href="route('dashboard.promotions')">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        @endif
    </div>
</x-layouts.dashboard>
