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
use App\Policies\DisputePolicy;
use App\Services\DisputeService;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
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

    /**
     * Holds the seeded `admin` role, which Gate::before grants everything —
     * needed for the actions that authorize `disputes.manage` rather than only
     * passing the admin middleware.
     */
    private function makeSuperAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'admin')->value('id'));

        return $user;
    }

    /**
     * A user holding one of the *seeded* staff roles.
     *
     * makeSuperAdmin() attaches the role literally named `admin`, which
     * Gate::before blanket-allows — so it cannot tell whether an action is
     * authorized on its own merits. `super_admin` and `support` do not trigger
     * Gate::before, which is what the real deployment looks like.
     */
    private function asRole(string $roleName): User
    {
        $user = User::factory()->create();
        $id   = Role::where('name', $roleName)->value('id');
        $this->assertNotNull($id, "the {$roleName} role is not seeded");
        $user->roles()->attach($id);

        return $user->fresh();
    }

    /**
     * The headers Inertia sends for the partial reload the page polls with.
     *
     * The version has to match what the middleware will compute for this request —
     * Inertia answers a stale asset version with a 409 telling the browser to
     * hard-reload rather than with the JSON payload. Asking the middleware itself
     * keeps this correct whether or not a Vite manifest has been built, which
     * matters because CI runs the suite without one.
     */
    private function partialReload(Dispute $dispute, array $only): \Illuminate\Testing\TestResponse
    {
        $version = (string) (new \App\Http\Middleware\HandleInertiaRequests())
            ->version(request());

        return $this->get("/dashboard/disputes/{$dispute->id}", [
            'X-Inertia'                   => 'true',
            'X-Inertia-Version'           => $version,
            'X-Inertia-Partial-Component' => 'Dashboard/Disputes/Show',
            'X-Inertia-Partial-Data'      => implode(',', $only),
        ]);
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

    // ── Legacy data and status casting ───────────────────────────────

    /**
     * The rebuild migration is the only thing standing between a legacy row and an
     * enum error, so its mapping must be total: every status the OLD enum could
     * store has to land on a case the NEW one defines.
     *
     * `waiting_for_seller` is the value that surfaced in the browser as
     * "not a valid backing value for enum App\Enums\DisputeStatus" — it is an
     * obsolete state, not a gap in the new lifecycle, and maps onto Open because
     * the new flow tracks the seller's reply separately via SellerResponded.
     */
    public function test_every_legacy_status_maps_onto_a_valid_new_status(): void
    {
        $migration = require database_path('migrations/2026_08_19_110000_rebuild_dispute_system.php');
        $constants = (new \ReflectionClass($migration))->getConstants();

        $statusMap   = $constants['STATUS_MAP'];
        $resolvedMap = $constants['RESOLVED_MAP'];

        // Every case the old enum defined, from its final revision on main.
        $legacy = ['open', 'under_review', 'waiting_for_buyer', 'waiting_for_seller',
                   'resolved', 'rejected', 'closed'];

        foreach ($legacy as $old) {
            // 'resolved' is deliberately absent from STATUS_MAP: the outcome it
            // stood for is recorded on resolution_type, so it maps through there.
            $covered = $old === 'resolved'
                ? $resolvedMap !== []
                : array_key_exists($old, $statusMap);

            $this->assertTrue($covered, "Legacy status '{$old}' has no mapping — a row holding it would break the enum cast.");
        }

        foreach (array_merge(array_values($statusMap), array_values($resolvedMap)) as $target) {
            $this->assertNotNull(
                DisputeStatus::tryFrom($target),
                "Legacy mapping points at '{$target}', which is not a DisputeStatus case.",
            );
        }

        // The specific value that broke the browser.
        $this->assertSame(DisputeStatus::Open->value, $statusMap['waiting_for_seller']);
    }

    public function test_every_status_round_trips_through_the_model_cast(): void
    {
        $dispute = $this->disputedOrder();

        foreach (DisputeStatus::cases() as $case) {
            $dispute->update(['status' => $case]);

            $fresh = $dispute->fresh();
            $this->assertInstanceOf(DisputeStatus::class, $fresh->status);
            $this->assertSame($case, $fresh->status);
            // label() is exhaustive over the enum, so a missing arm throws here.
            $this->assertNotSame('', $fresh->status->label());
        }
    }

    // ── The pages that were erroring ─────────────────────────────────

    /**
     * GET /dashboard/orders/{order} is where the enum error surfaced: show() reads
     * $order->dispute->status->value for the banner that links to the thread.
     */
    public function test_the_order_page_loads_for_an_order_under_dispute(): void
    {
        $dispute = $this->disputedOrder();
        $order   = $dispute->order;

        $this->actingAs($order->buyer)
            ->get("/dashboard/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Orders/Show')
                ->where('dispute.reference', $dispute->displayReference())
                ->where('dispute.status', 'open')
                ->where('dispute.is_active', true)
                ->etc()
            );

        // The seller's view of the same order renders too.
        $this->actingAs($order->seller)
            ->get("/dashboard/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dispute.status', 'open')
                ->etc()
            );
    }

    public function test_the_order_page_still_loads_when_there_is_no_dispute(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($order->buyer)
            ->get("/dashboard/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('dispute', null)->etc());
    }

    /**
     * The admin queue orders on last_activity_at — the column whose absence
     * produced "Unknown column 'last_activity_at' in 'order clause'". A dispute
     * waiting on staff must not sink below one merely opened later.
     */
    public function test_the_admin_queue_orders_by_latest_activity(): void
    {
        $stale  = $this->disputedOrder();
        $middle = $this->disputedOrder();
        $active = $this->disputedOrder();

        // Opened in ascending id order; activity deliberately the reverse.
        $stale->update(['last_activity_at' => now()->subDays(5)]);
        $middle->update(['last_activity_at' => now()->subDay()]);
        $active->update(['last_activity_at' => now()]);

        $this->actingAs($this->makeAdmin())
            ->get('/admin/disputes?status=open')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Disputes/Index')
                ->has('disputes.data', 3)
                ->where('disputes.data.0.reference', $active->displayReference())
                ->where('disputes.data.1.reference', $middle->displayReference())
                ->where('disputes.data.2.reference', $stale->displayReference())
            );
    }

    public function test_posting_to_the_thread_moves_a_dispute_up_the_queue(): void
    {
        $first  = $this->disputedOrder();
        $second = $this->disputedOrder();

        $first->update(['last_activity_at' => now()->subDays(3)]);
        $second->update(['last_activity_at' => now()->subDay()]);

        // The older dispute gets a reply, so it should overtake.
        $this->actingAs($first->order->seller)
            ->post("/dashboard/disputes/{$first->id}/messages", ['body' => 'Looking into this now.'])
            ->assertRedirect();

        $this->actingAs($this->makeAdmin())
            ->get('/admin/disputes?status=all')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('disputes.data.0.reference', $first->displayReference())
                ->etc()
            );

        $this->assertTrue($first->fresh()->last_activity_at->gt($second->fresh()->last_activity_at));
    }

    // ── Staff are not participants in the party thread ───────────────

    /**
     * An admin communicates on the record, not as a peer. Their notice is a System
     * row — isFromParticipant() is false — so the Vue pages can render it as an
     * administrative notice and it never reads as a third voice in the chat.
     */
    public function test_an_admin_notice_is_not_participant_chat(): void
    {
        $dispute = $this->disputedOrder();

        $this->actingAs($this->makeSuperAdmin())
            ->post("/admin/disputes/{$dispute->id}/message", ['body' => 'Both sides please upload your handover logs.'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $notice = $dispute->messages()->where('role', 'admin')->latest('id')->firstOrFail();
        $this->assertSame(DisputeMessageType::System, $notice->type);
        $this->assertFalse($notice->type->isFromParticipant());
        $this->assertFalse((bool) $notice->is_internal);   // both parties can read it
        $this->assertTrue($notice->isSystem());

        // No Text row was written on the admin's behalf.
        $this->assertSame(0, $dispute->messages()
            ->where('type', DisputeMessageType::Text->value)->count());

        // The parties see it, marked as a system event rather than someone's reply.
        $this->actingAs($dispute->order->buyer)
            ->get("/dashboard/disputes/{$dispute->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('thread.1.is_system', true)
                ->where('thread.1.is_mine', false)
                ->etc()
            );
    }

    public function test_an_admin_cannot_speak_as_a_party_in_the_thread(): void
    {
        $dispute = $this->disputedOrder();
        $admin   = $this->makeSuperAdmin();

        // The service is the last line: a Text row from staff is refused outright.
        $this->expectException(HttpException::class);
        try {
            app(DisputeService::class)->postMessage($dispute, $admin, 'Just chiming in as a peer.');
        } finally {
            $this->assertSame(0, $dispute->messages()
                ->where('type', DisputeMessageType::Text->value)->count());
        }
    }

    public function test_the_dashboard_thread_never_offers_an_admin_the_composer(): void
    {
        $dispute = $this->disputedOrder();
        $admin   = $this->makeSuperAdmin();

        // Gate::before grants an admin everything, so the policy is asserted
        // directly rather than through the page's `can` flags.
        $this->assertFalse((new DisputePolicy())->message($admin, $dispute));
        // Evidence is a record rather than speech, so that stays open to staff.
        $this->assertTrue((new DisputePolicy())->addEvidence($admin, $dispute));
        // And the parties keep their composer.
        $this->assertTrue((new DisputePolicy())->message($dispute->order->buyer, $dispute));
        $this->assertTrue((new DisputePolicy())->message($dispute->order->seller, $dispute));
    }

    /**
     * Admin decisions stay separate from party speech: deciding writes an
     * AdminDecision row, which is not participant chat either.
     */
    public function test_an_admin_decision_is_recorded_apart_from_party_messages(): void
    {
        $dispute = $this->disputedOrder();
        $buyer   = $dispute->order->buyer;

        $this->actingAs($buyer)
            ->post("/dashboard/disputes/{$dispute->id}/messages", ['body' => 'Still broken, please refund.'])
            ->assertRedirect();

        $this->actingAs($this->makeSuperAdmin())
            ->post("/admin/disputes/{$dispute->id}/full-refund", ['note' => 'Buyer evidence is conclusive.'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(DisputeStatus::ResolvedBuyer, $dispute->fresh()->status);

        // One party message; the decision is its own kind of row.
        $this->assertSame(1, $dispute->messages()->where('type', DisputeMessageType::Text->value)->count());
        $decision = $dispute->messages()->where('type', DisputeMessageType::AdminDecision->value)->firstOrFail();
        $this->assertFalse($decision->type->isFromParticipant());
        $this->assertSame('admin', $decision->role);
    }

    // ── Staff identity vs. the seat they occupy ──────────────────────

    /**
     * The bug behind "Access Denied": Dispute::roleOf() resolves a party before
     * staff, so a staff member who is also the buyer came back 'buyer' and every
     * guard written as `roleOf() === 'admin'` refused them — on their own dispute,
     * with the disputes.manage permission in hand.
     *
     * A single-operator or staging deployment routinely has exactly this shape.
     */
    public function test_staff_who_are_also_the_buyer_can_still_act_as_staff(): void
    {
        $dispute = $this->disputedOrder();
        $buyer   = $dispute->order->buyer;

        // The buyer is promoted to super_admin — the real deployment's setup.
        $buyer->roles()->attach(Role::where('name', 'super_admin')->value('id'));
        $buyer = $buyer->fresh();

        // Their seat in the thread is still 'buyer' — that part was never wrong.
        $this->assertSame('buyer', $dispute->roleOf($buyer));
        // …but they hold the staff capability, which is a separate question.
        $this->assertTrue($dispute->isStaff($buyer));

        // The three actions that used to 403.
        $this->actingAs($buyer)
            ->post("/admin/disputes/{$dispute->id}/message", ['body' => 'Staff notice while also the buyer.'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($buyer)
            ->post("/admin/disputes/{$dispute->id}/internal-note", ['body' => 'Internal note from staff-buyer.'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($buyer)
            ->post("/admin/disputes/{$dispute->id}/release-seller", ['note' => 'Claim not substantiated.'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(DisputeStatus::ResolvedSeller, $dispute->fresh()->status);
    }

    /**
     * A staff member deciding a dispute they are a party to is a conflict of
     * interest. It is allowed — see above for why — but it is recorded, so it can
     * be found later rather than being invisible.
     */
    public function test_a_decision_by_a_party_is_flagged_in_the_audit_log(): void
    {
        $dispute = $this->disputedOrder();
        $buyer   = $dispute->order->buyer;
        $buyer->roles()->attach(Role::where('name', 'super_admin')->value('id'));

        $this->actingAs($buyer->fresh())
            ->post("/admin/disputes/{$dispute->id}/full-refund", ['note' => 'Refunding my own order.'])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['action' => 'dispute.decided_by_party']);
    }

    /**
     * The seeded staff roles differ in what they may do, and that must come from
     * the permission model rather than from the role's name.
     */
    public function test_staff_authorization_follows_the_permission_model(): void
    {
        $dispute = $this->disputedOrder();

        // super_admin and support both carry disputes.manage; neither is named
        // 'admin', so neither is short-circuited by Gate::before.
        foreach (['super_admin', 'support'] as $roleName) {
            $staff = $this->asRole($roleName);
            $this->assertTrue($staff->hasPermission('disputes.manage'), "{$roleName} should hold disputes.manage");

            $this->actingAs($staff)
                ->post("/admin/disputes/{$dispute->id}/internal-note", ['body' => "Note from {$roleName}."])
                ->assertRedirect()
                ->assertSessionHas('success');
        }

        // moderator is an admin-type role WITHOUT disputes.manage, so it is
        // refused — authorization is still enforced, not blanket-granted.
        $moderator = $this->asRole('moderator');
        $this->assertFalse($moderator->hasPermission('disputes.manage'));
        $this->assertTrue($moderator->isAdmin());

        $this->actingAs($moderator)
            ->post("/admin/disputes/{$dispute->id}/internal-note", ['body' => 'Should not land.'])
            ->assertForbidden();

        $this->actingAs($moderator)
            ->post("/admin/disputes/{$dispute->id}/full-refund", ['note' => 'Should not land.'])
            ->assertForbidden();
    }

    // ── Parties cannot forge staff rows ──────────────────────────────

    public function test_a_buyer_cannot_post_an_internal_note(): void
    {
        $dispute = $this->disputedOrder();

        // The admin endpoint is closed to them…
        $this->actingAs($dispute->order->buyer)
            ->post("/admin/disputes/{$dispute->id}/internal-note", ['body' => 'Pretending to be staff.'])
            ->assertForbidden();

        // …and so is the service, whatever the caller passes.
        try {
            app(DisputeService::class)->postMessage(
                $dispute, $dispute->order->buyer, 'Forged internal note.', null, true,
            );
            $this->fail('A buyer was allowed to write an internal note.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertSame(0, $dispute->internalNotes()->count());
    }

    public function test_a_seller_cannot_post_an_internal_note(): void
    {
        $dispute = $this->disputedOrder();

        $this->actingAs($dispute->order->seller)
            ->post("/admin/disputes/{$dispute->id}/internal-note", ['body' => 'Pretending to be staff.'])
            ->assertForbidden();

        try {
            app(DisputeService::class)->postMessage(
                $dispute, $dispute->order->seller, 'Forged internal note.', null, true,
            );
            $this->fail('A seller was allowed to write an internal note.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertSame(0, $dispute->internalNotes()->count());
    }

    public function test_a_party_cannot_post_an_administrative_notice(): void
    {
        $dispute = $this->disputedOrder();

        foreach ([$dispute->order->buyer, $dispute->order->seller] as $party) {
            $this->actingAs($party)
                ->post("/admin/disputes/{$dispute->id}/message", ['body' => 'Official-looking notice.'])
                ->assertForbidden();

            try {
                app(DisputeService::class)->announce($dispute, $party, 'Official-looking notice.');
                $this->fail('A party was allowed to announce as staff.');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }

        $this->assertSame(0, $dispute->messages()->where('role', 'admin')->count());
    }

    // ── Cross-dispute isolation ──────────────────────────────────────

    public function test_a_buyer_cannot_reach_another_disputes_thread(): void
    {
        $mine   = $this->disputedOrder();
        $theirs = $this->disputedOrder();

        $this->actingAs($mine->order->buyer)
            ->get("/dashboard/disputes/{$theirs->id}")
            ->assertForbidden();

        $this->actingAs($mine->order->buyer)
            ->post("/dashboard/disputes/{$theirs->id}/messages", ['body' => 'Butting in.'])
            ->assertForbidden();

        $this->assertSame(1, $theirs->messages()->count()); // just the opening line
    }

    public function test_a_seller_cannot_reach_another_sellers_dispute(): void
    {
        $mine   = $this->disputedOrder();
        $theirs = $this->disputedOrder();

        $this->actingAs($mine->order->seller)
            ->get("/dashboard/disputes/{$theirs->id}")
            ->assertForbidden();

        $this->actingAs($mine->order->seller)
            ->post("/dashboard/disputes/{$theirs->id}/messages", ['body' => 'Butting in.'])
            ->assertForbidden();
    }

    // ── The live thread ──────────────────────────────────────────────

    /**
     * The page is told whether a broadcast driver exists. With none configured it
     * polls instead, so this flag is what turns that on.
     */
    public function test_the_thread_reports_whether_broadcasting_is_configured(): void
    {
        $dispute = $this->disputedOrder();

        config()->set('broadcasting.default', 'null');
        $this->actingAs($dispute->order->buyer)
            ->get("/dashboard/disputes/{$dispute->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('isRealtimeReady', false)->etc());

        config()->set('broadcasting.default', 'reverb');
        $this->actingAs($dispute->order->buyer)
            ->get("/dashboard/disputes/{$dispute->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('isRealtimeReady', true)->etc());
    }

    /**
     * The buyer has the thread open; the seller replies. The buyer's next poll
     * carries the new message — no full page load, and only the props the page
     * asked for come back.
     */
    public function test_a_reply_reaches_the_other_party_without_a_page_reload(): void
    {
        $dispute = $this->disputedOrder();
        $buyer   = $dispute->order->buyer;
        $seller  = $dispute->order->seller;

        // What the buyer has on screen right now.
        $this->actingAs($buyer)
            ->get("/dashboard/disputes/{$dispute->id}")
            ->assertInertia(fn (Assert $page) => $page->has('thread', 1)->etc());

        $this->actingAs($seller)
            ->post("/dashboard/disputes/{$dispute->id}/messages", ['body' => 'Hi — looking at it now.'])
            ->assertRedirect();

        // The poll the open page performs.
        $response = $this->actingAs($buyer)->partialReload($dispute, ['dispute', 'thread', 'pending', 'history', 'can']);
        $response->assertOk();

        $payload = $response->json('props');
        $this->assertCount(2, $payload['thread']);
        $this->assertSame('Hi — looking at it now.', $payload['thread'][1]['body']);
        $this->assertFalse($payload['thread'][1]['is_mine']);   // it is the seller's
        // A partial reload, not a whole page: untouched props are not resent.
        $this->assertArrayNotHasKey('order', $payload);
        $this->assertArrayNotHasKey('options', $payload);
        // Ordering is preserved — oldest first, the opening system line still #1.
        $this->assertTrue($payload['thread'][0]['is_system']);
    }

    public function test_the_poll_preserves_ordering_and_does_not_duplicate_rows(): void
    {
        $dispute = $this->disputedOrder();
        $buyer   = $dispute->order->buyer;
        $seller  = $dispute->order->seller;

        $this->actingAs($buyer)->post("/dashboard/disputes/{$dispute->id}/messages", ['body' => 'First.']);
        $this->actingAs($seller)->post("/dashboard/disputes/{$dispute->id}/messages", ['body' => 'Second.']);
        $this->actingAs($buyer)->post("/dashboard/disputes/{$dispute->id}/messages", ['body' => 'Third.']);

        // Polling repeatedly must not create or duplicate anything.
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($buyer)->partialReload($dispute, ['thread'])->assertOk();
        }

        $thread = $this->actingAs($buyer)->partialReload($dispute, ['thread'])->json('props.thread');

        // Oldest first: the opening system line, then the three replies in the
        // order they were sent, each exactly once.
        $this->assertCount(4, $thread);
        $this->assertTrue($thread[0]['is_system']);
        $this->assertSame(
            ['First.', 'Second.', 'Third.'],
            array_map(fn (array $m) => $m['body'], array_slice($thread, 1)),
        );
        $this->assertSame(4, $dispute->messages()->count());
    }

    /**
     * The poll is the only data path, and it re-authorizes every time — so there
     * is no channel an outsider could subscribe to instead.
     */
    public function test_an_outsider_cannot_poll_a_dispute_they_cannot_view(): void
    {
        $dispute  = $this->disputedOrder();
        $stranger = $this->buyer();

        $this->actingAs($stranger)
            ->partialReload($dispute, ['dispute', 'thread'])
            ->assertForbidden();

        // Another dispute's party is just as much an outsider here.
        $other = $this->disputedOrder();
        $this->actingAs($other->order->seller)
            ->partialReload($dispute, ['thread'])
            ->assertForbidden();
    }

    /** A poll must never hand a party someone else's internal notes. */
    public function test_polling_never_leaks_internal_notes_to_a_party(): void
    {
        $dispute = $this->disputedOrder();
        app(DisputeService::class)->addInternalNote($dispute, $this->makeSuperAdmin(), 'Staff eyes only.');

        $thread = $this->actingAs($dispute->order->buyer)
            ->partialReload($dispute, ['thread'])
            ->json('props.thread');

        $this->assertCount(1, $thread);                      // the opening line only
        foreach ($thread as $row) {
            $this->assertFalse($row['is_internal']);
        }
        $this->assertNotContains('Staff eyes only.', array_column($thread, 'body'));
    }

    /** An admin notice, unlike an internal note, is meant to reach both parties. */
    public function test_polling_delivers_an_admin_notice_to_both_parties(): void
    {
        $dispute = $this->disputedOrder();

        $this->actingAs($this->asRole('super_admin'))
            ->post("/admin/disputes/{$dispute->id}/message", ['body' => 'Please both upload your logs.'])
            ->assertRedirect();

        foreach ([$dispute->order->buyer, $dispute->order->seller] as $party) {
            $thread = $this->actingAs($party)->partialReload($dispute, ['thread'])->json('props.thread');

            $bodies = array_column($thread, 'body');
            $this->assertContains('Please both upload your logs.', $bodies);

            $notice = collect($thread)->firstWhere('body', 'Please both upload your logs.');
            // Reaches them as a system notice, never as a party's chat message.
            $this->assertTrue($notice['is_system']);
            $this->assertFalse($notice['is_internal']);
            $this->assertSame('admin', $notice['role']);
        }
    }
}
