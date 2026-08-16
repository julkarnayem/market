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
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('reports.view');

        $from = $request->from ? \Carbon\Carbon::parse($request->from)->startOfDay() : now()->subDays(30)->startOfDay();
        $to   = $request->to   ? \Carbon\Carbon::parse($request->to)->endOfDay()     : now()->endOfDay();

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

        // Daily order volume for chart (last 30 days within range)
        $dailyOrders = Order::where('payment_status','paid')
            ->whereBetween('paid_at',[$from,$to])
            ->selectRaw('DATE(paid_at) as date, COUNT(*) as count, SUM(buyer_total) as volume')
            ->groupBy('date')->orderBy('date')->get();

        if ($request->export === 'csv') {
            return $this->exportCsv($stats, $from, $to);
        }

        return view('admin.reports', compact('stats','dailyOrders','from','to'));
    }

    private function exportCsv(array $stats, $from, $to)
    {
        $csv = "Metric,Value\n";
        foreach ($stats as $k => $v) {
            $label = ucwords(str_replace('_',' ',$k));
            $val   = str_contains($k,'amount') || str_contains($k,'volume') || str_contains($k,'commission') || str_contains($k,'fee') || str_contains($k,'revenue')
                ? Money::format($v) : number_format($v);
            $csv  .= "\"{$label}\",\"{$val}\"\n";
        }
        $filename = "report-{$from->format('Y-m-d')}-to-{$to->format('Y-m-d')}.csv";
        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
