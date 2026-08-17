<?php
namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Concerns\MapsMarketplaceProps;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Favorite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class FavoriteController extends Controller
{
    use MapsMarketplaceProps;

    public function index()
    {
        $mapAsset  = self::mapAsset();
        $favorites = Auth::user()->favorites()
            ->with(['asset.category', 'asset.seller', 'asset.coverImage'])
            ->latest()
            ->paginate(12)
            ->through(fn (Favorite $fav) => [
                'id'     => $fav->id,
                'asset'  => $mapAsset($fav->asset),
                'status' => $fav->asset->status->value,
            ]);

        return Inertia::render('Dashboard/Favorites', [
            'favorites' => $favorites,
        ]);
    }

    /** Toggle favorite — returns JSON for AJAX or redirects. */
    public function toggle(Request $request)
    {
        $request->validate(['asset_id' => 'required|integer|exists:assets,id']);
        $assetId = $request->asset_id;
        $userId  = Auth::id();

        $fav = Favorite::where('user_id', $userId)->where('asset_id', $assetId)->first();

        if ($fav) {
            $fav->delete();
            $favorited = false;
        } else {
            Favorite::create(['user_id' => $userId, 'asset_id' => $assetId]);
            $favorited = true;
        }

        if ($request->expectsJson()) {
            return response()->json(['favorited' => $favorited]);
        }
        return back()->with('success', $favorited ? 'Added to favorites.' : 'Removed from favorites.');
    }

    public function remove(Favorite $favorite)
    {
        abort_unless($favorite->user_id === Auth::id(), 403);
        $favorite->delete();
        return back()->with('success', 'Removed from favorites.');
    }
}
