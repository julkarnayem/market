<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('reports.view');

        // The dates used to reach Carbon::parse() raw, so `?from=garbage` threw
        // InvalidFormatException and the page 500'd. A reversed range silently
        // returned zeroes; it is now a field error the form renders.
        $data = $request->validate([
            'from'   => 'nullable|date',
            'to'     => 'nullable|date|after_or_equal:from',
            'export' => 'nullable|in:csv',
        ]);

        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : now()->subDays(30)->startOfDay();
        $to   = isset($data['to'])   ? Carbon::parse($data['to'])->endOfDay()     : now()->endOfDay();

        $stats = [
            'new_users'          => User::whereBetween('created_at',[$from,$to])->count(),
            'verified_sellers'   => User::where('verification_status','approved')->whereBetween('created_at',[$from,$to])->count(),
            'new_listings'       => Asset::whereBetween('created_at',[$from,$to])->count(),
            'published_listings' => Asset::where('status','published')->whereBetween('created_at',[$from,$to])->count(),
            'orders'             => Order::whereBetween('created_at',[$from,$to])->count(),
            'paid_orders'        => Order::where('payment_status','paid')->whereBetween('paid_at',[$from,$to])->count(),
            'order_volume'       => (int) Order::where('payment_status','paid')->whereBetween('paid_at',[$from,$to])->sum('buyer_total'),
            'platform_commission'=> (int) Order::where('payment_status','paid')->whereBetween('paid_at',[$from,$to])->sum('platform_commission'),
            'seller_fees'        => (int) Order::where('payment_status','paid')->whereBetween('paid_at',[$from,$to])->sum('seller_fee_amount'),
            'buyer_fees'         => (int) Order::where('payment_status','paid')->whereBetween('paid_at',[$from,$to])->sum('buyer_fee_amount'),
            'promotions_sold'    => Promotion::where('is_manual',false)->where('payment_status','paid')->whereBetween('created_at',[$from,$to])->count(),
            'promotion_revenue'  => (int) Promotion::where('is_manual',false)->where('payment_status','paid')->whereBetween('created_at',[$from,$to])->sum('price'),
            'withdrawals'        => Withdrawal::where('status','completed')->whereBetween('created_at',[$from,$to])->count(),
            'withdrawal_amount'  => (int) Withdrawal::where('status','completed')->whereBetween('created_at',[$from,$to])->sum('net_amount'),
            'tickets'            => SupportTicket::whereBetween('created_at',[$from,$to])->count(),
        ];

        $metrics = $this->metrics($stats);

        if (($data['export'] ?? null) === 'csv') {
            return $this->exportCsv($metrics, $from, $to);
        }

        // Daily paid-order volume inside the range.
        $daily = Order::where('payment_status','paid')
            ->whereBetween('paid_at',[$from,$to])
            ->selectRaw('DATE(paid_at) as date, COUNT(*) as count, SUM(buyer_total) as volume')
            ->groupBy('date')->orderBy('date')->get()
            ->map(fn ($day) => [
                'date'   => Carbon::parse($day->date)->format('d M Y'),
                'orders' => (int) $day->count,
                'volume' => Money::format((int) $day->volume),
            ])->all();

        return Inertia::render('Admin/Reports/Index', [
            'metrics' => $metrics,
            'daily'   => $daily,
            'filters' => [
                'from'  => $from->format('Y-m-d'),
                'to'    => $to->format('Y-m-d'),
                'label' => $from->format('d M').' – '.$to->format('d M Y'),
            ],
            // The server owns the clock, so the quick-range buttons are computed
            // here rather than from the browser's timezone.
            'presets' => $this->presets(),
        ]);
    }

    /**
     * The report's metrics in display order, money already formatted.
     *
     * One list drives both the page and the CSV. They used to disagree: the
     * export derived its labels with ucwords() (so "Withdrawal Amount") while
     * the view hardcoded its own ("Withdrawal Payouts"), and both decided what
     * counted as money by substring-matching the key — `seller_fees` only
     * formatted as currency because it happens to contain "fee".
     *
     * @param  array<string,int>  $stats
     * @return list<array{key:string,label:string,icon:string,is_money:bool,value:string}>
     */
    private function metrics(array $stats): array
    {
        $spec = [
            ['new_users',           'New Users',             '👥', false],
            ['verified_sellers',    'New Verified Sellers',  '✅', false],
            ['new_listings',        'New Listings',          '🏷️', false],
            ['published_listings',  'Published Listings',    '📋', false],
            ['orders',              'Orders',                '📦', false],
            ['paid_orders',         'Paid Orders',           '💳', false],
            ['order_volume',        'Order Volume',          '💰', true],
            ['platform_commission', 'Platform Commission',   '🏦', true],
            ['seller_fees',         'Seller Fees',           '📊', true],
            ['buyer_fees',          'Buyer Fees',            '📊', true],
            ['promotions_sold',     'Promotions Sold',       '⭐', false],
            ['promotion_revenue',   'Promotion Revenue',     '💫', true],
            ['withdrawals',         'Withdrawals Completed', '🏧', false],
            ['withdrawal_amount',   'Withdrawal Payouts',    '💸', true],
            ['tickets',             'Support Tickets',       '🎧', false],
        ];

        return array_map(fn (array $m) => [
            'key'      => $m[0],
            'label'    => $m[1],
            'icon'     => $m[2],
            'is_money' => $m[3],
            'value'    => $m[3] ? Money::format($stats[$m[0]]) : number_format($stats[$m[0]]),
        ], $spec);
    }

    /** @return list<array{label:string,from:string,to:string}> */
    private function presets(): array
    {
        $today = now()->format('Y-m-d');

        return [
            ['label' => 'Today',      'from' => $today,                                    'to' => $today],
            ['label' => '7 days',     'from' => now()->subDays(7)->format('Y-m-d'),        'to' => $today],
            ['label' => '30 days',    'from' => now()->subDays(30)->format('Y-m-d'),       'to' => $today],
            ['label' => 'This month', 'from' => now()->startOfMonth()->format('Y-m-d'),    'to' => $today],
        ];
    }

    /** @param  list<array{label:string,value:string}>  $metrics */
    private function exportCsv(array $metrics, Carbon $from, Carbon $to)
    {
        $csv = "Metric,Value\n";
        foreach ($metrics as $m) {
            $csv .= '"'.$m['label'].'","'.$m['value']."\"\n";
        }

        $filename = "report-{$from->format('Y-m-d')}-to-{$to->format('Y-m-d')}.csv";

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
