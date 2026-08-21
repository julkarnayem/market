<?php
namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * The buyer's review of a purchase.
 *
 * An order here carries exactly one asset (there is no order-items table), so one
 * review per order *is* one review per purchased product — and the unique index on
 * reviews.order_id is what enforces "once". Both actions run the same eligibility
 * chain, and nothing is taken from the request but the rating and the comment:
 * reviewer, seller and asset are all read off the order the route resolved.
 */
class ReviewController extends Controller
{
    public function create(Order $order)
    {
        $this->assertReviewable($order);

        $order->load('asset', 'seller');

        return Inertia::render('Dashboard/Orders/Review', [
            'order' => [
                'id'          => $order->id,
                'asset_title' => $order->asset?->title ?? '—',
                'seller_name' => $order->seller?->name ?? '—',
            ],
        ]);
    }

    public function store(Request $request, Order $order)
    {
        $this->assertReviewable($order);

        $data = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            // The existing schema and form both treat the comment as optional;
            // preserved rather than tightened.
            'comment' => 'nullable|string|max:1000',
        ]);

        // Everything but the rating and comment is derived from the order, so a
        // forged reviewer_id/seller_id/asset_id in the payload has nothing to bind
        // to. The unique index is the backstop if two submits race.
        Review::create([
            'order_id'    => $order->id,
            'reviewer_id' => Auth::id(),
            'seller_id'   => $order->seller_user_id,
            'asset_id'    => $order->asset_id,
            'rating'      => $data['rating'],
            'comment'     => $data['comment'] ?? null,
        ]);

        return redirect()->route('dashboard.orders.show', $order)
            ->with('success', 'Thank you for your review!');
    }

    /**
     * The whole eligibility chain, in one place so create() and store() cannot
     * drift apart — the previous version let the page render for a state the
     * submit would then refuse.
     *
     * authenticated → owns the order → the asset reached them → not already
     * reviewed. "The product is in the order" and "the order item matches the
     * product" are structural here: the order *is* the line item, and the asset is
     * read from it rather than accepted from the caller.
     */
    private function assertReviewable(Order $order): void
    {
        // Ownership: only the buyer on this order, never the seller, never a
        // third party who guessed an id.
        abort_unless((int) $order->buyer_user_id === Auth::id(), 403, 'This is not your order.');

        // Delivery: keyed on the order status enum, which is the project's single
        // source of truth for "the buyer has it".
        abort_unless(
            $order->status->canBeReviewed(),
            403,
            'You can review once the order has been delivered.',
        );

        abort_if(
            Review::where('order_id', $order->id)->exists(),
            403,
            'You have already reviewed this order.',
        );
    }
}
