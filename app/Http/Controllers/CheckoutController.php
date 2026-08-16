<?php
namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Offer;
use App\Models\Order;
use App\Services\FeeCalculator;
use App\Services\OrderService;
use App\Services\UddoktaPayService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly OrderService      $orders,
        private readonly FeeCalculator     $fees,
        private readonly UddoktaPayService $gateway,
    ) {}

    /** GET /checkout/{asset}?qty=1  or  /checkout/{asset}?offer={id} */
    public function show(Request $request, string $slug)
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

        $gatewayConfigured = $this->gateway->isConfigured();

        return view('checkout.show', compact('asset','offer','quantity','feeSnap','gatewayConfigured'));
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

            return redirect()->away($checkoutUrl);
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
