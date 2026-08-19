<?php
namespace App\Http\Controllers;

use App\Enums\BidStatus;
use App\Models\Asset;
use App\Models\Bid;
use App\Models\Offer;
use App\Models\Order;
use App\Services\FeeCalculator;
use App\Services\OrderService;
use App\Services\SettingsService;
use App\Services\UddoktaPayService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly OrderService      $orders,
        private readonly FeeCalculator     $fees,
        private readonly UddoktaPayService $gateway,
    ) {}

    /** GET /checkout/{asset}?qty=1 — or ?offer={id} (custom offer) / ?bid={id} (accepted bid) */
    public function show(Request $request, string $slug, SettingsService $settings)
    {
        $user  = Auth::user();
        $asset = Asset::where('slug', $slug)->with(['seller','category','coverImage'])->firstOrFail();
        $offer = null;
        $bid   = null;

        abort_if(
            $request->filled('offer') && $request->filled('bid'),
            422,
            'A purchase cannot be both an accepted offer and an accepted bid.'
        );

        if ($request->filled('offer')) {
            $offer = Offer::where('id', $request->offer)
                ->where('buyer_user_id', $user->id)
                ->where('status', 'accepted')
                ->where('asset_id', $asset->id)
                ->firstOrFail();
        }

        if ($request->filled('bid')) {
            $bid = Bid::where('id', $request->bid)
                ->where('bidder_user_id', $user->id)
                ->where('status', BidStatus::Accepted->value)
                ->where('asset_id', $asset->id)
                ->firstOrFail();
        }

        // Quantity is only the buyer's to choose on a plain Buy Now. An accepted
        // offer carries the quantity it was agreed for, and a bid is always the
        // one single item — otherwise ?qty=99 would buy a whole shelf at the
        // negotiated unit price.
        $quantity = match (true) {
            $bid !== null   => 1,
            $offer !== null => max(1, (int) $offer->quantity),
            default         => max(1, (int) $request->get('qty', 1)),
        };

        // Server-side validation + fee calculation (never trust browser)
        try {
            $feeSnap = $this->orders->validateAndCalculate($asset, $quantity, $user, $offer, $bid);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        $unitPrice = match (true) {
            $bid !== null   => (int) $bid->amount,
            $offer !== null => (int) $offer->amount,
            default         => (int) $asset->price,
        };

        return Inertia::render('Checkout/Show', [
            'asset' => [
                'title'       => $asset->title,
                'url'         => route('marketplace.show', $asset->slug),
                'cover'       => $asset->coverImage?->url(),
                'icon'        => $asset->category->icon ?? '🧩',
                // The seller was dereferenced unguarded; a listing whose owner
                // has been removed 500'd the whole checkout.
                'seller_name' => $asset->seller?->name ?? 'Unknown seller',
            ],
            'fees' => [
                'unit_price'         => Money::format($unitPrice),
                'quantity'           => $quantity,
                'subtotal'           => Money::format($feeSnap['subtotal']),
                // The Blade guarded on enabled && amount > 0; keep that so a
                // 0-poisha buyer fee stays off the receipt.
                'has_buyer_fee'      => $feeSnap['buyer_fee_enabled'] && $feeSnap['buyer_fee_amount'] > 0,
                'buyer_fee_amount'   => Money::format($feeSnap['buyer_fee_amount']),
                'buyer_fee_percent'  => $feeSnap['buyer_fee_type'] === 'percentage' && $feeSnap['buyer_fee_bp'] !== null
                    ? number_format($feeSnap['buyer_fee_bp'] / 100, 2)
                    : null,
                'buyer_total'        => Money::format($feeSnap['buyer_total']),
                'seller_fee_percent' => number_format($feeSnap['seller_fee_bp'] / 100, 2),
                'seller_earning'     => Money::format($feeSnap['seller_earning']),
            ],
            'has_offer' => (bool) $offer,
            'has_bid'   => (bool) $bid,
            // Posted straight back to initiate(), which re-validates all of it.
            'order' => [
                'asset_id' => $asset->id,
                'quantity' => $quantity,
                'offer_id' => $offer?->id,
                'bid_id'   => $bid?->id,
            ],
            'gateway_configured' => $this->gateway->isConfigured(),
            // The Blade hard-coded "72-hour buyer protection" while the window is
            // admin-configurable (settings.buyer_protection_hours), so the promise
            // silently drifted from the value the orders actually get.
            'buyer_protection_hours' => $settings->buyerProtectionHours(),
        ]);
    }

    /** POST /checkout — initiate payment */
    public function initiate(Request $request)
    {
        $data = $request->validate([
            'asset_id' => 'required|integer|exists:assets,id',
            'quantity' => 'required|integer|min:1|max:9999',
            'offer_id' => 'nullable|integer|exists:offers,id',
            'bid_id'   => 'nullable|integer|exists:bids,id',
        ]);

        abort_if(
            !empty($data['offer_id']) && !empty($data['bid_id']),
            422,
            'A purchase cannot be both an accepted offer and an accepted bid.'
        );

        $user  = Auth::user();
        $asset = Asset::findOrFail($data['asset_id']);
        $offer = !empty($data['offer_id']) ? Offer::findOrFail($data['offer_id']) : null;
        $bid   = !empty($data['bid_id']) ? Bid::findOrFail($data['bid_id']) : null;

        // Re-validate offer belongs to this buyer. The buyer is always the payer,
        // even when the seller was the one who sent the offer.
        if ($offer) {
            abort_unless($offer->buyer_user_id === $user->id, 403);
            abort_unless($offer->status->value === 'accepted', 422, 'Offer is no longer in accepted state.');
            abort_unless((int) $offer->asset_id === (int) $asset->id, 422, 'Offer is for a different listing.');
        }

        // Re-validate the bid too: OrderService checks it again under a lock, but
        // a mismatched listing should never get as far as a fee calculation.
        if ($bid) {
            abort_unless((int) $bid->bidder_user_id === $user->id, 403);
            abort_unless($bid->status === BidStatus::Accepted, 422, 'Bid is no longer in accepted state.');
            abort_unless((int) $bid->asset_id === (int) $asset->id, 422, 'Bid is for a different listing.');
        }

        // The negotiated quantity, not the posted one.
        $quantity = match (true) {
            $bid !== null   => 1,
            $offer !== null => max(1, (int) $offer->quantity),
            default         => (int) $data['quantity'],
        };

        try {
            $result      = $this->orders->initiate($asset, $quantity, $user, $offer, $bid);
            $order       = $result['order'];
            $checkoutUrl = $result['checkoutUrl'];

            // Store order reference in session for callback handling
            session(['pending_order_id' => $order->id]);

            // Inertia::location, not redirect()->away(): the pay button is an
            // Inertia POST now, and its XHR cannot follow a 302 to the gateway's
            // origin. This answers an Inertia request with 409 +
            // X-Inertia-Location so the client does a real navigation, and falls
            // back to exactly the same Redirect::away() for a plain form post.
            return Inertia::location($checkoutUrl);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * GET|POST /checkout/callback — the buyer returning from UddoktaPay.
     *
     * UddoktaPay's `return_type` decides the method: it defaults to POST with
     * invoice_id in the body, and we ask for GET with it in the query string.
     * Both are accepted here because the method is the gateway's choice, not
     * ours — a GET-only route 405s the buyer straight after they have paid.
     *
     * This is a callback, not a page: it verifies, confirms, and redirects to
     * the buyer-facing success page. It renders nothing itself.
     */
    public function callback(Request $request)
    {
        // IMPORTANT: Never trust this redirect as proof of payment.
        // We re-verify server-side using the invoice_id.
        // input() reads the query string and the request body, so it covers
        // both return types without caring which one arrived.
        $invoiceId = $request->input('invoice_id');

        if (!$invoiceId) {
            return redirect()->route('dashboard.orders')->with('error', 'Invalid payment callback.');
        }

        try {
            $verified = $this->gateway->verify($invoiceId);
        } catch (\Throwable $e) {
            Log::error('UddoktaPay verify error on success redirect', ['error' => $e->getMessage()]);
            return redirect()->route('dashboard.orders')->with('error', 'Payment verification failed. Please contact support if payment was deducted.');
        }

        if (!$verified || strtoupper($verified['status']) !== 'COMPLETED') {
            return redirect()->route('dashboard.orders')->with('error', 'Payment was not completed. Please try again.');
        }

        return $this->processVerifiedPayment($invoiceId, $verified);
    }

    /**
     * GET /checkout/success?order={id} — the buyer's confirmation page.
     *
     * Purely a read: the payment was already verified and confirmed by the
     * callback (or the webhook), so refreshing or sharing this URL cannot
     * charge anything or move an order along.
     */
    public function success(Request $request, SettingsService $settings)
    {
        $order = Order::with(['asset.coverImage', 'asset.category', 'seller', 'conversation'])
            ->find($request->integer('order'));

        // An unknown reference and someone else's order get the same answer, so
        // the page cannot be used to probe which order ids exist. The order id
        // is all that travels in the URL — never an invoice, amount or token.
        if (!$order || (int) $order->buyer_user_id !== Auth::id()) {
            return redirect()->route('dashboard.orders')
                ->with('error', 'That order could not be found.');
        }

        return Inertia::render('Checkout/Success', [
            'order' => [
                'order_number'          => $order->order_number,
                'status_label'          => $order->status->label(),
                'is_paid'               => $order->payment_status === 'paid',
                'paid_at'               => $order->paid_at?->format('d M Y, H:i'),
                'quantity'              => (int) $order->quantity,
                'unit_price_formatted'  => Money::format((int) $order->unit_price),
                'buyer_total_formatted' => Money::format((int) $order->buyer_total),
                'asset_title'           => $order->asset?->title ?? 'Listing removed',
                'asset_cover'           => $order->asset?->coverImage?->url(),
                'asset_icon'            => $order->asset?->category->icon ?? '🧩',
                'seller_name'           => $order->seller?->name ?? 'Unknown seller',
                'url'                   => route('dashboard.orders.show', $order),
                'conversation_url'      => $order->conversation
                    ? route('dashboard.messages', ['conversation' => $order->conversation->id])
                    : null,
            ],
            'buyer_protection_hours' => $settings->buyerProtectionHours(),
        ]);
    }

    /** POST /checkout/webhook — server-to-server callback from UddoktaPay */
    public function webhook(Request $request)
    {
        // Webhook: UddoktaPay calls this directly — re-verify server-side
        $invoiceId = $request->input('invoice_id');
        if (!$invoiceId) return response('Missing invoice_id', 400);

        try {
            $verified = $this->gateway->verify($invoiceId);
        } catch (\Throwable $e) {
            Log::error('UddoktaPay webhook verify failed', ['error' => $e->getMessage()]);
            return response('Verification failed', 500);
        }

        if (!$verified || strtoupper($verified['status']) !== 'COMPLETED') {
            return response('Payment not completed', 200); // 200 to prevent re-delivery
        }

        try {
            $this->processVerifiedPayment($invoiceId, $verified, webhook: true);
        } catch (\Throwable $e) {
            Log::error('Webhook order confirm failed', ['error' => $e->getMessage()]);
            return response('Error processing', 500);
        }

        return response('OK', 200);
    }

    /** GET|POST /checkout/cancel — buyer cancelled at gateway */
    public function cancel()
    {
        $orderId = session('pending_order_id');
        if ($orderId) {
            $order = Order::find($orderId);
            if ($order && $order->buyer_user_id === Auth::id() && $order->payment_status === 'pending') {
                $this->orders->markPaymentFailed($order, 'Buyer cancelled at gateway');
            }
        }
        return redirect()->route('marketplace.index')->with('error', 'Payment was cancelled.');
    }

    private function processVerifiedPayment(string $invoiceId, array $verified, bool $webhook = false): mixed
    {
        $metadata = $verified['metadata'] ?? [];
        $orderId  = $metadata['order_id'] ?? null;

        try {
            $order = $this->orders->confirmPayment($invoiceId, $verified['transaction_id'] ?? '', $metadata);
            if ($webhook) return response('OK', 200);
            // The callback's job ends here: hand the browser a plain GET page.
            // confirmPayment() is idempotent, so a buyer who reloads the gateway
            // return lands on the same success page instead of paying twice.
            return redirect()->route('checkout.success', ['order' => $order->id])
                ->with('success', "Payment confirmed! Order #{$order->order_number} has been created.");
        } catch (\Throwable $e) {
            Log::error('Order confirm error', ['error' => $e->getMessage(), 'invoice' => $invoiceId]);
            if ($webhook) return response('Error', 500);
            return redirect()->route('dashboard.orders')->with('error', 'Payment confirmed but order setup encountered an issue. Contact support with reference: ' . $invoiceId);
        }
    }
}
