<x-layouts.admin title="Settings" heading="Platform Settings">
    @can('settings.manage')
    <div class="max-w-2xl vstack gap-3">
        <form method="POST" action="{{ route('admin.settings.update') }}" class="vstack gap-3">
            @csrf @method('PATCH')

            <x-card>
                <h2 class="section-title mb-3">Fees</h2>
                <div class="vstack gap-3">
                    <div>
                        <label class="label">Seller Platform Fee (basis points)</label>
                        <div class="d-flex align-items-center gap-3">
                            <input type="number" name="seller_fee_bp" min="0" max="10000" value="{{ $settings['seller_fee_bp'] }}" class="input max-w-xs">
                            <span class="fs-sm text-muted">= {{ number_format($settings['seller_fee_bp']/100,2) }}%</span>
                        </div>
                        <p class="label-hint">Default: 1000 bp = 10%. Enter 0–10000 (basis points). Applies to ALL prices.</p>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <label class="position-relative d-inline-flex align-items-center">
                            <input type="hidden" name="buyer_fee_enabled" value="0">
                            <input type="checkbox" name="buyer_fee_enabled" value="1" class="checkbox" {{ $settings['buyer_fee_enabled']?'checked':'' }}>
                            <span class="ms-2 fs-sm fw-medium text-dark">Enable buyer fee</span>
                        </label>
                    </div>
                </div>
            </x-card>

            <x-card>
                <h2 class="section-title mb-3">Withdrawals</h2>
                <div class="row row-cols-2 gap-3">
                    <div>
                        <label class="label">Minimum withdrawal (poisha)</label>
                        <input type="number" name="minimum_withdrawal" value="{{ $settings['minimum_withdrawal'] }}" class="input">
                        <p class="label-hint">{{ \App\Support\Money::format($settings['minimum_withdrawal']) }}</p>
                    </div>
                    <div>
                        <label class="label">Withdrawal fee (poisha)</label>
                        <input type="number" name="withdrawal_fee" value="{{ $settings['withdrawal_fee'] }}" class="input">
                        <p class="label-hint">{{ \App\Support\Money::format($settings['withdrawal_fee']) }}</p>
                    </div>
                </div>
            </x-card>

            <x-card>
                <h2 class="section-title mb-3">Timing</h2>
                <div class="row row-cols-3 gap-3">
                    <div>
                        <label class="label">Offer validity (hours)</label>
                        <input type="number" name="offer_validity_hours" value="{{ $settings['offer_validity_hours'] }}" class="input" min="1">
                    </div>
                    <div>
                        <label class="label">Earning lock (hours)</label>
                        <input type="number" name="earning_lock_hours" value="{{ $settings['earning_lock_hours'] }}" class="input" min="1">
                    </div>
                    <div>
                        <label class="label">Buyer protection (hours)</label>
                        <input type="number" name="buyer_protection_hours" value="{{ $settings['buyer_protection_hours'] }}" class="input" min="1">
                    </div>
                </div>
            </x-card>

            <x-card>
                <h2 class="section-title mb-3">Promotion Pricing</h2>
                <div class="vstack gap-2">
                    @foreach([1,2,3,4,5] as $day)
                        <div class="d-flex align-items-center gap-3">
                            <label class="label mb-0 w-16 flex-shrink-0">{{ $day }} day{{ $day>1?'s':'' }}</label>
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-muted font-mono">৳</span>
                                <input type="number" name="promotion_price_{{ $day }}"
                                       value="{{ \App\Support\Money::toBdt($settings['promotion_prices'][$day]??0) }}"
                                       class="input max-w-[120px]" min="0" step="1">
                                <span class="fs-xs text-muted">BDT (stored as poisha)</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>

            <x-button type="submit" size="lg">Save all settings</x-button>
        </form>
    </div>
    @else
        <x-alert type="warning">You do not have permission to manage settings.</x-alert>
    @endcan
</x-layouts.admin>
