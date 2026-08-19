<?php
namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Offer;
use App\Services\OfferService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Custom offers, which live only inside a chat.
 *
 * The old listing-level "Make an Offer" page is gone; an offer is now something
 * either party sends in the thread. Note what is *not* in the request payload:
 * no seller_id, no buyer_id, no listing id. The service derives all three from
 * the conversation's listing, so a crafted body cannot re-point an offer.
 */
class CustomOfferController extends Controller
{
    public function __construct(private readonly OfferService $offers) {}

    /** POST /dashboard/messages/{conversation}/offers */
    public function store(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->hasParticipant(Auth::id()), 403);

        $data = $request->validate([
            'amount_bdt'    => 'required|numeric|min:0.01|max:99999999',
            'quantity'      => 'nullable|integer|min:1|max:9999',
            'delivery_days' => 'nullable|integer|min:1|max:365',
            'note'          => 'nullable|string|max:1000',
        ]);

        $this->offers->createInConversation(
            $conversation,
            Auth::user(),
            Money::toPoisha($data['amount_bdt']),
            (int) ($data['quantity'] ?? 1),
            $data['delivery_days'] ?? null,
            $data['note'] ?? null,
        );

        return back()->with('success', 'Custom offer sent.');
    }

    /**
     * POST /offers/{offer}/accept
     *
     * The buyer accepting their counterpart's offer goes straight to payment —
     * that is the "Accept & Pay" button. A seller accepting the buyer's offer
     * just closes the negotiation; the buyer pays from their own side.
     */
    public function accept(Offer $offer)
    {
        $this->authorize('respond', $offer);

        $accepted = $this->offers->accept($offer, Auth::user());

        if ($accepted->isPayer(Auth::id())) {
            return redirect()->route('checkout.show', [
                'slug'  => $accepted->asset->slug,
                'offer' => $accepted->id,
                'qty'   => $accepted->quantity,
            ]);
        }

        return back()->with('success', 'Offer accepted. Waiting for the buyer to pay.');
    }

    /** POST /offers/{offer}/reject */
    public function reject(Offer $offer)
    {
        $this->authorize('respond', $offer);

        $this->offers->reject($offer, Auth::user());

        return back()->with('success', 'Offer declined.');
    }

    /** POST /offers/{offer}/cancel — withdraw an offer you sent. */
    public function cancel(Offer $offer)
    {
        $this->authorize('cancel', $offer);

        $this->offers->cancel($offer, Auth::user());

        return back()->with('success', 'Offer withdrawn.');
    }
}
