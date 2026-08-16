<?php
namespace App\Http\Controllers\Admin;

use App\Enums\AssetStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Withdrawal;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            // Users
            'users'                  => User::doesntHave('roles')->count(),
            'active_users'           => User::doesntHave('roles')->where('status','active')->count(),
            'verified_sellers'       => User::where('verification_status','approved')->count(),
            'suspended_users'        => User::where('status','suspended')->count(),
            'pending_verifications'  => User::where('verification_status','pending')->count(),
            // Listings
            'pending_listings'       => Asset::where('status', AssetStatus::PendingReview)->count(),
            'published_listings'     => Asset::where('status', AssetStatus::Published)->count(),
            // Orders
            'orders_today'           => Order::whereDate('created_at', today())->count(),
            'orders_month'           => Order::whereMonth('created_at', now()->month)->count(),
            'revenue_month'          => (int) Order::where('payment_status','paid')
                                            ->whereMonth('paid_at', now()->month)->sum('platform_commission'),
            // Disputes
            'open_disputes'          => Dispute::where('status','open')->count(),
            // Finance
            'pending_withdrawals'    => Withdrawal::where('status','pending')->count(),
            'approved_withdrawals'   => Withdrawal::where('status','approved')->count(),
            // Promotions
            'active_promotions'      => Promotion::where('status','active')->where('ends_at','>',now())->count(),
            // Support
            'open_tickets'           => SupportTicket::whereIn('status',['open','in_progress'])->count(),
            'unassigned_tickets'     => SupportTicket::whereNull('assigned_to')->whereIn('status',['open'])->count(),
        ];

        // Recent activity
        $recentOrders = Order::with('buyer','asset')->latest()->limit(5)->get();
        $recentTickets= SupportTicket::with('user')->whereIn('status',['open','in_progress'])->latest()->limit(5)->get();

        return view('admin.index', compact('stats','recentOrders','recentTickets'));
    }

    public function section(string $title, string $part = 'the next release')
    {
        return view('admin.section', compact('title','part'));
    }
}
