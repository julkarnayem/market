<?php
namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Concerns\MapsMarketplaceProps;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProfileController extends Controller
{
    use MapsMarketplaceProps;

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

        // The three asset-backed tabs share one prop, since only the active tab
        // is ever queried; sales/purchases map their order through to its asset.
        $assets = match ($tab) {
            'listings'  => $listings->through(self::mapAsset()),
            'sales'     => $completedSales
                ->setCollection($completedSales->getCollection()->filter(fn ($o) => $o->asset !== null)->values())
                ->through(fn ($order) => self::mapAsset()($order->asset)),
            'purchases' => $completedPurchases
                ->setCollection($completedPurchases->getCollection()->filter(fn ($o) => $o->asset !== null)->values())
                ->through(fn ($order) => self::mapAsset()($order->asset)),
            default     => null,
        };

        return Inertia::render('Profile/Show', [
            'profile' => [
                'name'               => $user->name,
                'initial'            => strtoupper(mb_substr($user->name, 0, 1)),
                'is_verified_seller' => $user->isVerifiedSeller(),
                'has_phone'          => (bool) $user->phone,
                'member_since'       => $user->created_at?->format('Y-m'),
                'profile_url'        => route('profile.show', $user->username ?? $user->id),
            ],
            'stats'        => $stats,
            'tab'          => $tab,
            'assets'       => $assets,
            'reviews'      => $tab === 'reviews'
                ? $reviews->through(fn ($review) => [
                    'id'              => $review->id,
                    'reviewer_name'   => $review->reviewer?->name ?? 'Deleted user',
                    'reviewer_initial'=> strtoupper(mb_substr($review->reviewer?->name ?? '?', 0, 1)),
                    'rating'          => (int) $review->rating,
                    'comment'         => $review->comment,
                    'created_at'      => $review->created_at?->format('d M Y'),
                    'asset_title'     => $review->asset?->title,
                ])
                : null,
            'isOwnProfile' => $isOwnProfile,
        ]);
    }
}
