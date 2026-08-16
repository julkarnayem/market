<x-layouts.dashboard title="Withdrawals" heading="Withdrawals">
    <div class="max-w-2xl vstack gap-3">
        {{-- Balance snapshot --}}
        <x-card>
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <p class="fs-xs text-muted text-uppercase">Available to withdraw</p>
                    <x-money :amount="$wallet?->available_balance ?? 0" class="fs-2 fw-bold text-success d-block mt-1" />
                </div>
                <div class="text-end fs-xs text-muted space-y-0.5">
                    <p>Minimum: <span class="money fw-medium text-dark">৳{{ number_format($minBdt,2) }}</span></p>
                    <p>Fee: <span class="money fw-medium text-dark">{{ \App\Support\Money::format($fee) }}</span> per withdrawal</p>
                    <p>Method: Mobile Financial Service (MFS)</p>
                </div>
            </div>
        </x-card>

        {{-- Pending warning --}}
        @if(($wallet->pending_balance ?? 0) > 0)
            <x-alert type="info">
                <div><p class="fw-semibold">Pending balance: <span class="money">{{ \App\Support\Money::format($wallet->pending_balance) }}</span></p>
                <p class="fs-sm mt-1">Pending amounts are locked until 8 hours after order completion. They will move to available automatically.</p></div>
            </x-alert>
        @endif

        @if(($wallet?->available_balance ?? 0) >= \App\Support\Money::toPoisha($minBdt))
            <x-card>
                <h2 class="section-title mb-3">Request Withdrawal</h2>
                <form method="POST" action="{{ route('dashboard.withdrawals.store') }}" class="vstack gap-3"
                      x-data="{ amount: 0, fee: {{ \App\Support\Money::toBdt($fee) }}, net() { return Math.max(0, this.amount - this.fee); } }">
                    @csrf
                    <div>
                        <label class="label">Amount (৳) <span class="text-danger">*</span></label>
                        <div class="position-relative">
                            <span class="position-absolute text-muted font-mono fw-bold">৳</span>
                            <input type="number" name="amount_bdt" class="input pl-7 {{ $errors->has('amount_bdt')?'input-error':'' }}"
                                   x-model.number="amount" min="{{ $minBdt }}" step="1"
                                   max="{{ \App\Support\Money::toBdt($wallet?->available_balance ?? 0) }}"
                                   value="{{ old('amount_bdt') }}" required autofocus>
                        </div>
                        @error('amount_bdt')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="row row-cols-2 gap-3">
                        <div>
                            <label class="label">MFS Provider <span class="text-danger">*</span></label>
                            <select name="mfs_provider" class="select {{ $errors->has('mfs_provider')?'input-error':'' }}" required>
                                @foreach(['bkash'=>'bKash','nagad'=>'Nagad','rocket'=>'Rocket','upay'=>'Upay'] as $v=>$l)
                                    <option value="{{ $v }}" @selected(old('mfs_provider')===$v)>{{ $l }}</option>
                                @endforeach
                            </select>
                            @error('mfs_provider')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="label">Mobile Number <span class="text-danger">*</span></label>
                            <input type="tel" name="mfs_number" class="input {{ $errors->has('mfs_number')?'input-error':'' }}"
                                   placeholder="01XXXXXXXXX" maxlength="15" value="{{ old('mfs_number') }}" required>
                            @error('mfs_number')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    {{-- Fee preview --}}
                    <div class="rounded-3 bg-light p-3 fs-sm vstack gap-2">
                        <div class="d-flex justify-content-between"><span class="text-muted">Requested</span><span class="money" x-text="'৳'+ amount.toFixed(2)"></span></div>
                        <div class="d-flex justify-content-between"><span class="text-muted">Withdrawal fee</span><span class="money text-danger">— ৳{{ number_format(\App\Support\Money::toBdt($fee),2) }}</span></div>
                        <div class="d-flex justify-content-between fw-bold border-top border-secondary border-opacity-25 pt-2"><span>You receive</span><span class="money text-success" x-text="'৳'+net().toFixed(2)"></span></div>
                    </div>
                    <x-button type="submit" size="lg">Submit withdrawal request</x-button>
                </form>
            </x-card>
        @else
            <x-alert type="warning">
                Your available balance ({{ \App\Support\Money::format($wallet?->available_balance ?? 0) }}) is below the minimum withdrawal amount (৳{{ number_format($minBdt,2) }}).
            </x-alert>
        @endif

        {{-- History --}}
        <h2 class="section-title">Withdrawal History</h2>
        @if($withdrawals->isEmpty())
            <x-empty-state icon="🏦" title="No withdrawals yet">Submit a request once your balance is ৳{{ number_format($minBdt,2) }} or more.</x-empty-state>
        @else
            <div class="table-wrap d-none d-sm-block">
                <table class="table">
                    <thead><tr><th>#</th><th>Amount</th><th>Fee</th><th>Net</th><th>Provider</th><th>Account</th><th>Status</th><th>Requested</th></tr></thead>
                    <tbody>
                    @foreach($withdrawals as $w)
                        <tr>
                            <td class="font-mono fs-xs text-muted">#{{ $w->id }}</td>
                            <td class="money">{{ \App\Support\Money::format($w->amount) }}</td>
                            <td class="money text-danger">{{ \App\Support\Money::format($w->fee) }}</td>
                            <td class="money fw-semibold text-success">{{ \App\Support\Money::format($w->net_amount) }}</td>
                            <td class="text-uppercase fs-xs">{{ $w->mfs_provider }}</td>
                            <td class="font-mono fs-xs">{{ $w->maskedNumber() }}</td>
                            <td><x-status-badge :status="$w->status->value" /></td>
                            <td class="fs-xs text-muted">{{ $w->created_at->format('d M Y') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="d-sm-none vstack gap-2">
            @foreach($withdrawals as $w)
                <div class="card-p">
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <div><x-money :amount="$w->net_amount" class="fw-bold text-success" />
                            <p class="fs-xs text-muted mt-1">{{ strtoupper($w->mfs_provider) }} · {{ $w->maskedNumber() }}</p>
                        </div>
                        <x-status-badge :status="$w->status->value" />
                    </div>
                    @if($w->status->value==='rejected' && $w->rejection_reason)
                        <p class="fs-xs text-danger mt-2">Reason: {{ $w->rejection_reason }}</p>
                    @endif
                    <p class="fs-xs text-secondary mt-1">{{ $w->created_at->format('d M Y') }}</p>
                </div>
            @endforeach
            </div>
            <div class="mt-3">{{ $withdrawals->withQueryString()->links() }}</div>
        @endif
    </div>
</x-layouts.dashboard>
