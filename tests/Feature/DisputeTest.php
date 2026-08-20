<?php
namespace Tests\Feature;

use App\Enums\DisputeMessageType;
use App\Enums\DisputeReason;
use App\Enums\DisputeResolutionType;
use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Models\Dispute;
use App\Models\DisputeResolution;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use App\Services\DisputeService;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/**
 * The buyer's and seller's side of a dispute.
 *
 * The admin decisions are covered in InertiaMigrationTest; everything here is
 * what the two parties can do without staff — open, argue, attach evidence,
 * settle between themselves, or hand it over.
 *
 * Money is the point of most of these. The seller's earning is credited to their
 * PENDING balance when the order is paid, so every outcome is asserted on both
 * wallets: a refund has to reverse that hold rather than leave it sitting there.
 */
class DisputeTest extends TestCase
{
    use BuildsMarketplace;

    private const PRICE_BDT = 5000;              // ৳5,000.00
    private const BUYER_TOTAL = 500000;          // poisha
    private const SELLER_EARNING = 450000;       // after the 10% seller fee

    /**
     * A paid order, left the way OrderService::confirmPayment would leave one:
     * the buyer has paid, the seller's earning is held in pending, and nothing
     * has been released.
     *
     * Order::factory() does not exist — the model imports HasFactory without
     * applying it (see .github/known-test-failures.txt) — so the row is built
     * with create(). Asset::factory() works and supplies the seller's listing.
     */
    private function paidOrder(OrderStatus $status = OrderStatus::Delivered): Order
    {
        $seller  = $this->seller();
        $buyer   = $this->buyer();
        $listing = $this->listing($seller, priceBdt: self::PRICE_BDT);

        $this->walletFor($buyer);
        Wallet::where('user_id', $seller->id)->update(['pending_balance' => self::SELLER_EARNING]);

        return Order::create([
            'reference'           => 'REF-'.strtoupper(Str::random(12)),
            'order_number'        => 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
            'buyer_user_id'       => $buyer->id,
            'seller_user_id'      => $seller->id,
            'asset_id'            => $listing->id,
            'quantity'            => 1,
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
            'delivery_status'     => $status === OrderStatus::Delivered ? 'delivered' : 'not_started',
            'payment_gateway'     => 'uddoktapay',
            'paid_at'             => now(),
            'delivered_at'        => $status === OrderStatus::Delivered ? now() : null,
            'earning_released'    => false,
        ]);
    }

    /** Opens a real dispute through the service, so the order history is written too. */
    private function disputeFor(
        Order $order,
        DisputeReason $reason = DisputeReason::NotWorking,
        string $description = 'The login stopped working two days after handover.',
    ): Dispute {
        return app(DisputeService::class)->open($order, $order->buyer, $reason, $description);
    }

    /** An order with a live dispute on it. */
    private function disputedOrder(OrderStatus $status = OrderStatus::Delivered): Dispute
    {
        return $this->disputeFor($this->paidOrder($status));
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::create([
            'name'          => 'dispute-admin-'.$user->id,
            'display_name'  => 'Administrator',
            'is_admin_role' => true,
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function balances(User $user): array
    {
        $wallet = Wallet::where('user_id', $user->id)->firstOrFail();

        return [(int) $wallet->available_balance, (int) $wallet->pending_balance];
    }

    /**
     * A fake PNG for the evidence tests.
     *
     * Not UploadedFile::fake()->image(), which needs the GD extension — it is not
     * loaded in this environment. create() with an explicit mime satisfies the
     * `mimes:` rule the same way without drawing anything.
     */
    private function pngFile(string $name): UploadedFile
    {
        return UploadedFile::fake()->create($name, 64, 'image/png');
    }

    // ── Opening ──────────────────────────────────────────────────────

    public function test_buyer_opens_a_dispute_and_lands_on_its_thread(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($order->buyer)
            ->post("/dashboard/orders/{$order->id}/dispute", [
                'reason_code' => 'not_working',
                'description' => 'The analytics dashboard has been down since handover.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $dispute = Dispute::firstOrFail();
        $this->assertSame('not_working', $dispute->reason_code->value);
        $this->assertSame(DisputeStatus::Open, $dispute->status);
        $this->assertSame($order->buyer_user_id, (int) $dispute->opened_by);
        // The handle is derived from the id, so it can only be set post-insert.
        $this->assertSame('D-'.(10000 + $dispute->id), $dispute->reference);
        $this->assertNotNull($dispute->last_activity_at);

        // The order mirrors the dispute for the life of it.
        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::Disputed, $fresh->status);
        $this->assertSame('open', $fresh->dispute_status);

        // …and the status it displaced is preserved, which is what cancel() restores.
        $this->assertSame('delivered', $order->statusHistory()
            ->where('to_status', 'disputed')->value('from_status'));

        // Opening writes the first line of the thread.
        $this->assertSame(1, $dispute->messages()->count());
        $this->assertSame(DisputeMessageType::System, $dispute->messages()->first()->type);
    }

    public function test_the_dispute_form_offers_only_reasons_the_request_accepts(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($order->buyer)
            ->get("/dashboard/orders/{$order->id}/dispute")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Orders/Dispute')
                ->has('reasons', count(DisputeReason::cases()))
                ->where('order.total', '৳5,000.00')
                ->etc()
            );
    }

    public function test_a_reason_outside_the_fixed_list_is_rejected(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($order->buyer)
            ->post("/dashboard/orders/{$order->id}/dispute", [
                'reason_code' => 'seller_was_rude',
                'description' => 'A description long enough to pass the length rule.',
            ])
            ->assertSessionHasErrors('reason_code');

        $this->assertSame(0, Dispute::count());
    }

    public function test_a_description_is_required_even_when_the_reason_is_fixed(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($order->buyer)
            ->post("/dashboard/orders/{$order->id}/dispute", [
                'reason_code' => 'other',
                'description' => 'too short',
            ])
            ->assertSessionHasErrors('description');

        $this->assertSame(0, Dispute::count());
    }

    public function test_the_seller_cannot_open_a_dispute_against_their_own_buyer(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($order->seller)
            ->post("/dashboard/orders/{$order->id}/dispute", [
                'reason_code' => 'not_working',
                'description' => 'A description long enough to pass the length rule.',
            ])
            ->assertForbidden();

        $this->assertSame(0, Dispute::count());
    }

    public function test_a_second_dispute_cannot_be_opened_while_one_is_live(): void
    {
        $dispute = $this->disputedOrder();
        $order   = $dispute->order;

        // The order is Disputed now, so the policy stops it before the service does.
        $this->actingAs($order->buyer)
            ->post("/dashboard/orders/{$order->id}/dispute", [
                'reason_code' => 'other',
                'description' => 'A second complaint about the very same order.',
            ])
            ->assertForbidden();

        $this->assertSame(1, Dispute::count());
    }

    public function test_a_completed_order_can_no_longer_be_disputed(): void
    {
        $order = $this->paidOrder(OrderStatus::Completed);

        $this->actingAs($order->buyer)
            ->post("/dashboard/orders/{$order->id}/dispute", [
                'reason_code' => 'not_working',
                'description' => 'A description long enough to pass the length rule.',
            ])
            ->assertForbidden();
    }

    // ── The list and the thread ──────────────────────────────────────

    public function test_the_dispute_list_shows_only_disputes_the_viewer_is_party_to(): void
    {
        $mine      = $this->disputedOrder();
        $strangers = $this->disputedOrder();

        $this->actingAs($mine->order->buyer)
            ->get('/dashboard/disputes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Disputes/Index')
                ->has('disputes.data', 1)
                ->has('disputes.data.0', fn (Assert $d) => $d
                    ->where('reference', $mine->displayReference())
                    ->where('role', 'buyer')
                    ->where('counterparty', $mine->order->seller->name)
                    ->where('status', 'open')
                    ->where('total', '৳5,000.00')
                    ->where('is_active', true)
                    ->etc()
                )
            );

        $this->assertNotSame($mine->id, $strangers->id);
    }

    public function test_the_seller_sees_the_same_dispute_from_their_own_side(): void
    {
        $dispute = $this->disputedOrder();

        $this->actingAs($dispute->order->seller)
            ->get('/dashboard/disputes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('disputes.data', 1)
                ->has('disputes.data.0', fn (Assert $d) => $d
                    ->where('role', 'seller')
                    ->where('counterparty', $dispute->order->buyer->name)
                    ->etc()
                )
            );
    }

    public function test_the_thread_renders_with_the_viewers_own_permissions(): void
    {
        $dispute = $this->disputedOrder();

        $this->actingAs($dispute->order->buyer)
            ->get("/dashboard/disputes/{$dispute->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Disputes/Show')
                ->where('role', 'buyer')
                ->where('dispute.status', 'open')
                ->where('dispute.is_active', true)
                ->where('dispute.reason', "Asset doesn't work as described")
                ->where('order.buyer_total', '৳5,000.00')
                // Raw taka drives the partial-refund input's max. Asserted for
                // presence only — it serialises as a JSON number, so its PHP type
                // is not stable enough to pin. The ceiling that actually matters
                // is the server's, covered further down.
                ->has('order.buyer_total_bdt')
                ->has('thread', 1)
                ->where('pending', null)
                // Release-to-seller is an admin decision, never a negotiable option.
                ->has('options', 3)
                ->where('can.propose', true)
                ->where('can.escalate', true)
                // Only the buyer may drop their own claim.
                ->where('can.cancel', true)
                ->where('can.respond', false)
            );
    }

    public function test_the_seller_may_not_cancel_a_dispute_filed_against_them(): void
    {
        $dispute = $this->disputedOrder();

        $this->actingAs($dispute->order->seller)
            ->get("/dashboard/disputes/{$dispute->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('role', 'seller')
                ->where('can.cancel', false)
                ->where('can.propose', true)
                ->etc()
            );

        $this->actingAs($dispute->order->seller)
            ->post("/dashboard/disputes/{$dispute->id}/cancel")
            ->assertForbidden();

        $this->assertTrue($dispute->fresh()->isActive());
    }

    public function test_an_outsider_cannot_read_a_dispute_thread(): void
    {
        $dispute  = $this->disputedOrder();
        $stranger = $this->buyer();

        $this->actingAs($stranger)
            ->get("/dashboard/disputes/{$dispute->id}")
            ->assertForbidden();
    }

    // ── Messages ─────────────────────────────────────────────────────

    public function test_a_party_can_post_to_the_thread(): void
    {
        $dispute = $this->disputedOrder();

        $this->actingAs($dispute->order->buyer)
            ->post("/dashboard/disputes/{$dispute->id}/messages", [
                'body' => 'I have attached the error page I get on login.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $message = $dispute->messages()->where('type', DisputeMessageType::Text->value)->firstOrFail();
        $this->assertSame('buyer', $message->role);
        $this->assertSame($dispute->order->buyer_user_id, (int) $message->user_id);
    }

    public function test_the_sellers_first_reply_moves_the_dispute_off_open(): void
    {
        $dispute = $this->disputedOrder();

        $this->actingAs($dispute->order->seller)
            ->post("/dashboard/disputes/{$dispute->id}/messages", [
                'body' => 'I can reset the credentials this evening.',
            ])
            ->assertRedirect();

        $fresh = $dispute->fresh();
        $this->assertSame(DisputeStatus::SellerResponded, $fresh->status);
        $this->assertNotNull($fresh->seller_responded_at);
        // The order's mirror follows, so order screens never have to join.
        $this->assertSame('seller_responded', $dispute->order->fresh()->dispute_status);
    }

    public function test_a_buyer_reply_does_not_claim_the_seller_responded(): void
    {
        $dispute = $this->disputedOrder();

        $this->actingAs($dispute->order->buyer)
            ->post("/dashboard/disputes/{$dispute->id}/messages", ['body' => 'Any update on this?']);

        $this->assertSame(DisputeStatus::Open, $dispute->fresh()->status);
    }

    public function test_a_double_submitted_composer_posts_once(): void
    {
        $dispute = $this->disputedOrder();
        $payload = [
            'body'              => 'Sending this twice by accident.',
            'client_message_id' => 'composer-abc-123',
        ];

        $this->actingAs($dispute->order->buyer)->post("/dashboard/disputes/{$dispute->id}/messages", $payload);
        $this->actingAs($dispute->order->buyer)->post("/dashboard/disputes/{$dispute->id}/messages", $payload);

        $this->assertSame(1, $dispute->messages()->where('type', DisputeMessageType::Text->value)->count());
    }

    public function test_internal_notes_never_reach_the_parties(): void
    {
        $dispute = $this->disputedOrder();
        $admin   = $this->makeAdmin();

        app(DisputeService::class)->addInternalNote($dispute, $admin, 'Buyer has opened three of these this month.');

        $this->assertTrue($dispute->fresh()->hasInternalNotes());

        // The buyer's thread carries the opening system line and nothing else.
        $this->actingAs($dispute->order->buyer)
            ->get("/dashboard/disputes/{$dispute->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('thread', 1));

        $this->actingAs($dispute->order->seller)
            ->get("/dashboard/disputes/{$dispute->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('thread', 1));

        // Staff see both rows.
        $this->actingAs($admin)
            ->get("/admin/disputes/{$dispute->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('thread', 2));
    }

    public function test_a_settled_dispute_stops_accepting_messages(): void
    {
        $dispute = $this->disputedOrder();
        app(DisputeService::class)->cancel($dispute, $dispute->order->buyer, 'Sorted it out directly.');

        $this->actingAs($dispute->order->buyer)
            ->post("/dashboard/disputes/{$dispute->id}/messages", ['body' => 'One more thing.'])
            ->assertForbidden();
    }

    // ── Evidence ─────────────────────────────────────────────────────

    public function test_evidence_is_stored_privately_and_joins_the_thread(): void
    {
        Storage::fake('private');
        $dispute = $this->disputedOrder();

        $this->actingAs($dispute->order->buyer)
            ->post("/dashboard/disputes/{$dispute->id}/evidence", [
                'file' => $this->pngFile('login-error.png'),
                'note' => 'The error I see on every login attempt.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $evidence = $dispute->evidence()->firstOrFail();
        $this->assertSame('buyer', $evidence->role);
        $this->assertSame('private', $evidence->file_disk);
        $this->assertSame('login-error.png', $evidence->file_original_name);
        Storage::disk('private')->assertExists($evidence->file_path);

        // It is a thread row too, not a sidebar attachment.
        $this->assertSame(1, $dispute->messages()->where('type', DisputeMessageType::Evidence->value)->count());
        $this->assertSame($evidence->message_id, $dispute->messages()
            ->where('type', DisputeMessageType::Evidence->value)->value('id'));
    }

    public function test_an_executable_upload_is_refused(): void
    {
        Storage::fake('private');
        $dispute = $this->disputedOrder();

        $this->actingAs($dispute->order->buyer)
            ->post("/dashboard/disputes/{$dispute->id}/evidence", [
                'file' => UploadedFile::fake()->create('payload.php', 12, 'application/x-php'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, $dispute->evidence()->count());
    }

    public function test_only_a_party_can_download_evidence(): void
    {
        Storage::fake('private');
        $dispute = $this->disputedOrder();

        $this->actingAs($dispute->order->buyer)->post("/dashboard/disputes/{$dispute->id}/evidence", [
            'file' => $this->pngFile('proof.png'),
        ]);
        $evidence = $dispute->evidence()->firstOrFail();
        $url      = "/dashboard/disputes/{$dispute->id}/evidence/{$evidence->id}";

        $this->actingAs($dispute->order->buyer)->get($url)->assertOk();
        $this->actingAs($dispute->order->seller)->get($url)->assertOk();
        $this->actingAs($this->buyer())->get($url)->assertForbidden();
    }

    public function test_evidence_ids_cannot_be_walked_across_disputes(): void
    {
        Storage::fake('private');
        $mine     = $this->disputedOrder();
        $stranger = $this->disputedOrder();

        $this->actingAs($stranger->order->buyer)->post("/dashboard/disputes/{$stranger->id}/evidence", [
            'file' => $this->pngFile('theirs.png'),
        ]);
        $theirs = $stranger->evidence()->firstOrFail();

        // A party to one dispute, quoting their own dispute id with someone
        // else's evidence id, gets a 404 rather than the file.
        $this->actingAs($mine->order->buyer)
            ->get("/dashboard/disputes/{$mine->id}/evidence/{$theirs->id}")
            ->assertNotFound();
    }

    // ── Negotiation ──────────────────────────────────────────────────

    public function test_a_proposal_puts_the_dispute_into_negotiating(): void
    {
        $dispute = $this->disputedOrder();

        $this->actingAs($dispute->order->buyer)
            ->post("/dashboard/disputes/{$dispute->id}/proposals", [
                'type'       => 'partial_refund',
                'amount_bdt' => 2000,
                'note'       => 'Half of it works, so half the money back seems fair.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $dispute->fresh();
        $this->assertSame(DisputeStatus::Negotiating, $fresh->status);
        $this->assertSame('negotiating', $dispute->order->fresh()->dispute_status);

        $pending = $fresh->pendingResolution();
        $this->assertNotNull($pending);
        $this->assertSame(DisputeResolutionType::PartialRefund, $pending->type);
        $this->assertSame(200000, $pending->amount);
        $this->assertSame('buyer', $pending->role);
        $this->assertSame('seller', $pending->awaitingRole());
    }

    public function test_the_other_side_sees_the_proposal_as_theirs_to_answer(): void
    {
        $dispute = $this->disputedOrder();
        app(DisputeService::class)->propose(
            $dispute, $dispute->order->buyer, DisputeResolutionType::PartialRefund, 200000, 'Half back.',
        );

        $this->actingAs($dispute->order->seller)
            ->get("/dashboard/disputes/{$dispute->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pending.type', 'partial_refund')
                ->where('pending.amount', '৳2,000.00')
                ->where('pending.is_mine', false)
                ->where('pending.awaiting', 'seller')
                ->where('can.respond', true)
                ->where('can.withdraw', false)
                ->etc()
            );

        // …and the proposer may take it back, but not answer it.
        $this->actingAs($dispute->order->buyer)
            ->get("/dashboard/disputes/{$dispute->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('pending.is_mine', true)
                ->where('can.respond', false)
                ->where('can.withdraw', true)
                ->etc()
            );
    }

    public function test_a_new_proposal_supersedes_the_one_on_the_table(): void
    {
        $dispute = $this->disputedOrder();
        $service = app(DisputeService::class);

        $first = $service->propose($dispute, $dispute->order->buyer, DisputeResolutionType::PartialRefund, 300000, '');
        $second = $service->propose($dispute, $dispute->order->seller, DisputeResolutionType::PartialRefund, 100000, '');

        $this->assertSame(DisputeResolution::STATUS_WITHDRAWN, $first->fresh()->status);
        $this->assertSame($second->id, $dispute->fresh()->pendingResolution()?->id);
        // Only one is ever live, so the stalest can never be accepted.
        $this->assertSame(1, $dispute->resolutions()->where('status', DisputeResolution::STATUS_PROPOSED)->count());
    }

    public function test_nobody_can_accept_their_own_proposal(): void
    {
        $dispute    = $this->disputedOrder();
        $resolution = app(DisputeService::class)->propose(
            $dispute, $dispute->order->buyer, DisputeResolutionType::PartialRefund, 200000, '',
        );

        $this->actingAs($dispute->order->buyer)
            ->post("/dashboard/dispute-proposals/{$resolution->id}/accept")
            ->assertForbidden();

        $this->assertNull($resolution->fresh()->executed_at);
        $this->assertTrue($dispute->fresh()->isActive());
    }

    public function test_an_outsider_cannot_answer_a_proposal(): void
    {
        $dispute    = $this->disputedOrder();
        $resolution = app(DisputeService::class)->propose(
            $dispute, $dispute->order->buyer, DisputeResolutionType::PartialRefund, 200000, '',
        );

        $this->actingAs($this->buyer())
            ->post("/dashboard/dispute-proposals/{$resolution->id}/accept")
            ->assertForbidden();

        $this->assertNull($resolution->fresh()->executed_at);
    }

    public function test_releasing_the_payment_cannot_be_proposed_by_a_party(): void
    {
        $dispute = $this->disputedOrder();

        $this->actingAs($dispute->order->seller)
            ->post("/dashboard/disputes/{$dispute->id}/proposals", ['type' => 'release_seller'])
            ->assertSessionHasErrors('type');

        $this->assertSame(0, $dispute->resolutions()->count());
    }

    public function test_a_partial_refund_cannot_exceed_what_the_buyer_paid(): void
    {
        $dispute = $this->disputedOrder();

        $this->actingAs($dispute->order->buyer)
            ->post("/dashboard/disputes/{$dispute->id}/proposals", [
                'type'       => 'partial_refund',
                'amount_bdt' => 6000,   // the order was ৳5,000.00
            ])
            ->assertStatus(422);

        $this->assertSame(0, $dispute->resolutions()->count());
    }

    public function test_a_partial_refund_proposal_must_name_an_amount(): void
    {
        $dispute = $this->disputedOrder();

        $this->actingAs($dispute->order->buyer)
            ->post("/dashboard/disputes/{$dispute->id}/proposals", ['type' => 'partial_refund'])
            ->assertStatus(422);

        $this->assertSame(0, $dispute->resolutions()->count());
    }

    public function test_declining_leaves_the_dispute_open_for_another_round(): void
    {
        $dispute    = $this->disputedOrder();
        $resolution = app(DisputeService::class)->propose(
            $dispute, $dispute->order->buyer, DisputeResolutionType::PartialRefund, 200000, '',
        );

        $this->actingAs($dispute->order->seller)
            ->post("/dashboard/dispute-proposals/{$resolution->id}/decline", ['note' => 'It works on my side.'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(DisputeResolution::STATUS_DECLINED, $resolution->fresh()->status);
        $this->assertTrue($dispute->fresh()->isActive());
        $this->assertNull($dispute->fresh()->pendingResolution());
        // No money moved.
        $this->assertSame([0, 0], $this->balances($dispute->order->buyer));
        $this->assertSame([0, self::SELLER_EARNING], $this->balances($dispute->order->seller));
    }

    public function test_the_proposer_can_withdraw_their_own_offer(): void
    {
        $dispute    = $this->disputedOrder();
        $resolution = app(DisputeService::class)->propose(
            $dispute, $dispute->order->seller, DisputeResolutionType::PartialRefund, 100000, '',
        );

        $this->actingAs($dispute->order->seller)
            ->post("/dashboard/dispute-proposals/{$resolution->id}/withdraw")
            ->assertRedirect();

        $this->assertSame(DisputeResolution::STATUS_WITHDRAWN, $resolution->fresh()->status);
        $this->assertNull($dispute->fresh()->pendingResolution());
    }

    public function test_the_other_side_cannot_withdraw_a_proposal_they_did_not_make(): void
    {
        $dispute    = $this->disputedOrder();
        $resolution = app(DisputeService::class)->propose(
            $dispute, $dispute->order->seller, DisputeResolutionType::PartialRefund, 100000, '',
        );

        $this->actingAs($dispute->order->buyer)
            ->post("/dashboard/dispute-proposals/{$resolution->id}/withdraw")
            ->assertForbidden();

        $this->assertTrue($resolution->fresh()->isPending());
    }

    // ── Settlement ───────────────────────────────────────────────────

    public function test_accepting_a_partial_refund_splits_the_money_proportionally(): void
    {
        $dispute    = $this->disputedOrder();
        $buyer      = $dispute->order->buyer;
        $seller     = $dispute->order->seller;
        $resolution = app(DisputeService::class)->propose(
            $dispute, $buyer, DisputeResolutionType::PartialRefund, 200000, 'Half back.',
        );

        $this->actingAs($seller)
            ->post("/dashboard/dispute-proposals/{$resolution->id}/accept")
            ->assertRedirect()
            ->assertSessionHas('success');

        // ৳2,000.00 of ৳5,000.00 back to the buyer…
        $this->assertSame([200000, 0], $this->balances($buyer));
        // …so 40% of the ৳4,500.00 earning is reversed and the other 60% released,
        // leaving nothing stranded in pending.
        $this->assertSame([270000, 0], $this->balances($seller));

        $fresh = $dispute->fresh();
        // Settled by the parties, so Refunded — not the admin-only resolved_partial.
        $this->assertSame(DisputeStatus::Refunded, $fresh->status);
        $this->assertSame('partial_refund', $fresh->resolution_type);
        $this->assertSame(200000, $fresh->resolution_amount);
        $this->assertNotNull($fresh->resolved_at);
        $this->assertSame($seller->id, (int) $fresh->resolved_by);

        $this->assertNotNull($resolution->fresh()->executed_at);
        $this->assertSame(OrderStatus::PartiallyRefunded, $dispute->order->fresh()->status);
        $this->assertTrue((bool) $dispute->order->fresh()->earning_released);
    }

    public function test_accepting_a_full_refund_returns_everything_and_reverses_the_hold(): void
    {
        $dispute    = $this->disputedOrder();
        $buyer      = $dispute->order->buyer;
        $seller     = $dispute->order->seller;
        $resolution = app(DisputeService::class)->propose(
            $dispute, $seller, DisputeResolutionType::FullRefund, null, 'Sorry — take it all back.',
        );

        $this->actingAs($buyer)
            ->post("/dashboard/dispute-proposals/{$resolution->id}/accept")
            ->assertRedirect()
            ->assertSessionHas('success');

        // The whole ৳5,000.00 back to the buyer, and the seller's hold reversed
        // rather than paid out — their available balance never moves.
        $this->assertSame([self::BUYER_TOTAL, 0], $this->balances($buyer));
        $this->assertSame([0, 0], $this->balances($seller));

        $fresh = $dispute->fresh();
        $this->assertSame(DisputeStatus::Refunded, $fresh->status);
        $this->assertSame('full_refund', $fresh->resolution_type);
        $this->assertSame(self::BUYER_TOTAL, $fresh->resolution_amount);
        $this->assertSame(OrderStatus::Refunded, $dispute->order->fresh()->status);
        $this->assertFalse((bool) $dispute->order->fresh()->earning_released);
    }

    public function test_accepting_a_replacement_moves_no_money_and_reopens_delivery(): void
    {
        $dispute    = $this->disputedOrder();
        $buyer      = $dispute->order->buyer;
        $seller     = $dispute->order->seller;
        $resolution = app(DisputeService::class)->propose(
            $dispute, $seller, DisputeResolutionType::Replacement, null, 'I will re-deliver tonight.',
        );

        $this->actingAs($buyer)
            ->post("/dashboard/dispute-proposals/{$resolution->id}/accept")
            ->assertRedirect();

        // Nothing moved: the earning stays held until the re-delivery completes.
        $this->assertSame([0, 0], $this->balances($buyer));
        $this->assertSame([0, self::SELLER_EARNING], $this->balances($seller));

        $fresh = $dispute->fresh();
        $this->assertSame(DisputeStatus::Cancelled, $fresh->status);
        $this->assertSame('replacement', $fresh->resolution_type);
        $this->assertNull($fresh->resolution_amount);

        $order = $dispute->order->fresh();
        $this->assertSame(OrderStatus::DeliveryPending, $order->status);
        $this->assertSame('not_started', $order->delivery_status);
        $this->assertNull($order->delivered_at);
    }

    public function test_a_replayed_accept_moves_the_money_only_once(): void
    {
        $dispute    = $this->disputedOrder();
        $buyer      = $dispute->order->buyer;
        $seller     = $dispute->order->seller;
        $resolution = app(DisputeService::class)->propose(
            $dispute, $buyer, DisputeResolutionType::PartialRefund, 200000, '',
        );

        $this->actingAs($seller)->post("/dashboard/dispute-proposals/{$resolution->id}/accept")->assertRedirect();
        // The second submit finds the proposal no longer pending and is refused
        // before it can reach the wallet.
        $this->actingAs($seller)->post("/dashboard/dispute-proposals/{$resolution->id}/accept")->assertForbidden();

        $this->assertSame([200000, 0], $this->balances($buyer));
        $this->assertSame([270000, 0], $this->balances($seller));
    }

    public function test_a_settled_dispute_cannot_be_settled_again(): void
    {
        $dispute = $this->disputedOrder();
        $service = app(DisputeService::class);
        $first   = $service->propose($dispute, $dispute->order->buyer, DisputeResolutionType::PartialRefund, 200000, '');

        $this->actingAs($dispute->order->seller)
            ->post("/dashboard/dispute-proposals/{$first->id}/accept")
            ->assertRedirect();

        // A new proposal on a dispute that is already closed is refused outright.
        $this->actingAs($dispute->order->buyer)
            ->post("/dashboard/disputes/{$dispute->id}/proposals", [
                'type' => 'partial_refund', 'amount_bdt' => 3000,
            ])
            ->assertForbidden();

        $this->assertSame(200000, $dispute->fresh()->resolution_amount);
    }

    // ── Escalation ───────────────────────────────────────────────────

    public function test_either_party_can_hand_the_dispute_to_an_admin(): void
    {
        $dispute = $this->disputedOrder();

        $this->actingAs($dispute->order->seller)
            ->post("/dashboard/disputes/{$dispute->id}/escalate", ['note' => 'We cannot agree.'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $dispute->fresh();
        $this->assertSame(DisputeStatus::Escalated, $fresh->status);
        $this->assertNotNull($fresh->escalated_at);
        $this->assertSame('escalated', $dispute->order->fresh()->dispute_status);
        $this->assertTrue($fresh->isActive());
    }

    public function test_escalation_outranks_a_later_proposal(): void
    {
        $dispute = $this->disputedOrder();
        $service = app(DisputeService::class);

        $service->escalate($dispute, $dispute->order->buyer, 'Please step in.');
        $service->propose($dispute, $dispute->order->seller, DisputeResolutionType::PartialRefund, 100000, '');

        // The proposal is recorded, but it cannot quietly take the dispute back
        // out of an admin's hands.
        $this->assertSame(DisputeStatus::Escalated, $dispute->fresh()->status);
        $this->assertNotNull($dispute->fresh()->pendingResolution());
    }

    public function test_escalating_twice_is_a_no_op(): void
    {
        $dispute = $this->disputedOrder();

        $this->actingAs($dispute->order->buyer)->post("/dashboard/disputes/{$dispute->id}/escalate");
        $escalatedAt = $dispute->fresh()->escalated_at;

        // The button is gone from the page, and the route refuses a second one.
        $this->actingAs($dispute->order->buyer)
            ->get("/dashboard/disputes/{$dispute->id}")
            ->assertInertia(fn (Assert $page) => $page->where('can.escalate', false)->etc());

        $this->actingAs($dispute->order->seller)
            ->post("/dashboard/disputes/{$dispute->id}/escalate")
            ->assertForbidden();

        $this->assertEquals($escalatedAt, $dispute->fresh()->escalated_at);
    }

    // ── Withdrawal ───────────────────────────────────────────────────

    public function test_the_buyer_can_drop_their_claim_and_the_order_goes_back(): void
    {
        $dispute = $this->disputedOrder();
        $buyer   = $dispute->order->buyer;

        $this->actingAs($buyer)
            ->post("/dashboard/disputes/{$dispute->id}/cancel", ['note' => 'The seller fixed it.'])
            ->assertRedirect("/dashboard/disputes/{$dispute->id}")
            ->assertSessionHas('success');

        $fresh = $dispute->fresh();
        $this->assertSame(DisputeStatus::Cancelled, $fresh->status);
        $this->assertSame('cancelled', $fresh->resolution);
        $this->assertFalse($fresh->isActive());

        // The order returns to the status the dispute displaced — read from its
        // own history, not assumed to be Delivered.
        $this->assertSame(OrderStatus::Delivered, $dispute->order->fresh()->status);
        $this->assertSame('cancelled', $dispute->order->fresh()->dispute_status);

        // No money moved either way.
        $this->assertSame([0, 0], $this->balances($buyer));
        $this->assertSame([0, self::SELLER_EARNING], $this->balances($dispute->order->seller));
    }

    public function test_a_dropped_claim_restores_the_status_the_dispute_displaced(): void
    {
        // Opened before delivery, so restoring must not land on Delivered.
        $dispute = $this->disputedOrder(OrderStatus::DeliveryPending);

        app(DisputeService::class)->cancel($dispute, $dispute->order->buyer, '');

        $this->assertSame(OrderStatus::DeliveryPending, $dispute->order->fresh()->status);
    }

    public function test_dropping_a_claim_frees_the_order_for_a_later_dispute(): void
    {
        $dispute = $this->disputedOrder();
        $order   = $dispute->order;

        app(DisputeService::class)->cancel($dispute, $order->buyer, 'Withdrawn by mistake.');

        $this->actingAs($order->buyer)
            ->post("/dashboard/orders/{$order->id}/dispute", [
                'reason_code' => 'access_problem',
                'description' => 'It broke again a week later, so I am raising this properly.',
            ])
            ->assertRedirect();

        $this->assertSame(2, Dispute::count());
        $this->assertSame(DisputeStatus::Open, $order->fresh()->dispute->status);
    }
}
