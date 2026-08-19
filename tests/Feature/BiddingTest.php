<?php
namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\BidStatus;
use App\Enums\InventoryType;
use App\Models\Asset;
use App\Models\Bid;
use App\Models\User;
use App\Services\BidService;
use App\Services\OrderService;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/**
 * Public bidding.
 *
 * The rules under test are deliberately narrow: bids exist only on single-item
 * listings, and the only floor a bid has to clear is the current top bid. There
 * is no minimum increment, and nothing stops a bidder from raising their own
 * bid — so a few of these tests exist to prove restrictions are *absent*.
 */
class BiddingTest extends TestCase
{
    use BuildsMarketplace;

    // ── Inventory type gate ──────────────────────────────────────────

    public function test_buyer_can_place_a_bid_on_a_single_listing(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);

        $this->actingAs($buyer)
            ->post("/listings/{$listing->slug}/bids", ['amount_bdt' => 4500])
            ->assertSessionHas('success');

        $bid = Bid::first();
        $this->assertNotNull($bid);
        $this->assertSame(450000, (int) $bid->amount);           // 4,500 BDT in poisha
        $this->assertSame(BidStatus::Active, $bid->status);
        $this->assertSame($buyer->id, (int) $bid->bidder_user_id);
        $this->assertSame($seller->id, (int) $bid->seller_user_id);
    }

    public function test_server_rejects_a_bid_on_a_multiple_quantity_listing(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Multiple, 5000, 100);

        $this->actingAs($buyer)
            ->post("/listings/{$listing->slug}/bids", ['amount_bdt' => 4500])
            ->assertForbidden();

        $this->assertSame(0, Bid::count());
    }

    public function test_server_rejects_a_bid_on_an_unlimited_listing(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Unlimited, 5000);

        $this->actingAs($buyer)
            ->post("/listings/{$listing->slug}/bids", ['amount_bdt' => 4500])
            ->assertForbidden();

        $this->assertSame(0, Bid::count());
    }

    /** The service is the last line, so it must refuse even if the policy is bypassed. */
    public function test_bid_service_itself_refuses_non_single_listings(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Multiple, 5000, 50);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Bidding is only available on single-item listings.');

        app(BidService::class)->place($buyer, $listing, Money::toPoisha(4500));
    }

    // ── The only minimum: beat the top bid ───────────────────────────

    public function test_a_bid_equal_to_the_top_bid_is_rejected(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $first   = $this->buyer();
        $second  = $this->buyer();

        $this->actingAs($first)->post("/listings/{$listing->slug}/bids", ['amount_bdt' => 4500]);

        $this->actingAs($second)
            ->post("/listings/{$listing->slug}/bids", ['amount_bdt' => 4500])
            ->assertStatus(422);

        $this->assertSame(1, Bid::count());
    }

    /** ৳4,501 against a ৳4,500 top bid is valid — there is no fixed increment. */
    public function test_one_taka_over_the_top_bid_is_accepted(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $first   = $this->buyer();
        $second  = $this->buyer();

        $this->actingAs($first)->post("/listings/{$listing->slug}/bids", ['amount_bdt' => 4500]);

        $this->actingAs($second)
            ->post("/listings/{$listing->slug}/bids", ['amount_bdt' => 4501])
            ->assertSessionHas('success');

        $this->assertSame(450100, (int) $listing->fresh()->topBidAmount());
    }

    /** Even a single poisha over is enough. */
    public function test_one_poisha_over_the_top_bid_is_accepted(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $bidder  = $this->buyer();

        app(BidService::class)->place($bidder, $listing, 450000);
        app(BidService::class)->place($this->buyer(), $listing->fresh(), 450001);

        $this->assertSame(450001, (int) $listing->fresh()->topBidAmount());
    }

    /** No "you already hold the top bid" rule: a user may raise their own bid. */
    public function test_the_same_user_may_bid_again_against_their_own_bid(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $bidder  = $this->buyer();

        $this->actingAs($bidder)->post("/listings/{$listing->slug}/bids", ['amount_bdt' => 4500]);
        $this->actingAs($bidder)
            ->post("/listings/{$listing->slug}/bids", ['amount_bdt' => 4600])
            ->assertSessionHas('success');

        $this->assertSame(2, Bid::where('bidder_user_id', $bidder->id)->count());
        $this->assertSame(460000, (int) $listing->fresh()->topBidAmount());
    }

    public function test_bid_amount_must_be_positive(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);

        $this->actingAs($this->buyer())
            ->post("/listings/{$listing->slug}/bids", ['amount_bdt' => 0])
            ->assertSessionHasErrors('amount_bdt');

        $this->assertSame(0, Bid::count());
    }

    // ── Statuses ─────────────────────────────────────────────────────

    public function test_a_higher_bid_outbids_the_previous_top_bid(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $first   = $this->buyer();
        $second  = $this->buyer();

        $low  = app(BidService::class)->place($first, $listing, 450000);
        $high = app(BidService::class)->place($second, $listing->fresh(), 500000);

        $this->assertSame(BidStatus::Outbid, $low->fresh()->status);
        $this->assertNotNull($low->fresh()->outbid_at);
        $this->assertSame(BidStatus::Active, $high->fresh()->status);
    }

    /** "Current top bid" is always the single Active row, never a stale amount. */
    public function test_exactly_one_bid_is_active_at_a_time(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);

        foreach ([450000, 460000, 470000, 480000] as $amount) {
            app(BidService::class)->place($this->buyer(), $listing->fresh(), $amount);
        }

        $this->assertSame(1, $listing->bids()->where('status', BidStatus::Active->value)->count());
        $this->assertSame(480000, (int) $listing->fresh()->topBidAmount());
    }

    public function test_withdrawing_the_top_bid_promotes_the_next_one(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $lower   = app(BidService::class)->place($this->buyer(), $listing, 450000);
        $topUser = $this->buyer();
        $top     = app(BidService::class)->place($topUser, $listing->fresh(), 500000);

        $this->actingAs($topUser)->post("/bids/{$top->id}/cancel")->assertSessionHas('success');

        $this->assertSame(BidStatus::Cancelled, $top->fresh()->status);
        $this->assertSame(BidStatus::Active, $lower->fresh()->status);
        $this->assertSame(450000, (int) $listing->fresh()->topBidAmount());
    }

    public function test_seller_rejecting_the_top_bid_promotes_the_next_one(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $lower   = app(BidService::class)->place($this->buyer(), $listing, 450000);
        $top     = app(BidService::class)->place($this->buyer(), $listing->fresh(), 500000);

        $this->actingAs($seller)->post("/bids/{$top->id}/reject")->assertSessionHas('success');

        $this->assertSame(BidStatus::Rejected, $top->fresh()->status);
        $this->assertSame(BidStatus::Active, $lower->fresh()->status);
    }

    public function test_a_bidder_cannot_withdraw_someone_elses_bid(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $bid     = app(BidService::class)->place($this->buyer(), $listing, 450000);

        $this->actingAs($this->buyer())->post("/bids/{$bid->id}/cancel")->assertForbidden();

        $this->assertSame(BidStatus::Active, $bid->fresh()->status);
    }

    // ── Who may act ──────────────────────────────────────────────────

    public function test_seller_cannot_bid_on_their_own_listing(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);

        $this->actingAs($seller)
            ->post("/listings/{$listing->slug}/bids", ['amount_bdt' => 4500])
            ->assertForbidden();

        $this->assertSame(0, Bid::count());
    }

    public function test_guest_cannot_bid(): void
    {
        $listing = $this->listing($this->seller(), InventoryType::Single, 5000);

        $this->post("/listings/{$listing->slug}/bids", ['amount_bdt' => 4500])
            ->assertRedirect('/login');

        $this->assertSame(0, Bid::count());
    }

    public function test_a_suspended_user_cannot_bid(): void
    {
        $seller    = $this->seller();
        $listing   = $this->listing($seller, InventoryType::Single, 5000);
        $suspended = User::factory()->suspended()->create();

        $this->assertFalse(Gate::forUser($suspended)->allows('create', [Bid::class, $listing]));

        $this->expectException(HttpException::class);
        app(BidService::class)->place($suspended, $listing, 450000);
    }

    public function test_only_the_seller_can_accept_a_bid(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $bidder  = $this->buyer();
        $bid     = app(BidService::class)->place($bidder, $listing, 450000);

        // The bidder cannot accept their own bid…
        $this->actingAs($bidder)->post("/bids/{$bid->id}/accept")->assertForbidden();
        // …and neither can an unrelated user.
        $this->actingAs($this->buyer())->post("/bids/{$bid->id}/accept")->assertForbidden();

        $this->assertSame(BidStatus::Active, $bid->fresh()->status);
        $this->assertNull($listing->fresh()->accepted_bid_id);
    }

    public function test_a_bid_cannot_be_placed_on_an_unpublished_listing(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $listing->update(['status' => AssetStatus::Paused]);

        $this->actingAs($this->buyer())
            ->post("/listings/{$listing->slug}/bids", ['amount_bdt' => 4500])
            ->assertForbidden();
    }

    /**
     * A crafted payload cannot re-point a bid: the controller validates only the
     * amount, and every id comes from the route's listing.
     */
    public function test_ids_cannot_be_forged_through_the_request(): void
    {
        $seller   = $this->seller();
        $listing  = $this->listing($seller, InventoryType::Single, 5000);
        $bidder   = $this->buyer();
        $victim   = $this->buyer();
        $other    = $this->listing($this->seller(), InventoryType::Single, 9000);

        $this->actingAs($bidder)->post("/listings/{$listing->slug}/bids", [
            'amount_bdt'     => 4500,
            'amount'         => 1,
            'bidder_user_id' => $victim->id,
            'seller_user_id' => $victim->id,
            'asset_id'       => $other->id,
            'status'         => BidStatus::Accepted->value,
        ])->assertSessionHas('success');

        $bid = Bid::firstOrFail();
        $this->assertSame($bidder->id, (int) $bid->bidder_user_id);
        $this->assertSame($seller->id, (int) $bid->seller_user_id);
        $this->assertSame($listing->id, (int) $bid->asset_id);
        $this->assertSame(450000, (int) $bid->amount);
        $this->assertSame(BidStatus::Active, $bid->status);
    }

    // ── Acceptance: "Bid Accepted" is not "Sold" ─────────────────────

    public function test_accepting_a_bid_holds_the_listing_without_selling_it(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $bid     = app(BidService::class)->place($this->buyer(), $listing, 450000);

        $this->actingAs($seller)->post("/bids/{$bid->id}/accept")->assertSessionHas('success');

        $listing->refresh();
        $this->assertSame(AssetStatus::BidAccepted, $listing->status);
        $this->assertSame('Bid Accepted', $listing->status->label());
        $this->assertSame($bid->id, (int) $listing->accepted_bid_id);
        $this->assertSame(BidStatus::Accepted, $bid->fresh()->status);

        // Not sold: stock untouched, nothing counted as sold, page still public.
        $this->assertNotSame(AssetStatus::SoldOut, $listing->status);
        $this->assertFalse($listing->isSoldOut());
        $this->assertSame(0, (int) $listing->sold_quantity);
        $this->assertSame(1, (int) $listing->available_quantity);
        $this->assertTrue($listing->status->isPubliclyVisible());
        $this->get("/asset/{$listing->slug}")->assertOk();
    }

    public function test_accepting_a_bid_rejects_the_other_bids(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $losing  = app(BidService::class)->place($this->buyer(), $listing, 450000);
        $winning = app(BidService::class)->place($this->buyer(), $listing->fresh(), 500000);

        app(BidService::class)->accept($winning, $seller);

        $this->assertSame(BidStatus::Accepted, $winning->fresh()->status);
        $this->assertSame(BidStatus::Rejected, $losing->fresh()->status);
    }

    public function test_a_second_bid_cannot_be_accepted_on_the_same_listing(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $lower   = app(BidService::class)->place($this->buyer(), $listing, 450000);
        $top     = app(BidService::class)->place($this->buyer(), $listing->fresh(), 500000);

        app(BidService::class)->accept($top, $seller);

        // The loser was rejected, so the policy blocks it before the service does.
        $this->actingAs($seller)->post("/bids/{$lower->id}/accept")->assertForbidden();
        $this->assertSame($top->id, (int) $listing->fresh()->accepted_bid_id);
    }

    public function test_accepting_the_same_bid_twice_is_refused(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $bid     = app(BidService::class)->place($this->buyer(), $listing, 450000);

        app(BidService::class)->accept($bid, $seller);

        $this->expectException(HttpException::class);
        app(BidService::class)->accept($bid->fresh(), $seller);
    }

    public function test_no_new_bid_is_accepted_after_a_bid_has_been_accepted(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $bid     = app(BidService::class)->place($this->buyer(), $listing, 450000);
        app(BidService::class)->accept($bid, $seller);

        $this->actingAs($this->buyer())
            ->post("/listings/{$listing->slug}/bids", ['amount_bdt' => 9999])
            ->assertForbidden();

        $this->assertSame(1, Bid::count());
        $this->assertFalse($listing->fresh()->allowsBidding());
    }

    public function test_buy_now_is_closed_once_a_bid_is_accepted(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $bid     = app(BidService::class)->place($this->buyer(), $listing, 450000);
        app(BidService::class)->accept($bid, $seller);

        $listing->refresh();
        $this->assertFalse($listing->isAvailableForPurchase());

        // A different buyer cannot check out the held listing.
        $this->actingAs($this->buyer())
            ->get("/checkout/{$listing->slug}")
            ->assertRedirect();
    }

    // ── Payment belongs to the winner, at the bid amount ─────────────

    public function test_only_the_winning_bidder_can_pay_for_an_accepted_bid(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $winner  = $this->buyer();
        $bid     = app(BidService::class)->place($winner, $listing, 450000);
        app(BidService::class)->accept($bid, $seller);

        $this->actingAs($winner)
            ->get("/checkout/{$listing->slug}?bid={$bid->id}")
            ->assertOk();

        // Someone else quoting the same bid id gets nothing.
        $this->actingAs($this->buyer())
            ->get("/checkout/{$listing->slug}?bid={$bid->id}")
            ->assertNotFound();
    }

    public function test_the_winner_pays_the_bid_amount_not_the_asking_price(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);   // asking ৳5,000
        $winner  = $this->buyer();
        $bid     = app(BidService::class)->place($winner, $listing, 450000); // bid ৳4,500
        app(BidService::class)->accept($bid, $seller);

        $fees = app(OrderService::class)
            ->validateAndCalculate($listing->fresh(), 1, $winner, null, $bid->fresh());

        $this->assertSame(450000, (int) $fees['subtotal']);
        $this->assertNotSame((int) $listing->price, (int) $fees['subtotal']);
    }

    public function test_a_bid_cannot_be_used_to_buy_a_different_listing(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $cheap   = $this->listing($seller, InventoryType::Single, 100000);
        $winner  = $this->buyer();
        $bid     = app(BidService::class)->place($winner, $listing, 450000);
        app(BidService::class)->accept($bid, $seller);

        $this->actingAs($winner)
            ->get("/checkout/{$cheap->slug}?bid={$bid->id}")
            ->assertNotFound();
    }

    // ── What the listing page is told ────────────────────────────────

    public function test_listing_page_exposes_bid_state_for_a_single_listing(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $bidder  = $this->buyer();
        app(BidService::class)->place($bidder, $listing, 450000);

        $this->actingAs($this->buyer())
            ->get("/asset/{$listing->slug}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Marketplace/Show')
                ->where('asset.inventory_type', 'single')
                ->where('asset.allows_bidding', true)
                ->where('asset.top_bid_formatted', Money::format(450000))
                ->where('asset.has_accepted_bid', false)
                ->where('canBid', true)
                ->has('bids', 1)
                ->where('acceptedBid', null));
    }

    public function test_listing_page_never_offers_bidding_on_multiple_or_unlimited(): void
    {
        $seller = $this->seller();

        foreach ([InventoryType::Multiple, InventoryType::Unlimited] as $type) {
            $listing = $this->listing($seller, $type, 5000, 100);

            $this->actingAs($this->buyer())
                ->get("/asset/{$listing->slug}")
                ->assertInertia(fn (Assert $page) => $page
                    ->where('asset.inventory_type', $type->value)
                    ->where('asset.allows_bidding', false)
                    ->where('asset.top_bid_formatted', null)
                    ->where('canBid', false)
                    ->where('canContact', true)
                    ->has('bids', 0));
        }
    }

    public function test_listing_page_shows_the_accepted_bid_to_the_winner_only(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $winner  = $this->buyer();
        $bid     = app(BidService::class)->place($winner, $listing, 450000);
        app(BidService::class)->accept($bid, $seller);

        $this->actingAs($winner)
            ->get("/asset/{$listing->slug}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('asset.status', 'bid_accepted')
                ->where('asset.status_label', 'Bid Accepted')
                ->where('asset.has_accepted_bid', true)
                ->where('asset.is_sold_out', false)
                ->where('acceptedBid.is_mine', true)
                ->whereNot('acceptedBid.pay_url', null));

        $this->actingAs($this->buyer())
            ->get("/asset/{$listing->slug}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('acceptedBid.is_mine', false)
                ->where('acceptedBid.pay_url', null)
                ->where('canBid', false));
    }
}
