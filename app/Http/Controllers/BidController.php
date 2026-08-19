<?php
namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Bid;
use App\Services\BidService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Public bidding on a listing.
 *
 * Every rule that matters lives on the server: the policy decides who may act,
 * and BidService re-checks the inventory type, the listing status and the
 * current top bid inside a locked transaction. Hiding a button in Vue is a
 * convenience, never the enforcement.
 */
class BidController extends Controller
{
    public function __construct(private readonly BidService $bids) {}

    /** POST /listings/{asset}/bids */
    public function store(Request $request, Asset $asset)
    {
        // Rejects Multiple and Unlimited listings, the seller bidding on their
        // own item, and anything not currently biddable.
        $this->authorize('create', [Bid::class, $asset]);

        $data = $request->validate([
            'amount_bdt' => 'required|numeric|min:0.01|max:99999999',
        ]);

        $bid = $this->bids->place(Auth::user(), $asset, Money::toPoisha($data['amount_bdt']));

        return back()->with('success', 'Your bid of ' . Money::format((int) $bid->amount) . ' has been placed.');
    }

    /** POST /bids/{bid}/accept — seller only. */
    public function accept(Bid $bid)
    {
        $this->authorize('accept', $bid);

        $this->bids->accept($bid, Auth::user());

        return back()->with(
            'success',
            'Bid accepted. The buyer has been asked to pay ' . Money::format((int) $bid->amount) . '.'
        );
    }

    /** POST /bids/{bid}/reject — seller only. */
    public function reject(Bid $bid)
    {
        $this->authorize('reject', $bid);

        $this->bids->reject($bid, Auth::user());

        return back()->with('success', 'Bid rejected.');
    }

    /** POST /bids/{bid}/cancel — the bidder withdraws their own bid. */
    public function cancel(Bid $bid)
    {
        $this->authorize('cancel', $bid);

        $this->bids->cancel($bid, Auth::user());

        return back()->with('success', 'Bid withdrawn.');
    }
}
