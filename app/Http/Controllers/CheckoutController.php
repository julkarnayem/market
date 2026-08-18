<?php
namespace App\Http\Controllers;

use App\Models\Asset;
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

    /** GET /checkout/{asset}?qty=1  or  /checkout/{asset}?offer={id} */
    public function show(Request $request, string $slug, SettingsService $settings)
    {
        $user  = Auth::user();
        $asset = Asset::where('slug', $slug)->with(['seller','category','coverImage'])->firstOrFail();
        $offer = null;

        if ($request->filled('offer')) {
            $offer = Offer::where('id', $request->offer)
                ->where('buyer_user_id', $user->id)
                ->where('status', 'accepted')
                ->where('asset_id', $asset->id)
                ->firstOrFail();
        }

        $quantity = max(1, (int)$request->get('qty', 1));

        // Server-side validation + fee calculation (never trust browser)
        try {
            $feeSnap = $this->orders->validateAndCalculate($asset, $quantity, $user, $offer);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        $unitPrice = $offer ? $offer->amount : $asset->price;

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
            // Posted straight back to initiate(), which re-validates all of it.
            'order' => [
                'asset_id' => $asset->id,
                'quantity' => $quantity,
                'offer_id' => $offer?->id,
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
        ]);

        $user  = Auth::user();
        $asset = Asset::findOrFail($data['asset_id']);
        $offer = $data['offer_id'] ? Offer::findOrFail($data['offer_id']) : null;

        // Re-validate offer belongs to this buyer
        if ($offer) {
            abort_unless($offer->buyer_user_id === $user->id, 403);
            abort_unless($offer->status->value === 'accepted', 422, 'Offer is no longer in accepted state.');
        }

        try {
            $result      = $this->orders->initiate($asset, $data['quantity'], $user, $offer);
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

    /** GET /checkout/success — redirect callback from UddoktaPay */
    public function success(Request $request)
    {
        // IMPORTANT: Never trust this redirect as proof of payment.
        // We re-verify server-side using the invoice_id.
        $invoiceId = $request->get('invoice_id');

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

    /** GET /checkout/cancel — buyer cancelled at gateway */
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
            return redirect()->route('dashboard.orders.show', $order)
                ->with('success', "Payment confirmed! Order #{$order->order_number} has been created.");
        } catch (\Throwable $e) {
            Log::error('Order confirm error', ['error' => $e->getMessage(), 'invoice' => $invoiceId]);
            if ($webhook) return response('Error', 500);
            return redirect()->route('dashboard.orders')->with('error', 'Payment confirmed but order setup encountered an issue. Contact support with reference: ' . $invoiceId);
        }
    }
}
