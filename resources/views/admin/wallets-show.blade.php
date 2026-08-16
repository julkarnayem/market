<x-layouts.admin :title="$wallet->user->name . chr(39) . 's Wallet'" heading="Wallet Detail">
    <x-breadcrumb :items="[['label'=>'Wallets','url'=>route('admin.wallets')],['label'=>$wallet->user->name]]" />
    <div class="grid-cols-[1fr_20rem] gap-4">
        <div class="vstack gap-3">
            <x-card>
                <h2 class="section-title mb-2">Balance</h2>
                <div class="row row-cols-3 gap-3">
                    <div class="rounded-3 bg-success bg-opacity-10 p-3"><p class="fs-xs text-success fw-semibold">Available</p><p class="money fs-3 fw-bold text-success mt-1">{{ \App\Support\Money::format($wallet->available_balance) }}</p></div>
                    <div class="rounded-3 bg-warning bg-opacity-10 p-3"><p class="fs-xs text-warning fw-semibold">Pending</p><p class="money fs-3 fw-bold text-warning mt-1">{{ \App\Support\Money::format($wallet->pending_balance) }}</p></div>
                    <div class="rounded-3 bg-light p-3"><p class="fs-xs text-muted fw-semibold">Total</p><p class="money fs-3 fw-bold text-dark mt-1">{{ \App\Support\Money::format($wallet->totalBalance()) }}</p></div>
                </div>
            </x-card>

            {{-- Transaction history --}}
            <x-card>
                <h2 class="section-title mb-2">Transaction Ledger</h2>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Available after</th><th>Description</th></tr></thead>
                        <tbody>
                        @foreach($transactions as $tx)
                            <tr>
                                <td class="fs-xs text-muted">{{ $tx->created_at->format('d M Y, H:i') }}</td>
                                <td class="fs-xs"><span class="badge-{{ $tx->amount > 0 ? 'mint' : 'rose' }}">{{ $tx->type instanceof \App\Enums\TransactionType ? $tx->type->value : $tx->type }}</span></td>
                                <td class="money text-sm font-semibold {{ $tx->amount > 0 ? 'text-mint-700' : 'text-rose-600' }}">{{ $tx->amount > 0 ? '+' : '' }}{{ \App\Support\Money::format($tx->amount) }}</td>
                                <td class="money fs-sm text-muted">{{ \App\Support\Money::format($tx->available_after) }}</td>
                                <td class="fs-xs text-muted max-w-xs text-truncate">{{ $tx->description ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-2">{{ $transactions->links() }}</div>
            </x-card>
        </div>

        {{-- Admin adjustment --}}
        @can('payments.view')
        <div>
            <x-card>
                <h2 class="section-title mb-1">Manual Adjustment <span class="badge-rose ms-1">Admin only</span></h2>
                <p class="section-sub mb-3">Use sparingly. Every adjustment is audit-logged.</p>
                <form method="POST" action="{{ route('admin.wallets.adjust',$wallet) }}" class="vstack gap-2">
                    @csrf
                    <div>
                        <label class="label">Amount (positive = credit, negative = debit)</label>
                        <div class="position-relative"><span class="position-absolute text-secondary font-mono">৳</span>
                        <input type="number" name="amount_bdt" class="input ps-4" step="0.01" required placeholder="e.g. 100 or -50"></div>
                    </div>
                    <div>
                        <label class="label">Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" rows="3" class="textarea" required minlength="10" placeholder="Explain why this manual adjustment is needed…"></textarea>
                    </div>
                    <x-button type="submit" variant="warning" class="w-100" onclick="return confirm('Apply this manual wallet adjustment?')">Apply adjustment</x-button>
                </form>
            </x-card>
        </div>
        @endcan
    </div>
</x-layouts.admin>
