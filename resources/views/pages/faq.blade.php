<x-layouts.public title="FAQ">
    <div class="mx-auto max-w-3xl px-3 px-sm-4 px-lg-4 py-4" x-data>
        <h1 class="font-display fs-2 fw-bold text-dark">Frequently asked questions</h1>
        <div class="mt-4 vstack gap-2">
            @foreach ([
                ['Is there a fee to list an asset?', 'No. Creating a listing is completely free. Sellers pay a flat 10% platform fee only when an asset sells.'],
                ['How does buyer protection work?', 'After payment, funds are held and you have 72 hours to confirm delivery. If you do nothing, the order auto-completes.'],
                ['When can a seller withdraw earnings?', 'Earnings unlock 8 hours after an order is completed. The minimum withdrawal is ৳50 with a ৳5 fee, paid via Mobile Financial Services.'],
                ['Can I cancel after paying?', 'No. Once payment succeeds, orders cannot be cancelled by the buyer. Issues are handled through disputes.'],
                ['Do I need verification to buy?', 'No. Anyone can buy. Verification is only required to sell.'],
            ] as $i => [$q, $a])
                <div x-data="{ open: {{ $i === 0 ? 'true' : 'false' }} }" class="card">
                    <button @click="open=!open" class="w-100 d-flex align-items-center justify-content-between p-3 text-start fw-medium text-dark">
                        {{ $q }} <span x-text="open ? '−' : '+'"></span>
                    </button>
                    <div x-show="open" x-collapse class="px-3 pb-3 fs-sm text-muted">{{ $a }}</div>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.public>
