<?php
namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * UddoktaPay payment gateway integration.
 *
 * All gateway-specific logic is isolated here.
 * Credentials are environment-based; never hardcoded.
 *
 * API reference: https://uddoktapay.com/docs
 * Standard endpoints used:
 *   POST {base_url}/checkout-v2     — initiate payment session
 *   POST {base_url}/verify-payment  — verify gateway callback (server-side)
 *
 * IMPORTANT: UddoktaPay API credentials must be configured in .env:
 *   UDDOKTAPAY_API_KEY=
 *   UDDOKTAPAY_BASE_URL=
 */
class UddoktaPayService
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey  = config('uddoktapay.api_key', '');
        $this->baseUrl = rtrim(config('uddoktapay.base_url', ''), '/');
    }

    /**
     * Initiate a payment session with UddoktaPay.
     * Returns the checkout URL to redirect the buyer to.
     *
     * @throws \RuntimeException if API call fails or credentials are missing
     */
    public function initiate(Order $order, Payment $payment): string
    {
        $this->requireCredentials();

        $payload = [
            'full_name'     => $order->buyer->name,
            'email'         => $order->buyer->email,
            'amount'        => $this->formatAmount($order->buyer_total),
            'metadata'      => [
                'order_id'      => $order->id,
                'order_number'  => $order->order_number,
                'payment_id'    => $payment->id,
            ],
            'redirect_url'  => route('checkout.callback.return'),
            'cancel_url'    => route('checkout.callback.cancel'),
            'webhook_url'   => route('checkout.callback.webhook'),
            // UddoktaPay defaults return_type to POST, which sends invoice_id in
            // the request body. Asking for GET keeps the buyer's return a plain
            // navigation with invoice_id in the query string — no re-POST on
            // refresh, no CSRF token needed for a request we do not originate.
            // The callback route accepts both methods regardless, so a gateway
            // that ignores this field still lands somewhere that works.
            'return_type'   => 'GET',
        ];

        try {
            $response = $this->client()->post("{$this->baseUrl}/checkout-v2", $payload);
        } catch (\Throwable $e) {
            Log::error('UddoktaPay initiate failed', ['error' => $e->getMessage(), 'order' => $order->id]);
            throw new \RuntimeException('Payment gateway is temporarily unavailable. Please try again.');
        }

        if (!$response->successful()) {
            Log::warning('UddoktaPay API error', [
                'status' => $response->status(),
                'order'  => $order->id,
                // Never log the raw response in case it contains sensitive data
            ]);
            throw new \RuntimeException('Payment gateway returned an error. Please try again.');
        }

        $data = $response->json();

        if (empty($data['payment_url'])) {
            throw new \RuntimeException('Payment gateway did not return a checkout URL.');
        }

        // Store gateway payment ID for idempotency
        $payment->update(['gateway_payment_id' => $data['invoice_id'] ?? null]);

        return $data['payment_url'];
    }

    /**
     * Verify a payment with UddoktaPay server-side.
     * Called after redirect callback or webhook — never trust browser params.
     *
     * @param string $invoiceId The invoice_id returned by UddoktaPay
     * @return array{status:string, transaction_id:string, amount:float, metadata:array}|null
     */
    public function verify(string $invoiceId): ?array
    {
        $this->requireCredentials();

        try {
            $response = $this->client()->post("{$this->baseUrl}/verify-payment", [
                'invoice_id' => $invoiceId,
            ]);
        } catch (\Throwable $e) {
            Log::error('UddoktaPay verify failed', ['error' => $e->getMessage()]);
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();

        // UddoktaPay returns status: COMPLETED for success
        return [
            'status'         => $data['status'] ?? 'UNKNOWN',
            'transaction_id' => $data['transaction_id'] ?? null,
            'amount'         => (float)($data['amount'] ?? 0),
            'fee'            => (float)($data['fee'] ?? 0),
            'metadata'       => $data['metadata'] ?? [],
        ];
    }

    /** @throws \RuntimeException if API key is not configured */
    private function requireCredentials(): void
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('UddoktaPay API key is not configured. Set UDDOKTAPAY_API_KEY in .env');
        }
    }

    private function client(): PendingRequest
    {
        return Http::timeout(config('uddoktapay.timeout', 30))
            ->withHeaders([
                'RT-UDDOKTAPAY-API-KEY' => $this->apiKey,
                'Content-Type'          => 'application/json',
                'Accept'                => 'application/json',
            ]);
    }

    /**
     * UddoktaPay expects BDT amount as decimal string (e.g. "1000.00").
     * We convert from poisha safely.
     */
    private function formatAmount(int $poisha): string
    {
        return number_format($poisha / 100, 2, '.', '');
    }

    /** Check if credentials are configured (for health checks). */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->baseUrl);
    }
}
