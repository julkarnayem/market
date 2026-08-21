<?php
namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\BidStatus;
use App\Enums\InventoryType;
use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Models\Asset;
use App\Models\Bid;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\Order;
use App\Models\User;
use App\Services\BidService;
use App\Services\ConversationService;
use App\Services\DisputeService;
use App\Services\ListingService;
use App\Services\OfferService;
use App\Services\OrderService;
use App\Support\Money;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/**
 * Inventory behaviour across the three listing types, and the slug rule.
 *
 * Purchases run through OrderService (initiate → confirmPayment) with the
 * payment gateway faked, so the real UddoktaPay API is never touched — see
 * fakeGateway(), which also fails the test on any stray outbound request.
 */
class ListingInventoryTest extends TestCase
{
    use BuildsMarketplace;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'uddoktapay.api_key'  => 'test-key',
            'uddoktapay.base_url' => 'https://gateway.test/api',
        ]);

        // One stub for the whole test: Http::fake() appends, and the first
        // matching stub wins, so re-faking per purchase would keep replaying the
        // first invoice id — and gateway_payment_id is unique. Deriving the
        // invoice from the order in the payload keeps every purchase distinct.
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $orderId = data_get($request->data(), 'metadata.order_id');

            return Http::response([
                'payment_url' => 'https://gateway.test/pay/' . $orderId,
                'invoice_id'  => $this->invoiceFor((int) $orderId),
            ]);
        });
    }

    private function invoiceFor(int $orderId): string
    {
        return 'INV-' . $orderId;
    }

    /** A full paid purchase: pending order → confirmed payment. */
    private function purchase(
        User $buyer,
        Asset $listing,
        int $quantity = 1,
        ?Offer $offer = null,
        ?Bid $bid = null,
    ): Order {
        $orders = app(OrderService::class);
        $result = $orders->initiate($listing->fresh(), $quantity, $buyer, $offer, $bid);

        return $this->settle($result);
    }

    /** Order rows only, no payment — for racing two buyers at one item. */
    private function startPurchase(User $buyer, Asset $listing, int $quantity = 1): array
    {
        return app(OrderService::class)->initiate($listing->fresh(), $quantity, $buyer, null, null);
    }

    private function settle(array $started): Order
    {
        $orderId = (int) $started['order']->id;

        return app(OrderService::class)->confirmPayment(
            $this->invoiceFor($orderId),
            'TXN-' . $orderId,
            ['order_id' => $orderId],
        );
    }

    // ── Single / unique ──────────────────────────────────────────────

    public function test_buying_a_single_listing_consumes_the_unique_item(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);

        $order = $this->purchase($this->buyer(), $listing);

        $listing->refresh();
        $this->assertSame(AssetStatus::SoldOut, $listing->status);
        $this->assertSame(0, (int) $listing->available_quantity);
        $this->assertSame(1, (int) $listing->sold_quantity);
        $this->assertTrue($listing->isSoldOut());
        $this->assertFalse($listing->isAvailableForPurchase());

        $this->assertSame(OrderStatus::DeliveryPending, $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(500000, (int) $order->unit_price);
    }

    public function test_a_sold_single_listing_cannot_be_bought_again(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $this->purchase($this->buyer(), $listing);

        $this->expectException(HttpException::class);
        $this->purchase($this->buyer(), $listing);
    }

    /**
     * §24: two buyers must never both get the one unique item. Both orders are
     * created while it is still on the market, then only the first payment lands.
     */
    public function test_two_buyers_cannot_both_pay_for_the_same_unique_item(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);

        $first  = $this->startPurchase($this->buyer(), $listing);
        $second = $this->startPurchase($this->buyer(), $listing);

        $this->settle($first);

        try {
            $this->settle($second);
            $this->fail('The second buyer was allowed to pay for an item that was already sold.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $listing->refresh();
        $this->assertSame(1, (int) $listing->sold_quantity);
        $this->assertSame(0, (int) $listing->available_quantity);
        $this->assertSame('pending', $second['order']->fresh()->payment_status);
    }

    /** Buying it outright ends the auction: open bids have nothing left to win. */
    public function test_buying_a_single_listing_outright_expires_the_open_bids(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);

        $bidOne = app(BidService::class)->place($this->buyer(), $listing, 450000);
        $bidTwo = app(BidService::class)->place($this->buyer(), $listing, 460000);

        $this->purchase($this->buyer(), $listing);

        $this->assertSame(BidStatus::Expired, $bidOne->fresh()->status);
        $this->assertSame(BidStatus::Expired, $bidTwo->fresh()->status);
    }

    public function test_the_winning_bidder_pays_the_bid_amount_and_takes_the_item(): void
    {
        $seller  = $this->seller();
        $winner  = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);

        $bid = app(BidService::class)->place($winner, $listing, 450000);
        app(BidService::class)->accept($bid, $seller);

        $order = $this->purchase($winner, $listing, 1, null, $bid->fresh());

        $this->assertSame(450000, (int) $order->unit_price);
        $this->assertSame($bid->id, (int) $order->bid_id);

        $listing->refresh();
        $this->assertSame(AssetStatus::SoldOut, $listing->status);
        $this->assertSame(1, (int) $listing->sold_quantity);
        $this->assertSame(BidStatus::Accepted, $bid->fresh()->status);
    }

    // ── Multiple quantity ────────────────────────────────────────────

    public function test_each_sale_counts_a_multiple_listing_down_to_sold_out(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Multiple, 5000, 3);

        foreach ([2, 1, 0] as $remaining) {
            $this->purchase($this->buyer(), $listing);

            $listing->refresh();
            $this->assertSame($remaining, (int) $listing->available_quantity);
            $this->assertSame(3 - $remaining, (int) $listing->sold_quantity);

            // Only the last unit closes the listing.
            $this->assertSame(
                $remaining === 0 ? AssetStatus::SoldOut : AssetStatus::Published,
                $listing->status,
            );
        }

        $this->assertSame(3, Order::count());
    }

    public function test_a_multiple_listing_sells_several_units_in_one_order(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Multiple, 5000, 10);

        $order = $this->purchase($this->buyer(), $listing, 4);

        $listing->refresh();
        $this->assertSame(6, (int) $listing->available_quantity);
        $this->assertSame(4, (int) $listing->sold_quantity);
        $this->assertSame(AssetStatus::Published, $listing->status);
        // 4 units at the asking price, priced per unit.
        $this->assertSame(500000, (int) $order->unit_price);
        $this->assertSame(2000000, (int) $order->subtotal);
    }

    public function test_a_multiple_listing_refuses_an_order_larger_than_its_stock(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Multiple, 5000, 2);

        $this->expectException(HttpException::class);
        $this->purchase($this->buyer(), $listing, 3);
    }

    public function test_a_sold_out_multiple_listing_stops_selling(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Multiple, 5000, 1);

        $this->purchase($this->buyer(), $listing);
        $this->assertSame(AssetStatus::SoldOut, $listing->fresh()->status);

        $this->expectException(HttpException::class);
        $this->purchase($this->buyer(), $listing);
    }

    // ── Unlimited ────────────────────────────────────────────────────

    public function test_an_unlimited_listing_sells_forever_and_stays_active(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Unlimited, 5000);

        for ($i = 0; $i < 5; $i++) {
            $this->purchase($this->buyer(), $listing);
        }

        $listing->refresh();
        $this->assertSame(AssetStatus::Published, $listing->status);
        $this->assertFalse($listing->isSoldOut());
        $this->assertTrue($listing->isAvailableForPurchase());
        // Stock never moves; only the sold counter does.
        $this->assertSame(1, (int) $listing->available_quantity);
        $this->assertSame(5, (int) $listing->sold_quantity);
        $this->assertSame(5, Order::count());
        $this->assertSame(5, Order::distinct()->count('buyer_user_id'));
    }

    public function test_one_unlimited_order_can_be_for_many_units(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Unlimited, 5000);

        $this->purchase($this->buyer(), $listing, 50);

        $listing->refresh();
        $this->assertSame(AssetStatus::Published, $listing->status);
        $this->assertSame(50, (int) $listing->sold_quantity);
        $this->assertSame(1, (int) $listing->available_quantity);
    }

    // ── Custom offers against inventory ──────────────────────────────

    public function test_paying_an_accepted_offer_marks_it_paid_and_consumes_a_single_item(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);

        $conv  = app(ConversationService::class)->forListing($buyer, $listing);
        $offer = app(OfferService::class)->createInConversation($conv, $buyer, Money::toPoisha(4200));
        app(OfferService::class)->accept($offer, $seller);

        $order = $this->purchase($buyer, $listing, 1, $offer->fresh());

        $this->assertSame(420000, (int) $order->unit_price);
        $this->assertSame($offer->id, (int) $order->offer_id);

        $offer->refresh();
        $this->assertSame(OfferStatus::Paid, $offer->status);
        $this->assertNotNull($offer->paid_at);

        $listing->refresh();
        $this->assertSame(AssetStatus::SoldOut, $listing->status);
        $this->assertSame(1, (int) $listing->sold_quantity);
    }

    public function test_paying_an_accepted_offer_reduces_a_multiple_listing_by_the_offered_quantity(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Multiple, 5000, 10);

        $conv  = app(ConversationService::class)->forListing($buyer, $listing);
        $offer = app(OfferService::class)->createInConversation($conv, $buyer, Money::toPoisha(4200), 3);
        app(OfferService::class)->accept($offer, $seller);

        $this->purchase($buyer, $listing, 3, $offer->fresh());

        $listing->refresh();
        $this->assertSame(7, (int) $listing->available_quantity);
        $this->assertSame(3, (int) $listing->sold_quantity);
        $this->assertSame(AssetStatus::Published, $listing->status);
    }

    public function test_paying_an_accepted_offer_leaves_an_unlimited_listing_untouched(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Unlimited, 5000);

        $conv  = app(ConversationService::class)->forListing($buyer, $listing);
        $offer = app(OfferService::class)->createInConversation($conv, $buyer, Money::toPoisha(4200), 5);
        app(OfferService::class)->accept($offer, $seller);

        $this->purchase($buyer, $listing, 5, $offer->fresh());

        $listing->refresh();
        $this->assertSame(AssetStatus::Published, $listing->status);
        $this->assertSame(1, (int) $listing->available_quantity);
        $this->assertSame(5, (int) $listing->sold_quantity);

        // Still open for business: another buyer can negotiate their own offer.
        $otherBuyer = $this->buyer();
        $otherConv  = app(ConversationService::class)->forListing($otherBuyer, $listing);
        $otherOffer = app(OfferService::class)->createInConversation($otherConv, $otherBuyer, Money::toPoisha(4000));
        $this->assertSame(OfferStatus::Pending, $otherOffer->status);
    }

    /** The order joins the thread they were already talking in. */
    public function test_paying_reuses_the_conversation_contact_seller_opened(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);

        $conv = app(ConversationService::class)->forListing($buyer, $listing);
        $this->assertNull($conv->order_id);

        $order = $this->purchase($buyer, $listing);

        $this->assertSame(1, Conversation::count());
        $this->assertSame($order->id, (int) $conv->fresh()->order_id);
    }

    // ── Slugs ────────────────────────────────────────────────────────

    /** Str::slug(title) + "-" + 8 lowercase URL-safe characters, always. */
    public function test_a_new_listing_gets_a_slug_with_an_eight_character_suffix(): void
    {
        $seller = $this->seller();
        $asset  = $this->createListing($seller, '5k Subs YouTube Sell');

        $this->assertMatchesRegularExpression('/^5k-subs-youtube-sell-[a-z0-9]{8}$/', $asset->slug);
        $this->assertNotSame('5k-subs-youtube-sell', $asset->slug);
    }

    public function test_two_listings_with_the_same_title_get_different_slugs(): void
    {
        $seller = $this->seller();

        $first  = $this->createListing($seller, '5k Subs YouTube Sell');
        $second = $this->createListing($seller, '5k Subs YouTube Sell');

        $this->assertNotSame($first->slug, $second->slug);
        foreach ([$first, $second] as $asset) {
            $this->assertMatchesRegularExpression('/^5k-subs-youtube-sell-[a-z0-9]{8}$/', $asset->slug);
        }
    }

    public function test_a_title_with_no_sluggable_characters_still_produces_a_suffixed_slug(): void
    {
        $asset = $this->createListing($this->seller(), '!!! ... ???');

        $this->assertMatchesRegularExpression('/^listing-[a-z0-9]{8}$/', $asset->slug);
    }

    /** The suffix is server-generated, so a slug in the payload is ignored. */
    public function test_a_slug_supplied_by_the_browser_is_never_used(): void
    {
        $seller   = $this->seller();
        $category = $this->category();

        $this->actingAs($seller)
            ->post('/dashboard/listings', [
                'category_id'    => $category->id,
                'title'          => '5k Subs YouTube Sell',
                'description'    => str_repeat('A genuine channel with real subscribers. ', 3),
                'price_bdt'      => 5000,
                'inventory_type' => 'single',
                'policy_accept'  => true,
                'slug'           => 'admin-owned-slug',
            ])
            ->assertRedirect(route('dashboard.listings'));

        $asset = Asset::latest('id')->firstOrFail();
        $this->assertNotSame('admin-owned-slug', $asset->slug);
        $this->assertMatchesRegularExpression('/^5k-subs-youtube-sell-[a-z0-9]{8}$/', $asset->slug);
    }

    /** Public URLs stay stable: retitling a live listing keeps its slug. */
    public function test_editing_the_title_keeps_the_original_slug(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $before  = $listing->slug;

        $this->actingAs($seller);

        $listings = app(ListingService::class);
        $edit     = $listings->submitEdit($listing, [
            'title'       => 'A Completely Different Title Now',
            'description' => str_repeat('Updated description with plenty of detail. ', 3),
            'price_bdt'   => Money::toBdt((int) $listing->price),
            'quantity'    => 1,
        ], []);

        $listings->applyEdit($edit, User::factory()->admin()->create());

        $listing->refresh();
        $this->assertSame('A Completely Different Title Now', $listing->title);
        $this->assertSame($before, $listing->slug);
    }

    public function test_the_slug_column_stays_unique_across_many_listings(): void
    {
        $seller = $this->seller();
        $slugs  = [];

        for ($i = 0; $i < 15; $i++) {
            $slugs[] = $this->createListing($seller, 'Identical Listing Title')->slug;
        }

        $this->assertCount(15, array_unique($slugs));
    }

    private function createListing(User $seller, string $title): Asset
    {
        return app(ListingService::class)->create($seller, [
            'category_id'    => $this->category()->id,
            'title'          => $title,
            'description'    => str_repeat('A long enough description for the validator. ', 2),
            'price_bdt'      => 5000,
            'inventory_type' => 'single',
        ], []);
    }

    // ── Availability after a refund ───────────────────────────────────

    /** A staff user who can decide disputes. */
    private function staff(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(\App\Models\Role::where('name', 'admin')->value('id'));

        return $user->fresh();
    }

    /**
     * The real admin refund path: the buyer opens a dispute, staff decide a full
     * refund. That is the only place an order becomes Refunded.
     */
    private function refundByAdmin(Order $order): void
    {
        $dispute = app(DisputeService::class)->open(
            $order->fresh(),
            $order->buyer,
            \App\Enums\DisputeReason::NotWorking,
            'The asset did not work after handover, requesting a refund.',
        );

        app(DisputeService::class)->resolveFullRefund($dispute, $this->staff(), 'Buyer evidence stands.');
    }

    /**
     * Test 1 — the reported bug. Stock is taken at payment and was never given
     * back, so a fully refunded listing stayed sold_out forever even though
     * nothing was holding it.
     */
    public function test_refunding_the_only_sale_puts_the_listing_back_on_the_market(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);

        $order = $this->purchase($buyer, $listing);

        // Sold: the single unit is held by a live sale.
        $sold = $listing->fresh();
        $this->assertSame(AssetStatus::SoldOut, $sold->status);
        $this->assertSame(0, (int) $sold->available_quantity);
        $this->assertSame(1, (int) $sold->sold_quantity);
        $this->assertTrue($sold->isSoldOut());

        $this->refundByAdmin($order);

        // Refunded and nothing else holds it, so it is available again.
        $free = $listing->fresh();
        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
        $this->assertSame(AssetStatus::Published, $free->status);
        $this->assertSame(1, (int) $free->available_quantity);
        $this->assertSame(0, (int) $free->sold_quantity);
        $this->assertFalse($free->isSoldOut());
        $this->assertTrue($free->isAvailableForPurchase());
    }

    /**
     * Test 2 — the rule that stops this being "every refund frees the listing".
     * A refunded order sitting alongside a live one must not free anything.
     */
    public function test_a_refund_does_not_free_a_listing_another_sale_still_holds(): void
    {
        $seller  = $this->seller();
        $first   = $this->buyer();
        $second  = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);

        // A buys, is refunded, so the listing returns to the market…
        $orderA = $this->purchase($first, $listing);
        $this->refundByAdmin($orderA);
        $this->assertSame(AssetStatus::Published, $listing->fresh()->status);

        // …then B buys it. Order A = Refunded, Order B = live.
        $orderB = $this->purchase($second, $listing);

        $held = $listing->fresh();
        $this->assertSame(OrderStatus::Refunded, $orderA->fresh()->status);
        $this->assertTrue($orderB->fresh()->status->countsAsSale());
        $this->assertSame(AssetStatus::SoldOut, $held->status);
        $this->assertSame(0, (int) $held->available_quantity);
        // Only B counts, so the sold counter is 1 and not 2.
        $this->assertSame(1, (int) $held->sold_quantity);
        $this->assertTrue($held->isSoldOut());
    }

    /** Multi-unit stock frees exactly the refunded unit, not the whole listing. */
    public function test_refunding_one_unit_of_a_multi_unit_listing_frees_only_that_unit(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Multiple, 5000, quantity: 2);

        $orderA = $this->purchase($this->buyer(), $listing);
        $orderB = $this->purchase($this->buyer(), $listing);

        $soldOut = $listing->fresh();
        $this->assertSame(AssetStatus::SoldOut, $soldOut->status);
        $this->assertSame(0, (int) $soldOut->available_quantity);

        $this->refundByAdmin($orderA);

        $partial = $listing->fresh();
        $this->assertSame(AssetStatus::Published, $partial->status);
        $this->assertSame(1, (int) $partial->available_quantity);   // A's unit back
        $this->assertSame(1, (int) $partial->sold_quantity);        // B still holds one
        $this->assertFalse($partial->isSoldOut());
    }

    /**
     * Test 3 — the ordinary path is untouched: a live sale, and a completed one,
     * both keep the listing sold.
     */
    public function test_a_normal_sale_still_marks_the_listing_sold(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);

        $order = $this->purchase($buyer, $listing);
        $this->assertSame(AssetStatus::SoldOut, $listing->fresh()->status);

        // Deliver and complete — the sale is finished, not undone.
        app(OrderService::class)->deliver($order->fresh(), $seller, 'Credentials sent.');
        app(OrderService::class)->complete($order->fresh(), $buyer);

        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
        $done = $listing->fresh();
        $this->assertSame(AssetStatus::SoldOut, $done->status);
        $this->assertSame(0, (int) $done->available_quantity);
        $this->assertSame(1, (int) $done->sold_quantity);
        $this->assertFalse($done->isAvailableForPurchase());
    }

    /** An unlimited listing is never sold out, so a refund cannot change that. */
    public function test_an_unlimited_listing_is_unaffected_by_a_refund(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Unlimited, 5000);

        $order = $this->purchase($this->buyer(), $listing);
        $this->assertSame(AssetStatus::Published, $listing->fresh()->status);
        $this->assertFalse($listing->fresh()->isSoldOut());

        $this->refundByAdmin($order);

        $after = $listing->fresh();
        $this->assertSame(AssetStatus::Published, $after->status);
        $this->assertFalse($after->isSoldOut());
        // The sold counter drops, since that sale was undone.
        $this->assertSame(0, (int) $after->sold_quantity);
    }

    /** A paused listing must not be published by a refund. */
    public function test_a_refund_does_not_republish_a_listing_the_seller_paused(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Multiple, 5000, quantity: 2);

        $order = $this->purchase($this->buyer(), $listing);
        $listing->fresh()->update(['status' => AssetStatus::Paused]);

        $this->refundByAdmin($order);

        $after = $listing->fresh();
        $this->assertSame(AssetStatus::Paused, $after->status);   // still the seller's call
        $this->assertSame(2, (int) $after->available_quantity);   // stock still restored
    }
}
