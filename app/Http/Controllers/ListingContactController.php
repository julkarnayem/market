<?php
namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\ConversationService;
use Illuminate\Support\Facades\Auth;

/**
 * "Contact Seller" — not a system of its own, just the door into the existing
 * buyer↔seller chat. The same buyer, seller and listing always land back in the
 * same thread instead of piling up duplicates.
 */
class ListingContactController extends Controller
{
    public function __invoke(Asset $asset, ConversationService $conversations)
    {
        $conversation = $conversations->forListing(Auth::user(), $asset);

        return redirect()->route('dashboard.messages', ['conversation' => $conversation->id]);
    }
}
