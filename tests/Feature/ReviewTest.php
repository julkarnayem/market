<?php
namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/**
 * The buyer's review of a purchase.
 *
 * An order carries exactly one asset — there is no order-items table — so one
 * review per order is one review per purchased product, and the pre-existing
 * unique index on reviews.order_id is what enforces "once". These tests pin the
 * eligibility window in particular: the review action used to be rendered only
 * while the order was Delivered, which is precisely the window the server refused,
 * and it disappeared the moment the buyer completed the order, which is the only
 * window the server allowed.
 */
class ReviewTest extends TestCase
{
    use BuildsMarketplace;

    private const BUYER_TOTAL = 500000;     // ৳5,000.00
    private const SELLER_EARNING = 450000;

    /**
     * An order at the given status. Order::factory() does not exist — the model
     * imports HasFactory without applying it (see .github/known-test-failures.txt)
     * — so the row is built with create().
     */
    private function orderAt(OrderStatus $status, int $quantity = 1): Order
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, priceBdt: 5000, quantity: max(1, $quantity));

        return Order::create([
            'reference'           => 'REF-'.strtoupper(Str::random(12)),
            'order_number'        => 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
            'buyer_user_id'       => $buyer->id,
            'seller_user_id'      => $seller->id,
            'asset_id'            => $listing->id,
            'quantity'            => $quantity,
            'unit_price'          => self::BUYER_TOTAL,
            'subtotal'            => self::BUYER_TOTAL,
            'seller_fee_bp'       => 1000,
            'seller_fee_amount'   => self::BUYER_TOTAL - self::SELLER_EARNING,
            'buyer_fee_enabled'   => false,
            'buyer_fee_bp'        => 0,
            'buyer_fee_amount'    => 0,
            'platform_commission' => self::BUYER_TOTAL - self::SELLER_EARNING,
            'buyer_total'         => self::BUYER_TOTAL,
            'seller_earning'      => self::SELLER_EARNING,
            'currency'            => 'BDT',
            'status'              => $status,
            'payment_status'      => 'paid',
            'delivery_status'     => match (true) {
                $status === OrderStatus::Delivered => 'delivered',
                $status === OrderStatus::Completed => 'confirmed',
                default                            => 'not_started',
            },
            'payment_gateway'     => 'uddoktapay',
            'paid_at'             => now(),
            'delivered_at'        => $status === OrderStatus::Delivered ? now() : null,
            'earning_released'    => false,
        ]);
    }

    private function reviewPayload(array $overrides = []): array
    {
        return array_merge(['rating' => 5, 'comment' => 'Exactly as described, fast handover.'], $overrides);
    }

    // ── The eligibility window ───────────────────────────────────────

    /**
     * The reported bug. A Delivered order is the whole buyer-protection window,
     * and it is when a buyer actually wants to write the review — the old gate
     * demanded delivery_status be 'confirmed' and returned 403 for all of it.
     */
    public function test_buyer_can_review_a_delivered_order(): void
    {
        $order = $this->orderAt(OrderStatus::Delivered);

        $this->actingAs($order->buyer)
            ->get("/dashboard/orders/{$order->id}/review")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Orders/Review')
                ->where('order.id', $order->id)
                ->etc()
            );

        $this->actingAs($order->buyer)
            ->post("/dashboard/orders/{$order->id}/review", $this->reviewPayload())
            ->assertRedirect(route('dashboard.orders.show', $order))
            ->assertSessionHas('success');

        $review = Review::firstOrFail();
        $this->assertSame($order->id, (int) $review->order_id);
        $this->assertSame(5, $review->rating);
        // Derived from the order, never from the request.
        $this->assertSame($order->buyer_user_id, (int) $review->reviewer_id);
        $this->assertSame($order->seller_user_id, (int) $review->seller_id);
        $this->assertSame($order->asset_id, (int) $review->asset_id);
    }

    /**
     * The other half of the deadlock: once the buyer completed the order the whole
     * card was hidden, so the only state the server accepted was unreachable.
     */
    public function test_buyer_can_still_review_after_completing_the_order(): void
    {
        $order = $this->orderAt(OrderStatus::Completed);

        $this->actingAs($order->buyer)
            ->post("/dashboard/orders/{$order->id}/review", $this->reviewPayload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, Review::count());
    }

    public function test_buyer_cannot_review_before_delivery(): void
    {
        foreach ([OrderStatus::PendingPayment, OrderStatus::Paid, OrderStatus::DeliveryPending] as $status) {
            $order = $this->orderAt($status);

            $this->actingAs($order->buyer)
                ->get("/dashboard/orders/{$order->id}/review")
                ->assertForbidden();

            $this->actingAs($order->buyer)
                ->post("/dashboard/orders/{$order->id}/review", $this->reviewPayload())
                ->assertForbidden();
        }

        $this->assertSame(0, Review::count());
    }

    /** An unwound sale is not a purchase worth rating; a live claim is not finished. */
    public function test_an_unwound_or_disputed_order_cannot_be_reviewed(): void
    {
        foreach ([OrderStatus::Refunded, OrderStatus::PartiallyRefunded,
                  OrderStatus::Cancelled, OrderStatus::Disputed] as $status) {
            $order = $this->orderAt($status);

            $this->actingAs($order->buyer)
                ->post("/dashboard/orders/{$order->id}/review", $this->reviewPayload())
                ->assertForbidden();
        }

        $this->assertSame(0, Review::count());
    }

    // ── Ownership ────────────────────────────────────────────────────

    public function test_a_stranger_cannot_review_someone_elses_order(): void
    {
        $order    = $this->orderAt(OrderStatus::Delivered);
        $stranger = $this->buyer();

        $this->actingAs($stranger)
            ->get("/dashboard/orders/{$order->id}/review")
            ->assertForbidden();

        // Changing the order id in the request buys nothing: ownership is read
        // off the order the route resolved.
        $this->actingAs($stranger)
            ->post("/dashboard/orders/{$order->id}/review", $this->reviewPayload())
            ->assertForbidden();

        $this->assertSame(0, Review::count());
    }

    public function test_the_seller_cannot_review_their_own_sale(): void
    {
        $order = $this->orderAt(OrderStatus::Delivered);

        $this->actingAs($order->seller)
            ->post("/dashboard/orders/{$order->id}/review", $this->reviewPayload())
            ->assertForbidden();

        $this->assertSame(0, Review::count());
    }

    public function test_a_buyer_of_one_order_cannot_review_another(): void
    {
        $mine   = $this->orderAt(OrderStatus::Delivered);
        $theirs = $this->orderAt(OrderStatus::Delivered);

        $this->actingAs($mine->buyer)
            ->post("/dashboard/orders/{$theirs->id}/review", $this->reviewPayload())
            ->assertForbidden();

        $this->assertSame(0, Review::count());
    }

    /**
     * The asset is never taken from the payload, so naming another listing cannot
     * attach the review to a product the buyer did not purchase.
     */
    public function test_a_forged_payload_cannot_redirect_the_review(): void
    {
        $order       = $this->orderAt(OrderStatus::Delivered);
        $otherSeller = $this->seller();
        $otherAsset  = $this->listing($otherSeller, priceBdt: 999);
        $impostor    = $this->buyer();

        $this->actingAs($order->buyer)
            ->post("/dashboard/orders/{$order->id}/review", $this->reviewPayload([
                'asset_id'    => $otherAsset->id,
                'seller_id'   => $otherSeller->id,
                'reviewer_id' => $impostor->id,
                'order_id'    => 99999,
            ]))
            ->assertRedirect();

        $review = Review::firstOrFail();
        $this->assertSame($order->asset_id, (int) $review->asset_id);
        $this->assertSame($order->seller_user_id, (int) $review->seller_id);
        $this->assertSame($order->buyer_user_id, (int) $review->reviewer_id);
        $this->assertSame($order->id, (int) $review->order_id);
    }

    // ── Once only ────────────────────────────────────────────────────

    public function test_a_second_review_is_rejected(): void
    {
        $order = $this->orderAt(OrderStatus::Delivered);

        $this->actingAs($order->buyer)
            ->post("/dashboard/orders/{$order->id}/review", $this->reviewPayload())
            ->assertRedirect();

        $this->actingAs($order->buyer)
            ->post("/dashboard/orders/{$order->id}/review", $this->reviewPayload(['rating' => 1]))
            ->assertForbidden();

        // The form is closed too, not just the submit.
        $this->actingAs($order->buyer)
            ->get("/dashboard/orders/{$order->id}/review")
            ->assertForbidden();

        $this->assertSame(1, Review::count());
        $this->assertSame(5, Review::first()->rating);
    }

    /** The database is the backstop if the application check is ever bypassed. */
    public function test_the_schema_refuses_a_second_review_for_one_order(): void
    {
        $order = $this->orderAt(OrderStatus::Delivered);

        Review::create([
            'order_id' => $order->id, 'reviewer_id' => $order->buyer_user_id,
            'seller_id' => $order->seller_user_id, 'asset_id' => $order->asset_id,
            'rating' => 4, 'comment' => null,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Review::create([
            'order_id' => $order->id, 'reviewer_id' => $order->buyer_user_id,
            'seller_id' => $order->seller_user_id, 'asset_id' => $order->asset_id,
            'rating' => 1, 'comment' => 'second',
        ]);
    }

    /**
     * Buying five of something is still one purchase to rate. Nothing in the
     * schema or the UI multiplies the review action by quantity.
     */
    public function test_quantity_greater_than_one_still_allows_only_one_review(): void
    {
        $order = $this->orderAt(OrderStatus::Delivered, quantity: 5);

        $this->actingAs($order->buyer)
            ->post("/dashboard/orders/{$order->id}/review", $this->reviewPayload())
            ->assertRedirect();

        $this->actingAs($order->buyer)
            ->post("/dashboard/orders/{$order->id}/review", $this->reviewPayload())
            ->assertForbidden();

        $this->assertSame(1, Review::count());
        $this->assertSame(5, (int) $order->fresh()->quantity);
    }

    // ── Validation ───────────────────────────────────────────────────

    public function test_the_rating_must_be_an_integer_from_one_to_five(): void
    {
        $order = $this->orderAt(OrderStatus::Delivered);

        foreach ([0, 6, -1, 99, 'five', 3.5, null] as $bad) {
            $this->actingAs($order->buyer)
                ->post("/dashboard/orders/{$order->id}/review", ['rating' => $bad, 'comment' => 'x'])
                ->assertSessionHasErrors('rating');
        }

        $this->assertSame(0, Review::count());

        // Every value in range is accepted.
        foreach ([1, 2, 3, 4, 5] as $good) {
            $fresh = $this->orderAt(OrderStatus::Delivered);
            $this->actingAs($fresh->buyer)
                ->post("/dashboard/orders/{$fresh->id}/review", ['rating' => $good])
                ->assertRedirect()
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(5, Review::count());
    }

    /** The existing schema and form both treat the comment as optional. */
    public function test_the_comment_is_optional_but_bounded(): void
    {
        $order = $this->orderAt(OrderStatus::Delivered);

        $this->actingAs($order->buyer)
            ->post("/dashboard/orders/{$order->id}/review", ['rating' => 4])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull(Review::firstOrFail()->comment);

        $long = $this->orderAt(OrderStatus::Delivered);
        $this->actingAs($long->buyer)
            ->post("/dashboard/orders/{$long->id}/review", ['rating' => 4, 'comment' => str_repeat('a', 1001)])
            ->assertSessionHasErrors('comment');
    }

    // ── What the order pages advertise ───────────────────────────────

    public function test_the_order_page_offers_the_review_action_only_when_eligible(): void
    {
        $pending = $this->orderAt(OrderStatus::DeliveryPending);
        $this->actingAs($pending->buyer)
            ->get("/dashboard/orders/{$pending->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.can_be_reviewed', false)
                ->where('alreadyReviewed', false)
                ->where('myReview', null)
                ->etc()
            );

        foreach ([OrderStatus::Delivered, OrderStatus::Completed] as $status) {
            $order = $this->orderAt($status);
            $this->actingAs($order->buyer)
                ->get("/dashboard/orders/{$order->id}")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('order.can_be_reviewed', true)
                    ->where('myReview', null)
                    ->etc()
                );
        }
    }

    public function test_a_reviewed_order_reports_its_rating_instead_of_the_action(): void
    {
        $order = $this->orderAt(OrderStatus::Delivered);

        $this->actingAs($order->buyer)
            ->post("/dashboard/orders/{$order->id}/review", $this->reviewPayload(['rating' => 4]));

        $this->actingAs($order->buyer)
            ->get("/dashboard/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.can_be_reviewed', true)
                ->where('alreadyReviewed', true)
                ->where('myReview.rating', 4)
                ->etc()
            );
    }

    public function test_the_orders_list_reports_review_state_per_order(): void
    {
        $reviewed   = $this->orderAt(OrderStatus::Delivered);
        $unreviewed = $this->orderAt(OrderStatus::Delivered);
        $tooEarly   = $this->orderAt(OrderStatus::DeliveryPending);

        // Put all three under one buyer so a single list shows the mix.
        $buyer = $reviewed->buyer;
        $unreviewed->update(['buyer_user_id' => $buyer->id]);
        $tooEarly->update(['buyer_user_id' => $buyer->id]);

        $this->actingAs($buyer)
            ->post("/dashboard/orders/{$reviewed->id}/review", $this->reviewPayload(['rating' => 3]));

        $rows = $this->actingAs($buyer)
            ->get('/dashboard/orders?role=buyer')
            ->assertOk()
            ->viewData('page')['props']['orders']['data'];

        $byId = collect($rows)->keyBy('id');
        $this->assertTrue($byId[$reviewed->id]['can_be_reviewed']);
        $this->assertSame(3, $byId[$reviewed->id]['review_rating']);
        $this->assertTrue($byId[$unreviewed->id]['can_be_reviewed']);
        $this->assertNull($byId[$unreviewed->id]['review_rating']);
        $this->assertFalse($byId[$tooEarly->id]['can_be_reviewed']);
    }

    /** The seller's own list never offers to review their sale. */
    public function test_the_seller_list_never_offers_the_review_action(): void
    {
        $order = $this->orderAt(OrderStatus::Delivered);

        $rows = $this->actingAs($order->seller)
            ->get('/dashboard/orders?role=seller')
            ->assertOk()
            ->viewData('page')['props']['orders']['data'];

        $this->assertFalse($rows[0]['can_be_reviewed']);
    }

    // ── Where reviews surface ────────────────────────────────────────

    public function test_the_listing_page_shows_the_rating_aggregate(): void
    {
        $first  = $this->orderAt(OrderStatus::Delivered);
        $asset  = $first->asset;
        $seller = $first->seller;

        // A second buyer of the same listing, so the average is not just one row.
        $second = Order::create(array_merge($first->only([
            'seller_user_id', 'asset_id', 'quantity', 'unit_price', 'subtotal',
            'seller_fee_bp', 'seller_fee_amount', 'buyer_fee_enabled', 'buyer_fee_bp',
            'buyer_fee_amount', 'platform_commission', 'buyer_total', 'seller_earning',
            'currency', 'payment_status', 'delivery_status', 'payment_gateway',
        ]), [
            'reference'     => 'REF-'.strtoupper(Str::random(12)),
            'order_number'  => 'ORD-2-'.strtoupper(Str::random(6)),
            'buyer_user_id' => $this->buyer()->id,
            'status'        => OrderStatus::Delivered,
            'paid_at'       => now(),
            'delivered_at'  => now(),
        ]));

        $this->actingAs($first->buyer)
            ->post("/dashboard/orders/{$first->id}/review", $this->reviewPayload(['rating' => 5, 'comment' => 'Great.']));
        $this->actingAs($second->buyer)
            ->post("/dashboard/orders/{$second->id}/review", $this->reviewPayload(['rating' => 4, 'comment' => 'Good.']));

        $this->get("/asset/{$asset->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('reviews.count', 2)
                ->where('reviews.average', 4.5)
                ->has('reviews.items', 2)
                ->etc()
            );

        $this->assertSame(2, $seller->reviewsReceived()->count());
    }

    public function test_a_listing_with_no_reviews_reports_empty_aggregates(): void
    {
        $asset = $this->listing($this->seller());

        $this->get("/asset/{$asset->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('reviews.count', 0)
                ->where('reviews.average', null)
                ->has('reviews.items', 0)
                ->etc()
            );
    }

    /** The seller's public profile already lists their reviews; still does. */
    public function test_the_sellers_public_profile_lists_the_review(): void
    {
        $order = $this->orderAt(OrderStatus::Delivered);

        $this->actingAs($order->buyer)
            ->post("/dashboard/orders/{$order->id}/review", $this->reviewPayload(['rating' => 5, 'comment' => 'Smooth.']));

        $seller = $order->seller;

        $this->get("/users/{$seller->username}?tab=reviews")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.reviews', 1)
                ->has('reviews.data', 1)
                ->has('reviews.data.0', fn (Assert $r) => $r
                    ->where('rating', 5)
                    ->where('comment', 'Smooth.')
                    ->where('reviewer_name', $order->buyer->name)
                    ->etc()
                )
                ->etc()
            );
    }

    // ── Regression guard ─────────────────────────────────────────────

    /** Reviewing must not touch the order itself. */
    public function test_reviewing_does_not_alter_the_order(): void
    {
        $order  = $this->orderAt(OrderStatus::Delivered);
        $before = $order->only(['status', 'payment_status', 'delivery_status', 'buyer_total',
                                'seller_earning', 'earning_released', 'dispute_status']);

        $this->actingAs($order->buyer)
            ->post("/dashboard/orders/{$order->id}/review", $this->reviewPayload());

        $after = $order->fresh()->only(['status', 'payment_status', 'delivery_status', 'buyer_total',
                                        'seller_earning', 'earning_released', 'dispute_status']);

        $this->assertEquals($before, $after);
    }
}
