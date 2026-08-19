<?php
namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\InventoryType;
use App\Enums\OfferStatus;
use App\Models\Asset;
use App\Models\Bid;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Offer;
use App\Models\User;
use App\Services\ConversationService;
use App\Services\OfferService;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/**
 * Contact Seller and custom offers.
 *
 * Custom offers replace the old listing-level "Make an Offer". They live only
 * inside a conversation, work on all three inventory types, travel in both
 * directions — and no matter who sent one, the buyer is the party who pays.
 */
class CustomOfferTest extends TestCase
{
    use BuildsMarketplace;

    /** Opens (or reuses) the buyer↔seller thread and returns it. */
    private function contact(User $buyer, Asset $listing): Conversation
    {
        return app(ConversationService::class)->forListing($buyer, $listing);
    }

    private function offerFrom(User $creator, Conversation $conversation, int $amountBdt, int $quantity = 1): Offer
    {
        return app(OfferService::class)->createInConversation(
            $conversation,
            $creator,
            Money::toPoisha($amountBdt),
            $quantity,
        );
    }

    // ── Contact Seller ───────────────────────────────────────────────

    public function test_contact_seller_opens_a_chat_with_the_listing_as_context(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);

        $this->actingAs($buyer)
            ->post("/listings/{$listing->slug}/contact")
            ->assertRedirect();

        $conversation = Conversation::firstOrFail();
        $this->assertSame($listing->id, (int) $conversation->asset_id);
        $this->assertSame('direct', $conversation->type);
        $this->assertTrue($conversation->hasParticipant($buyer->id));
        $this->assertTrue($conversation->hasParticipant($seller->id));
        $this->assertSame($listing->id, $conversation->contextAsset()?->id);
    }

    public function test_contact_seller_twice_reuses_the_same_conversation(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);

        $this->actingAs($buyer)->post("/listings/{$listing->slug}/contact");
        $this->actingAs($buyer)->post("/listings/{$listing->slug}/contact");

        $this->assertSame(1, Conversation::count());
    }

    public function test_contact_seller_is_available_on_every_inventory_type(): void
    {
        $seller = $this->seller();

        foreach ([InventoryType::Single, InventoryType::Multiple, InventoryType::Unlimited] as $type) {
            $listing = $this->listing($seller, $type, 5000, 20);

            $this->actingAs($this->buyer())
                ->post("/listings/{$listing->slug}/contact")
                ->assertRedirect();
        }

        $this->assertSame(3, Conversation::count());
    }

    public function test_a_seller_cannot_contact_themselves(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);

        $this->actingAs($seller)
            ->post("/listings/{$listing->slug}/contact")
            ->assertForbidden();

        $this->assertSame(0, Conversation::count());
    }

    public function test_guest_cannot_contact_a_seller(): void
    {
        $listing = $this->listing($this->seller(), InventoryType::Single, 5000);

        $this->post("/listings/{$listing->slug}/contact")->assertRedirect('/login');
        $this->assertSame(0, Conversation::count());
    }

    /** A listing held at Bid Accepted is still reachable — Contact Seller stays open. */
    public function test_contact_seller_still_works_while_a_bid_is_accepted(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $listing->update(['status' => AssetStatus::BidAccepted]);

        $this->actingAs($this->buyer())
            ->post("/listings/{$listing->slug}/contact")
            ->assertRedirect();
    }

    // ── Creating a custom offer ──────────────────────────────────────

    public function test_buyer_can_send_a_custom_offer_in_the_chat(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $conv    = $this->contact($buyer, $listing);

        $this->actingAs($buyer)
            ->post("/dashboard/messages/{$conv->id}/offers", [
                'amount_bdt'    => 4200,
                'delivery_days' => 3,
                'note'          => 'Can you do this price?',
            ])
            ->assertSessionHas('success');

        $offer = Offer::firstOrFail();
        $this->assertSame(420000, (int) $offer->amount);
        $this->assertSame(3, (int) $offer->delivery_days);
        $this->assertSame($conv->id, (int) $offer->conversation_id);
        $this->assertSame($listing->id, (int) $offer->asset_id);
        $this->assertSame($buyer->id, (int) $offer->buyer_user_id);
        $this->assertSame($seller->id, (int) $offer->seller_user_id);
        $this->assertSame($buyer->id, (int) $offer->created_by_user_id);
        $this->assertSame(OfferStatus::Pending, $offer->status);

        // The card is a message in the thread, attributed to its sender.
        $card = Message::where('message_type', 'custom_offer')->firstOrFail();
        $this->assertSame($offer->message_id, $card->id);
        $this->assertSame($buyer->id, (int) $card->sender_user_id);
        $this->assertSame($offer->id, (int) $card->metadata['offer_id']);
    }

    public function test_seller_can_send_a_custom_offer_and_the_buyer_is_still_the_payer(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $conv    = $this->contact($buyer, $listing);

        $this->actingAs($seller)
            ->post("/dashboard/messages/{$conv->id}/offers", ['amount_bdt' => 4800])
            ->assertSessionHas('success');

        $offer = Offer::firstOrFail();
        $this->assertSame($seller->id, (int) $offer->created_by_user_id);
        $this->assertTrue($offer->wasCreatedBySeller());
        // Roles do not swap with the sender: the buyer still buys and still pays.
        $this->assertSame($buyer->id, (int) $offer->buyer_user_id);
        $this->assertSame($seller->id, (int) $offer->seller_user_id);
        $this->assertSame($buyer->id, $offer->responderId());
        $this->assertTrue($offer->isPayer($buyer->id));
        $this->assertFalse($offer->isPayer($seller->id));
    }

    public function test_custom_offers_work_on_every_inventory_type(): void
    {
        $seller = $this->seller();

        foreach ([InventoryType::Single, InventoryType::Multiple, InventoryType::Unlimited] as $type) {
            $buyer   = $this->buyer();
            $listing = $this->listing($seller, $type, 5000, 20);
            $conv    = $this->contact($buyer, $listing);

            $offer = $this->offerFrom($buyer, $conv, 4000);
            $this->assertSame(OfferStatus::Pending, $offer->status);
        }

        $this->assertSame(3, Offer::count());
    }

    /** Bids and custom offers are separate systems that do not read each other. */
    public function test_a_custom_offer_never_appears_in_the_public_bid_history(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $conv    = $this->contact($buyer, $listing);
        $this->offerFrom($buyer, $conv, 4200);

        $this->assertSame(0, Bid::count());

        $this->actingAs($this->buyer())
            ->get("/asset/{$listing->slug}")
            ->assertInertia(fn (Assert $page) => $page
                ->has('bids', 0)
                ->where('asset.top_bid_formatted', null));
    }

    public function test_a_stranger_cannot_send_an_offer_into_someone_elses_chat(): void
    {
        $seller  = $this->seller();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $conv    = $this->contact($this->buyer(), $listing);

        $this->actingAs($this->buyer())
            ->post("/dashboard/messages/{$conv->id}/offers", ['amount_bdt' => 100])
            ->assertForbidden();

        $this->assertSame(0, Offer::count());
    }

    /**
     * The request carries an amount and nothing else that matters: buyer, seller
     * and listing are all derived from the conversation's own listing.
     */
    public function test_offer_parties_cannot_be_forged_through_the_request(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $victim  = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $other   = $this->listing($this->seller(), InventoryType::Single, 90000);
        $conv    = $this->contact($buyer, $listing);

        $this->actingAs($buyer)->post("/dashboard/messages/{$conv->id}/offers", [
            'amount_bdt'         => 4200,
            'amount'             => 1,
            'buyer_user_id'      => $victim->id,
            'seller_user_id'     => $victim->id,
            'created_by_user_id' => $seller->id,
            'asset_id'           => $other->id,
            'status'             => OfferStatus::Accepted->value,
        ])->assertSessionHas('success');

        $offer = Offer::firstOrFail();
        $this->assertSame($buyer->id, (int) $offer->buyer_user_id);
        $this->assertSame($seller->id, (int) $offer->seller_user_id);
        $this->assertSame($buyer->id, (int) $offer->created_by_user_id);
        $this->assertSame($listing->id, (int) $offer->asset_id);
        $this->assertSame(420000, (int) $offer->amount);
        $this->assertSame(OfferStatus::Pending, $offer->status);
    }

    public function test_a_single_item_offer_must_be_for_one_unit(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $conv    = $this->contact($buyer, $listing);

        $this->actingAs($buyer)
            ->post("/dashboard/messages/{$conv->id}/offers", ['amount_bdt' => 4200, 'quantity' => 2])
            ->assertStatus(422);
    }

    public function test_a_multiple_quantity_offer_cannot_exceed_the_stock(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Multiple, 5000, 5);
        $conv    = $this->contact($buyer, $listing);

        $this->actingAs($buyer)
            ->post("/dashboard/messages/{$conv->id}/offers", ['amount_bdt' => 4200, 'quantity' => 6])
            ->assertStatus(422);

        $this->actingAs($buyer)
            ->post("/dashboard/messages/{$conv->id}/offers", ['amount_bdt' => 4200, 'quantity' => 5])
            ->assertSessionHas('success');
    }

    public function test_an_unlimited_listing_accepts_any_offer_quantity(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Unlimited, 5000);
        $conv    = $this->contact($buyer, $listing);

        $offer = $this->offerFrom($buyer, $conv, 4200, 50);
        $this->assertSame(50, (int) $offer->quantity);
    }

    public function test_a_sender_cannot_stack_two_pending_offers_but_the_other_party_can_counter(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $conv    = $this->contact($buyer, $listing);

        $this->offerFrom($buyer, $conv, 4200);

        $this->actingAs($buyer)
            ->post("/dashboard/messages/{$conv->id}/offers", ['amount_bdt' => 4300])
            ->assertStatus(422);

        // The seller countering is a different creator, so it is allowed.
        $this->actingAs($seller)
            ->post("/dashboard/messages/{$conv->id}/offers", ['amount_bdt' => 4800])
            ->assertSessionHas('success');

        $this->assertSame(2, Offer::count());
    }

    // ── Responding ───────────────────────────────────────────────────

    public function test_the_sender_cannot_accept_their_own_offer(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $conv    = $this->contact($buyer, $listing);
        $offer   = $this->offerFrom($buyer, $conv, 4200);

        $this->actingAs($buyer)->post("/offers/{$offer->id}/accept")->assertForbidden();
        $this->assertSame(OfferStatus::Pending, $offer->fresh()->status);
    }

    public function test_an_outsider_cannot_respond_to_an_offer(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $conv    = $this->contact($buyer, $listing);
        $offer   = $this->offerFrom($buyer, $conv, 4200);

        $stranger = $this->buyer();
        $this->actingAs($stranger)->post("/offers/{$offer->id}/accept")->assertForbidden();
        $this->actingAs($stranger)->post("/offers/{$offer->id}/reject")->assertForbidden();
        $this->actingAs($stranger)->post("/offers/{$offer->id}/cancel")->assertForbidden();
    }

    public function test_seller_accepting_the_buyers_offer_leaves_the_payment_to_the_buyer(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $conv    = $this->contact($buyer, $listing);
        $offer   = $this->offerFrom($buyer, $conv, 4200);

        $this->actingAs($seller)
            ->post("/offers/{$offer->id}/accept")
            ->assertSessionHas('success');

        $offer->refresh();
        $this->assertSame(OfferStatus::Accepted, $offer->status);
        $this->assertNotNull($offer->responded_at);

        // The seller who accepted gets no Pay button; the buyer does.
        $this->assertFalse(Gate::forUser($seller)->allows('pay', $offer));
        $this->assertTrue(Gate::forUser($buyer)->allows('pay', $offer));
    }

    /** The buyer accepting the seller's offer is the "Accept & Pay" path. */
    public function test_buyer_accepting_the_sellers_offer_goes_straight_to_checkout(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $conv    = $this->contact($buyer, $listing);
        $offer   = $this->offerFrom($seller, $conv, 4800);

        $this->actingAs($buyer)
            ->post("/offers/{$offer->id}/accept")
            ->assertRedirect(route('checkout.show', [
                'slug'  => $listing->slug,
                'offer' => $offer->id,
                'qty'   => 1,
            ]));

        $this->assertSame(OfferStatus::Accepted, $offer->fresh()->status);
    }

    public function test_a_seller_cannot_pay_for_their_own_accepted_offer(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $conv    = $this->contact($buyer, $listing);
        $offer   = $this->offerFrom($seller, $conv, 4800);

        app(OfferService::class)->accept($offer, $buyer);

        // Checkout scopes the offer to its buyer, so the seller gets nothing.
        $this->actingAs($seller)
            ->get("/checkout/{$listing->slug}?offer={$offer->id}")
            ->assertNotFound();

        $this->actingAs($buyer)
            ->get("/checkout/{$listing->slug}?offer={$offer->id}")
            ->assertOk();
    }

    public function test_the_buyer_pays_the_offer_amount_not_the_asking_price(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $conv    = $this->contact($buyer, $listing);
        $offer   = $this->offerFrom($buyer, $conv, 4200);

        app(OfferService::class)->accept($offer, $seller);

        $this->actingAs($buyer)
            ->get("/checkout/{$listing->slug}?offer={$offer->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Checkout/Show')
                ->where('has_offer', true)
                ->where('has_bid', false)
                ->where('fees.unit_price', Money::format(420000))
                ->where('order.offer_id', $offer->id));
    }

    /** Accepting is not selling — the listing stays live until payment. */
    public function test_accepting_an_offer_does_not_mark_the_listing_sold(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Multiple, 5000, 10);
        $conv    = $this->contact($buyer, $listing);
        $offer   = $this->offerFrom($buyer, $conv, 4200, 2);

        app(OfferService::class)->accept($offer, $seller);

        $listing->refresh();
        $this->assertSame(AssetStatus::Published, $listing->status);
        $this->assertSame(10, (int) $listing->available_quantity);
        $this->assertSame(0, (int) $listing->sold_quantity);
        $this->assertTrue($listing->isAvailableForPurchase());
    }

    /**
     * One accepted custom offer must not close the listing down: Buy Now and
     * further offers stay open on Multiple and Unlimited.
     */
    public function test_an_accepted_offer_does_not_disable_buy_now_for_multiple_or_unlimited(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Multiple, 5000, 10);
        $conv    = $this->contact($buyer, $listing);
        $offer   = $this->offerFrom($buyer, $conv, 4200, 2);
        app(OfferService::class)->accept($offer, $seller);

        // Another buyer can still buy at the asking price…
        $other = $this->buyer();
        $this->actingAs($other)->get("/checkout/{$listing->slug}?qty=1")->assertOk();

        // …and still negotiate their own offer.
        $otherConv = $this->contact($other, $listing->fresh());
        $this->actingAs($other)
            ->post("/dashboard/messages/{$otherConv->id}/offers", ['amount_bdt' => 4100])
            ->assertSessionHas('success');
    }

    public function test_declining_an_offer_sets_it_to_rejected_and_posts_an_event(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $conv    = $this->contact($buyer, $listing);
        $offer   = $this->offerFrom($buyer, $conv, 4200);

        $this->actingAs($seller)->post("/offers/{$offer->id}/reject")->assertSessionHas('success');

        $offer->refresh();
        $this->assertSame(OfferStatus::Rejected, $offer->status);
        $this->assertSame('Declined', $offer->status->label());
        $this->assertNotNull($offer->rejected_at);
        $this->assertSame(1, Message::where('message_type', 'custom_offer_event')->count());
    }

    public function test_the_sender_can_withdraw_their_own_pending_offer(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $conv    = $this->contact($buyer, $listing);
        $offer   = $this->offerFrom($buyer, $conv, 4200);

        // The responder cannot withdraw it…
        $this->actingAs($seller)->post("/offers/{$offer->id}/cancel")->assertForbidden();

        // …the sender can.
        $this->actingAs($buyer)->post("/offers/{$offer->id}/cancel")->assertSessionHas('success');
        $this->assertSame(OfferStatus::Cancelled, $offer->fresh()->status);
    }

    public function test_an_expired_offer_cannot_be_accepted(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $conv    = $this->contact($buyer, $listing);
        $offer   = $this->offerFrom($buyer, $conv, 4200);

        $offer->update(['expires_at' => now()->subMinute()]);

        // The deadline is enforced at accept time, not left to a scheduled sweep,
        // so a late accept both fails and settles the offer.
        $this->actingAs($seller)->post("/offers/{$offer->id}/accept")->assertStatus(422);
        $this->assertSame(OfferStatus::Expired, $offer->fresh()->status);
    }

    public function test_the_sweeper_expires_stale_pending_offers(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $conv    = $this->contact($buyer, $listing);
        $offer   = $this->offerFrom($buyer, $conv, 4200);

        // Fresh offers are left alone.
        $this->assertSame(0, app(OfferService::class)->expireStale());

        $offer->update(['expires_at' => now()->subMinute()]);

        $this->assertSame(1, app(OfferService::class)->expireStale());
        $offer->refresh();
        $this->assertSame(OfferStatus::Expired, $offer->status);
        $this->assertNotNull($offer->expired_at);
    }

    public function test_accepting_one_offer_supersedes_the_other_pending_offer(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $conv    = $this->contact($buyer, $listing);

        $buyersOffer  = $this->offerFrom($buyer, $conv, 4200);
        $sellersOffer = $this->offerFrom($seller, $conv, 4800);

        app(OfferService::class)->accept($sellersOffer, $buyer);

        $this->assertSame(OfferStatus::Accepted, $sellersOffer->fresh()->status);
        $this->assertSame(OfferStatus::Cancelled, $buyersOffer->fresh()->status);
    }

    public function test_an_accepted_offer_cannot_be_accepted_again(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $conv    = $this->contact($buyer, $listing);
        $offer   = $this->offerFrom($buyer, $conv, 4200);

        app(OfferService::class)->accept($offer, $seller);

        $this->expectException(HttpException::class);
        app(OfferService::class)->accept($offer->fresh(), $seller);
    }

    // ── What the chat is told ────────────────────────────────────────

    public function test_the_chat_carries_the_listing_context_and_offer_cards(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Multiple, 5000, 7);
        $conv    = $this->contact($buyer, $listing);
        $offer   = $this->offerFrom($seller, $conv, 4800, 2);

        $this->actingAs($buyer)
            ->get("/dashboard/messages?conversation={$conv->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Messages/Index')
                ->where('listing.title', $listing->title)
                ->where('listing.inventory_type', 'multiple')
                ->where('listing.max_quantity', 7)
                ->where('listing.url', route('marketplace.show', $listing->slug))
                ->where('canOffer', true)
                // The buyer did not send this one, so they are the responder —
                // and being the payer, their accept is "Accept & Pay".
                ->where("offers.{$offer->id}.mine", false)
                ->where("offers.{$offer->id}.can_accept", true)
                ->where("offers.{$offer->id}.accept_is_pay", true)
                ->where("offers.{$offer->id}.can_cancel", false)
                ->where("offers.{$offer->id}.quantity", 2)
                ->where("offers.{$offer->id}.status", 'pending'));
    }

    public function test_the_seller_side_of_the_chat_never_offers_a_pay_button(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $conv    = $this->contact($buyer, $listing);
        $offer   = $this->offerFrom($seller, $conv, 4800);

        app(OfferService::class)->accept($offer, $buyer);

        $this->actingAs($seller)
            ->get("/dashboard/messages?conversation={$conv->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where("offers.{$offer->id}.status", 'accepted')
                ->where("offers.{$offer->id}.can_pay", false)
                ->where("offers.{$offer->id}.pay_url", null));

        $this->actingAs($buyer)
            ->get("/dashboard/messages?conversation={$conv->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where("offers.{$offer->id}.can_pay", true)
                ->whereNot("offers.{$offer->id}.pay_url", null));
    }

    /** State changes have to reach the other side without a page refresh. */
    public function test_the_poll_endpoint_carries_offer_state_alongside_messages(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $conv    = $this->contact($buyer, $listing);
        $offer   = $this->offerFrom($buyer, $conv, 4200);

        app(OfferService::class)->accept($offer, $seller);

        $this->actingAs($buyer)
            ->getJson("/api/conversations/{$conv->id}/poll")
            ->assertOk()
            ->assertJsonPath("offers.{$offer->id}.status", 'accepted')
            ->assertJsonPath("offers.{$offer->id}.can_pay", true);

        $this->actingAs($this->buyer())
            ->getJson("/api/conversations/{$conv->id}/poll")
            ->assertForbidden();
    }

    /**
     * §16: no page refresh. With no broadcaster configured the page must report
     * "not ready" so the Vue side keeps polling — BROADCAST_DRIVER=null reaches
     * config() as PHP null, not the string "null".
     */
    public function test_the_chat_reports_realtime_readiness_from_the_broadcaster(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $conv    = $this->contact($buyer, $listing);

        // env() casts BROADCAST_DRIVER=null to PHP null; a literal "null" string
        // is the other shape the same setting arrives in. Neither is a broadcaster.
        foreach ([null, 'null'] as $driver) {
            config(['broadcasting.default' => $driver]);

            $this->actingAs($buyer)
                ->get("/dashboard/messages?conversation={$conv->id}")
                ->assertInertia(fn (Assert $page) => $page->where('isRealtimeReady', false));
        }

        config(['broadcasting.default' => 'reverb']);

        $this->actingAs($buyer)
            ->get("/dashboard/messages?conversation={$conv->id}")
            ->assertInertia(fn (Assert $page) => $page->where('isRealtimeReady', true));
    }

    public function test_no_offer_can_be_sent_once_the_listing_is_no_longer_published(): void
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, InventoryType::Single, 5000);
        $conv    = $this->contact($buyer, $listing);

        $listing->update(['status' => AssetStatus::Paused]);

        $this->actingAs($buyer)
            ->post("/dashboard/messages/{$conv->id}/offers", ['amount_bdt' => 4200])
            ->assertStatus(422);

        $this->actingAs($buyer)
            ->get("/dashboard/messages?conversation={$conv->id}")
            ->assertInertia(fn (Assert $page) => $page->where('canOffer', false));
    }
}
