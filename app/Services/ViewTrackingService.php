<?php
namespace App\Services;

use App\Models\Asset;
use App\Models\AssetView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ViewTrackingService
{
    public function record(Asset $asset, Request $request): void
    {
        // One unique view per viewer+asset per day (privacy-safe)
        $hash = hash('sha256', ($request->ip() ?? '') . ($request->userAgent() ?? '') . today()->toDateString());

        try {
            AssetView::firstOrCreate(
                ['asset_id' => $asset->id, 'viewer_hash' => $hash, 'viewed_date' => today()],
                ['user_id' => Auth::id()]
            );
            // If a new record was inserted, increment the counter
            $asset->increment('views_count');
        } catch (\Throwable) {
            // Unique constraint violation = already counted today; ignore
        }
    }
}
