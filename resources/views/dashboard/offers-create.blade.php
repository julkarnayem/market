<x-layouts.dashboard title="Make an Offer" heading="Make an Offer">
    <div class="max-w-lg">
        {{-- Asset summary --}}
        <div class="card-p mb-3 d-flex align-items-center gap-3">
            @if($asset->coverImage)
                <img src="{{ $asset->coverImage->url() }}" class="h-16 w-16 rounded-3 object-fit-cover flex-shrink-0">
            @else
                <div class="h-16 w-16 rounded-3 bg-primary bg-opacity-10 d-grid place-items-center fs-3 flex-shrink-0">{{ $asset->category->icon ?? '🧩' }}</div>
            @endif
            <div class="">
                <p class="fw-semibold text-dark text-truncate">{{ $asset->title }}</p>
                <p class="fs-xs text-muted">{{ $asset->category->name }}</p>
                <p class="fs-sm mt-1">Listing price: <x-money :amount="$asset->price" class="fw-bold text-dark" /></p>
            </div>
        </div>

        @if($userActiveOffer)
            <x-alert type="warning" class="mb-3">
                <div>
                    <p class="fw-semibold">You already have an active offer on this listing.</p>
                    <p class="fs-sm mt-1">Amount: <x-money :amount="$userActiveOffer->amount" /> — Expires {{ $userActiveOffer->expires_at->diffForHumans() }}</p>
                </div>
            </x-alert>
        @else
            <x-card>
                <h2 class="section-title mb-3">Your Offer</h2>
                <form method="POST" action="{{ route('offers.store') }}" class="vstack gap-3">
                    @csrf
                    <input type="hidden" name="asset_id" value="{{ $asset->id }}">

                    <div>
                        <label class="label">Offer Amount (৳) <span class="text-danger">*</span></label>
                        <div class="position-relative">
                            <span class="position-absolute text-muted font-mono fw-bold">৳</span>
                            <input type="number" name="amount_bdt" class="input pl-7 {{ $errors->has('amount_bdt')?'input-error':'' }}"
                                   min="1" step="1" value="{{ old('amount_bdt') }}" required placeholder="0"
                                   autofocus>
                        </div>
                        @error('amount_bdt')<p class="field-error">{{ $message }}</p>@enderror
                        <p class="field-hint">Listing price is <x-money :amount="$asset->price" class="fw-medium" />. Offer any amount.</p>
                    </div>

                    @if($asset->quantity > 1)
                        <div>
                            <label class="label">Quantity</label>
                            <input type="number" name="quantity" class="input" min="1" max="{{ $asset->available_quantity }}" value="{{ old('quantity',1) }}">
                        </div>
                    @else
                        <input type="hidden" name="quantity" value="1">
                    @endif

                    <div>
                        <label class="label">Message to seller (optional)</label>
                        <textarea name="buyer_message" rows="3" class="textarea" placeholder="Explain your offer or ask a question…">{{ old('buyer_message') }}</textarea>
                    </div>

                    <div class="rounded-3 bg-primary bg-opacity-10 p-3 fs-sm">
                        <p class="fw-semibold text-brand-900 mb-1">📋 Offer terms</p>
                        <ul class="vstack gap-1 text-primary">
                            <li>• This offer is valid for <strong>8 hours</strong> from submission.</li>
                            <li>• The seller can accept or reject. No counter-offers.</li>
                            <li>• If accepted, you must complete payment immediately.</li>
                            <li>• Seller cannot change the listing price while your offer is active.</li>
                        </ul>
                    </div>

                    <div class="d-flex gap-3">
                        <x-button type="submit" size="lg">Submit offer</x-button>
                        <x-button variant="outline" :href="route('marketplace.show', $asset->slug)">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        @endif
    </div>
</x-layouts.dashboard>
