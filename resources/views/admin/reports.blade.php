<x-layouts.admin title="Reports" heading="Platform Reports">
    {{-- Date filter --}}
    <form method="GET" class="d-flex flex-wrap gap-2 mb-4 align-items-end">
        <div><label class="label fs-xs">From</label><input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="input w-auto"></div>
        <div><label class="label fs-xs">To</label><input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="input w-auto"></div>
        <x-button type="submit">Apply</x-button>
        <a href="{{ route('admin.reports') }}" class="btn-ghost">Reset</a>
        <a href="{{ route('admin.reports',array_merge(request()->all(),['export'=>'csv'])) }}" class="btn-outline ms-auto">Export CSV</a>
    </form>

    {{-- Quick-select presets --}}
    <div class="d-flex flex-wrap gap-2 mb-3 fs-xs">
        @foreach(['Today'=>[now()->format('Y-m-d'),now()->format('Y-m-d')],'7 days'=>[now()->subDays(7)->format('Y-m-d'),now()->format('Y-m-d')],'30 days'=>[now()->subDays(30)->format('Y-m-d'),now()->format('Y-m-d')],'This month'=>[now()->startOfMonth()->format('Y-m-d'),now()->format('Y-m-d')]] as $label=>[$f,$t])
            <a href="{{ route('admin.reports',['from'=>$f,'to'=>$t]) }}" class="btn-ghost btn-sm">{{ $label }}</a>
        @endforeach
    </div>

    {{-- Summary grid --}}
    <div class="row row-cols-2 row-cols-3 row-cols-4 gap-3 mb-4">
        @foreach([
            ['New Users',$stats['new_users'],'👥',''],
            ['New Sellers',$stats['verified_sellers'],'✅',''],
            ['New Listings',$stats['new_listings'],'🏷️',''],
            ['Published',$stats['published_listings'],'📋',''],
            ['Orders',$stats['orders'],'📦',''],
            ['Paid Orders',$stats['paid_orders'],'💳',''],
            ['Order Volume',\App\Support\Money::format($stats['order_volume']),'💰','money'],
            ['Platform Commission',\App\Support\Money::format($stats['platform_commission']),'🏦','money'],
            ['Seller Fees',\App\Support\Money::format($stats['seller_fees']),'📊','money'],
            ['Buyer Fees',\App\Support\Money::format($stats['buyer_fees']),'📊','money'],
            ['Promotions Sold',$stats['promotions_sold'],'⭐',''],
            ['Promotion Revenue',\App\Support\Money::format($stats['promotion_revenue']),'💫','money'],
            ['Withdrawals Completed',$stats['withdrawals'],'🏧',''],
            ['Withdrawal Payouts',\App\Support\Money::format($stats['withdrawal_amount']),'💸','money'],
            ['Support Tickets',$stats['tickets'],'🎧',''],
        ] as [$label,$value,$icon,$type])
            <div class="stat-card">
                <p class="stat-label">{{ $icon }} {{ $label }}</p>
                <p class="stat-value {{ $type==='money'?'money text-mint-700':'' }}">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    {{-- Daily order chart data --}}
    @if($dailyOrders->isNotEmpty())
    <x-card>
        <h2 class="section-title mb-3">Daily Paid Orders ({{ $from->format('d M') }} – {{ $to->format('d M Y') }})</h2>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Date</th><th>Orders</th><th>Volume</th></tr></thead>
                <tbody>
                @foreach($dailyOrders as $day)
                    <tr>
                        <td class="fs-sm">{{ \Carbon\Carbon::parse($day->date)->format('d M Y') }}</td>
                        <td>{{ $day->count }}</td>
                        <td class="money">{{ \App\Support\Money::format($day->volume) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
    @endif
</x-layouts.admin>
