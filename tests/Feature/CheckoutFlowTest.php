<?php
namespace Tests\Feature;

use App\Enums\InventoryType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/**
 * The checkout → gateway → return → success-page flow.
 *
 * The regression that started this: UddoktaPay's `return_type` defaults to POST,
 * so it hands the buyer back with a POST. The return endpoint was GET-only and
 * 405'd the buyer immediately after they had paid. The gateway decides that
 * method, so the endpoint accepts both — and it is a callback, redirecting to a
 * plain GET success page rather than rendering one from a POST.
 *
 * The real UddoktaPay API is never called: every request is faked, and
 * preventStrayRequests() fails the test if anything escapes.
 */
class CheckoutFlowTest extends TestCase
{
    use BuildsMarketplace;

    private const INVOICE_PREFIX = 'INV-TEST-';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'uddoktapay.api_key'  => 'test-key',
            'uddoktapay.base_url' => 'https://gateway.test/api',
        ]);

        Http::preventStrayRequests();
    }

    /**
     * Both gateway endpoints in one stub, keyed on the URL.
     *
     * Call this exactly once, before anything makes an HTTP call: Http::fake()
     * appends and the first matching stub wins, so a second fake() cannot
     * correct the first — a catch-all registered earlier would answer
     * verify-payment with the checkout payload and report status UNKNOWN.
     *
     * The invoice id is derived from the order rather than fixed, because
     * payments.gateway_payment_id is unique: a test that starts two purchases
     * would otherwise collide on the second one.
     */
    private function fakeGateway(string $status = 'COMPLETED'): void
    {
        Http::fake(function (Request $request) use ($status) {
            if (str_contains($request->url(), 'checkout-v2')) {
                $orderId = (int) data_get($request->data(), 'metadata.order_id');

                return Http::response([
                    'payment_url' => 'https://gateway.test/pay/' . $orderId,
                    'invoice_id'  => $this->invoiceFor($orderId),
                ]);
            }

            return Http::response([
                'status'         => $status,
                'transaction_id' => 'TXN-1',
                'amount'         => 5000.00,
                'metadata'       => ['order_id' => $this->orderIdFrom((string) $request['invoice_id'])],
            ]);
        });
    }

    private function invoiceFor(int $orderId): string
    {
        return self::INVOICE_PREFIX . $orderId;
    }

    private function orderIdFrom(string $invoiceId): string
    {
        return (string) (int) str_replace(self::INVOICE_PREFIX, '', $invoiceId);
    }

    // ── POST /checkout ───────────────────────────────────────────────

    public function test_checkout_post_creates_a_pending_order_and_hands_off_to_the_gateway(): void
    {
        $this->fakeGateway();
        $listing = $this->listing($this->seller(), InventoryType::Single, 5000);
        $buyer   = $this->buyer();

        $response = $this->actingAs($buyer)->post('/checkout', [
            'asset_id' => $listing->id,
            'quantity' => 1,
        ]);

        $order = Order::firstOrFail();
        // Inertia::location() answers a plain POST with a 302 to the gateway.
        $response->assertRedirect('https://gateway.test/pay/' . $order->id);

        $this->assertSame($buyer->id, (int) $order->buyer_user_id);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame(1, Payment::count());
    }

    public function test_the_checkout_page_renders_for_the_buyer(): void
    {
        $listing = $this->listing($this->seller(), InventoryType::Single, 5000);

        $this->actingAs($this->buyer())
            ->get("/checkout/{$listing->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Checkout/Show')
                ->where('order.asset_id', $listing->id)
                ->where('order.quantity', 1)
                ->where('has_bid', false)
            );
    }

    // ── The gateway's return ─────────────────────────────────────────

    /**
     * The reported bug. A POST return must be handled, not answered with
     * "The POST method is not supported for route checkout/success".
     */
    public function test_the_gateway_can_return_the_buyer_with_a_post(): void
    {
        [$buyer, $order] = $this->pendingOrder();

        $this->actingAs($buyer)
            ->post('/checkout/callback', ['invoice_id' => $this->invoiceFor($order->id)])
            ->assertRedirect(route('checkout.success', ['order' => $order->id]));

        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    /** The same endpoint still works when return_type=GET puts it in the query. */
    public function test_the_gateway_can_return_the_buyer_with_a_get(): void
    {
        [$buyer, $order] = $this->pendingOrder();

        $this->actingAs($buyer)
            ->get('/checkout/callback?invoice_id=' . $this->invoiceFor($order->id))
            ->assertRedirect(route('checkout.success', ['order' => $order->id]));

        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    /** We ask for GET returns, but the endpoint must survive either default. */
    public function test_the_gateway_is_asked_for_a_get_return_to_the_callback_route(): void
    {
        $this->fakeGateway();
        $listing = $this->listing($this->seller(), InventoryType::Single, 5000);

        $this->actingAs($this->buyer())->post('/checkout', [
            'asset_id' => $listing->id,
            'quantity' => 1,
        ]);

        Http::assertSent(function (Request $request) {
            if (!str_contains($request->url(), 'checkout-v2')) {
                return false;
            }

            return $request['return_type'] === 'GET'
                && $request['redirect_url'] === route('checkout.callback.return')
                && $request['cancel_url'] === route('checkout.callback.cancel');
        });
    }

    /** A POST from the gateway carries no CSRF token; it must not 419. */
    public function test_the_callback_does_not_require_a_csrf_token(): void
    {
        [$buyer, $order] = $this->pendingOrder();

        // withMiddleware() puts CSRF validation back, which the test harness
        // disables by default — without the exemption this is a 419.
        $this->actingAs($buyer)
            ->withMiddleware()
            ->post('/checkout/callback', ['invoice_id' => $this->invoiceFor($order->id)])
            ->assertRedirect(route('checkout.success', ['order' => $order->id]));
    }

    public function test_a_callback_with_no_invoice_is_turned_away_safely(): void
    {
        [$buyer, $order] = $this->pendingOrder();

        $this->actingAs($buyer)
            ->post('/checkout/callback', [])
            ->assertRedirect(route('dashboard.orders'))
            ->assertSessionHas('error');

        // Nothing was confirmed on the strength of an empty callback.
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_an_unpaid_invoice_does_not_confirm_the_order(): void
    {
        [$buyer, $order] = $this->pendingOrder(status: 'PENDING');

        $this->actingAs($buyer)
            ->post('/checkout/callback', ['invoice_id' => $this->invoiceFor($order->id)])
            ->assertRedirect(route('dashboard.orders'))
            ->assertSessionHas('error');

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    /** The gateway may hand the buyer back to the cancel URL by either method. */
    public function test_the_cancel_return_accepts_both_methods(): void
    {
        foreach (['get', 'post'] as $method) {
            [$buyer, $order] = $this->pendingOrder();

            $this->actingAs($buyer)
                ->withSession(['pending_order_id' => $order->id])
                ->{$method}('/checkout/cancel')
                ->assertRedirect(route('marketplace.index'));

            $this->assertSame('failed', $order->fresh()->payment_status);
        }
    }

    // ── Retries must not duplicate anything ──────────────────────────

    /**
     * A buyer reloading the gateway return, or the webhook landing alongside it,
     * replays the callback. confirmPayment() is idempotent, so the second pass
     * settles nothing new: same order, same payment, one unit sold.
     */
    public function test_replaying_the_callback_does_not_duplicate_the_order_or_payment(): void
    {
        [$buyer, $order, $listing] = $this->pendingOrder();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($buyer)
                ->post('/checkout/callback', ['invoice_id' => $this->invoiceFor($order->id)])
                ->assertRedirect(route('checkout.success', ['order' => $order->id]));
        }

        $this->assertSame(1, Order::count());
        $this->assertSame(1, Payment::count());
        $this->assertSame(1, Payment::where('status', 'paid')->count());
        $this->assertSame(1, (int) $listing->fresh()->sold_quantity);

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(OrderStatus::DeliveryPending, $order->status);
        // One credit to the seller, not three.
        $this->assertSame(
            (int) $order->seller_earning,
            (int) $order->seller->wallet->fresh()->pending_balance,
        );
    }

    /** The webhook and the browser return confirming the same invoice agree. */
    public function test_the_webhook_and_the_browser_return_cannot_both_confirm(): void
    {
        [$buyer, $order, $listing] = $this->pendingOrder();

        $this->post('/checkout/webhook', ['invoice_id' => $this->invoiceFor($order->id)])->assertOk();

        $this->actingAs($buyer)
            ->post('/checkout/callback', ['invoice_id' => $this->invoiceFor($order->id)])
            ->assertRedirect(route('checkout.success', ['order' => $order->id]));

        $this->assertSame(1, Payment::where('status', 'paid')->count());
        $this->assertSame(1, (int) $listing->fresh()->sold_quantity);
    }

    // ── GET /checkout/success ────────────────────────────────────────

    public function test_the_success_page_renders_for_the_buyer_who_paid(): void
    {
        [$buyer, $order] = $this->pendingOrder();
        $this->actingAs($buyer)->post('/checkout/callback', ['invoice_id' => $this->invoiceFor($order->id)]);

        $order->refresh();

        $this->actingAs($buyer)
            ->get(route('checkout.success', ['order' => $order->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Checkout/Success')
                ->where('order.order_number', $order->order_number)
                ->where('order.is_paid', true)
                ->where('order.buyer_total_formatted', Money::format((int) $order->buyer_total))
                ->where('order.url', route('dashboard.orders.show', $order))
                ->has('buyer_protection_hours')
            );
    }

    /** Nothing but the order id travels in the URL. */
    public function test_the_success_url_carries_no_payment_details(): void
    {
        [, $order] = $this->pendingOrder();

        $url = route('checkout.success', ['order' => $order->id]);

        $this->assertStringNotContainsString($this->invoiceFor((int) $order->id), $url);
        $this->assertStringNotContainsString((string) $order->buyer_total, $url);
        $this->assertStringNotContainsString($order->reference, $url);
        $this->assertSame(['order' => (string) $order->id], $this->queryOf($url));
    }

    public function test_a_stranger_cannot_view_someone_elses_success_page(): void
    {
        [, $order] = $this->pendingOrder();

        // The seller is a party to the order and still does not get the buyer's
        // payment confirmation; an unrelated user gets the same answer, so the
        // page cannot be used to find out which order ids exist.
        foreach ([$order->seller, $this->buyer()] as $intruder) {
            $this->actingAs($intruder)
                ->get(route('checkout.success', ['order' => $order->id]))
                ->assertRedirect(route('dashboard.orders'))
                ->assertSessionHas('error');
        }
    }

    public function test_a_guest_is_sent_to_login_rather_than_the_success_page(): void
    {
        [, $order] = $this->pendingOrder();

        $this->get(route('checkout.success', ['order' => $order->id]))
            ->assertRedirect('/login');
    }

    public function test_a_missing_or_unknown_order_reference_is_handled_safely(): void
    {
        $buyer = $this->buyer();

        foreach (['', '?order=', '?order=999999', '?order=not-a-number', '?order[]=1'] as $query) {
            $this->actingAs($buyer)
                ->get('/checkout/success' . $query)
                ->assertRedirect(route('dashboard.orders'))
                ->assertSessionHas('error');
        }
    }

    /** The success page is a page, not an action: it never accepts a POST. */
    public function test_the_success_page_is_get_only(): void
    {
        [$buyer, $order] = $this->pendingOrder();

        $this->actingAs($buyer)
            ->post('/checkout/success', ['order' => $order->id])
            ->assertStatus(405);
    }

    /**
     * A pending order for a fresh single listing.
     *
     * It registers the gateway stub itself, because the stub has to exist before
     * initiate() runs and Http::fake() cannot be corrected afterwards.
     *
     * @param string $status what verify-payment will report for the invoice
     * @return array{0: User, 1: Order, 2: \App\Models\Asset}
     */
    private function pendingOrder(string $status = 'COMPLETED'): array
    {
        $this->fakeGateway($status);

        $listing = $this->listing($this->seller(), InventoryType::Single, 5000);
        $buyer   = $this->buyer();

        $order = app(\App\Services\OrderService::class)
            ->initiate($listing, 1, $buyer, null, null)['order'];

        return [$buyer, $order, $listing];
    }

    /** @return array<string, string> */
    private function queryOf(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }
}
