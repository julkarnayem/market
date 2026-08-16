<?php
namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(string $identifier, Request $request)
    {
        $user = User::where('username', $identifier)
            ->orWhere('id', $identifier)
            ->firstOrFail();

        $tab = $request->get('tab', 'listings');

        // Stats
        $stats = [
            'listed'    => $user->listings()->published()->count(),
            'sold'      => $user->sales()->where('status', OrderStatus::Completed)->count(),
            'purchases' => $user->purchases()->where('status', OrderStatus::Completed)->count(),
            'reviews'   => 0, // reserved for future
            'trades'    => $user->sales()->where('status', OrderStatus::Completed)->count()
                         + $user->purchases()->where('status', OrderStatus::Completed)->count(),
        ];

        // Active listings tab
        $listings = collect();
        if ($tab === 'listings') {
            $q = $user->listings()->published()->with(['category','coverImage']);
            if ($request->filled('q')) $q->where('title','like','%'.$request->q.'%');
            $listings = $q->latest()->paginate(12)->withQueryString();
        }

        // Completed sales tab
        $completedSales = collect();
        if ($tab === 'sales') {
            $completedSales = $user->sales()
                ->where('status', OrderStatus::Completed)
                ->with(['asset.category','asset.coverImage'])
                ->latest()->paginate(12)->withQueryString();
        }

        // Completed purchases tab
        $completedPurchases = collect();
        if ($tab === 'purchases') {
            $completedPurchases = $user->purchases()
                ->where('status', OrderStatus::Completed)
                ->with(['asset.category','asset.coverImage'])
                ->latest()->paginate(12)->withQueryString();
        }

        // Reviews tab
        $reviews = collect();
        if ($tab === 'reviews') {
            $reviews = \App\Models\Review::where('seller_id', $user->id)
                ->with(['reviewer','asset'])
                ->latest()->paginate(10)->withQueryString();
        }

        // Update reviews count in stats
        $stats['reviews'] = \App\Models\Review::where('seller_id', $user->id)->count();

        $isOwnProfile = auth()->check() && auth()->id() === $user->id;

        return view('profile.show', compact(
            'user','tab','stats','listings',
            'completedSales','completedPurchases','reviews','isOwnProfile'
        ));
    }
}
