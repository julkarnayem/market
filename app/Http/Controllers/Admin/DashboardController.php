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
use App\Support\Money;
use Inertia\Inertia;

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

        return Inertia::render('Admin/Index', [
            // Whitelisted to what the overview renders; revenue is pre-formatted
            // (integer poisha -> currency string) since Money owns formatting.
            'stats' => [
                'users'                   => $stats['users'],
                'active_users'            => $stats['active_users'],
                'published_listings'      => $stats['published_listings'],
                'orders_month'            => $stats['orders_month'],
                'revenue_month_formatted' => Money::format($stats['revenue_month']),
                'active_promotions'       => $stats['active_promotions'],
                'open_tickets'            => $stats['open_tickets'],
                'unassigned_tickets'      => $stats['unassigned_tickets'],
                'pending_verifications'   => $stats['pending_verifications'],
                'pending_listings'        => $stats['pending_listings'],
                'open_disputes'           => $stats['open_disputes'],
                'pending_withdrawals'     => $stats['pending_withdrawals'],
                'approved_withdrawals'    => $stats['approved_withdrawals'],
                'suspended_users'         => $stats['suspended_users'],
            ],
            'recentOrders' => $recentOrders->map(fn (Order $o) => [
                'id'              => $o->id,
                'order_number'    => $o->order_number,
                'asset_title'     => $o->asset?->title ?? '—',
                'total_formatted' => Money::format((int) $o->buyer_total),
                'url'             => route('admin.orders.show', $o),
            ])->values(),
            'recentTickets' => $recentTickets->map(fn (SupportTicket $t) => [
                'id'             => $t->id,
                'subject'        => $t->subject,
                'priority_label' => ucfirst($t->priority),
                'priority_color' => $t->priorityColor(),
                'user_name'      => $t->user?->name ?? 'Unknown',
                'url'            => route('admin.tickets.show', $t),
            ])->values(),
        ]);
    }

    public function section(string $title, string $part = 'the next release')
    {
        return view('admin.section', compact('title','part'));
    }
}
