<?php
namespace App\Services;

use App\Enums\BidStatus;
use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Models\Asset;
use App\Models\Bid;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        private readonly FeeCalculator      $fees,
        private readonly SettingsService    $settings,
        private readonly UddoktaPayService  $gateway,
        private readonly AuditLogger        $audit,
        private readonly WalletService      $walletSvc,
        private readonly BidService         $bids,
    ) {}

    /**
     * Validate purchase eligibility and calculate fees.
     * Returns the fee snapshot array (server-side, never from browser).
     *
     * A purchase can arrive three ways, and the unit price comes from whichever
     * one it is — never from the request:
     *   plain Buy Now      → the listing price
     *   accepted offer     → the offer amount (buyer only)
     *   accepted bid       → the bid amount (the winning bidder only)
     */
    public function validateAndCalculate(Asset $asset, int $quantity, User $buyer, ?Offer $offer = null, ?Bid $bid = null): array
    {
        // Self-purchase prevention
        abort_if($buyer->id === $asset->user_id, 403, 'You cannot purchase your own listing.');
        abort_unless($buyer->canTransact(), 403, 'Your account is not in good standing.');
        abort_if($quantity < 1, 422, 'Quantity must be at least 1.');

        if ($bid) {
            // A listing sitting at "Bid Accepted" is closed to everyone except
            // the winning bidder, who is the one being sent here to pay.
            abort_unless((int) $bid->bidder_user_id === $buyer->id, 403, 'This bid is not yours.');
            abort_unless($bid->status === BidStatus::Accepted, 422, 'This bid has not been accepted.');
            abort_unless((int) $bid->asset_id === (int) $asset->id, 422, 'This bid is for a different listing.');
            abort_unless(
                in_array($asset->status->value, ['published', 'bid_accepted'], true),
                422,
                'This listing is not available for purchase.'
            );
        } else {
            abort_unless($asset->status->value === 'published', 422, 'This listing is not available for purchase.');
        }

        abort_if($asset->isSoldOut(), 422, 'This listing is sold out.');

        // Unlimited stock is never short.
        if ($asset->inventoryType()->consumesInventory()) {
            abort_if($asset->available_quantity < $quantity, 422, "Only {$asset->available_quantity} unit(s) available.");
        }

        $unitPrice = match (true) {
            $bid !== null   => (int) $bid->amount,
            $offer !== null => (int) $offer->amount,
            default         => (int) $asset->price,
        };

        return $this->fees->forOrder($unitPrice, $quantity);
    }

    /**
     * Initiate payment: create pending order + payment, get gateway URL.
     * Order is NOT yet confirmed/paid.
     */
    public function initiate(Asset $asset, int $quantity, User $buyer, ?Offer $offer = null, ?Bid $bid = null): array
    {
        $feeSnap = $this->validateAndCalculate($asset, $quantity, $buyer, $offer, $bid);

        return DB::transaction(function () use ($asset, $quantity, $buyer, $offer, $bid, $feeSnap) {
            $unitPrice = match (true) {
                $bid !== null   => (int) $bid->amount,
                $offer !== null => (int) $offer->amount,
                default         => (int) $asset->price,
            };

            $order = Order::create([
                'reference'          => 'REF-' . strtoupper(Str::random(12)),
                'order_number'       => $this->generateOrderNumber(),
                'buyer_user_id'      => $buyer->id,
                'seller_user_id'     => $asset->user_id,
                'asset_id'           => $asset->id,
                'offer_id'           => $offer?->id,
                'bid_id'             => $bid?->id,
                'quantity'           => $quantity,
                'unit_price'         => $unitPrice,
                'subtotal'           => $feeSnap['subtotal'],
                'seller_fee_bp'      => $feeSnap['seller_fee_bp'],
                'seller_fee_amount'  => $feeSnap['seller_fee_amount'],
                'buyer_fee_enabled'  => $feeSnap['buyer_fee_enabled'],
                'buyer_fee_type'     => $feeSnap['buyer_fee_type'],
                'buyer_fee_bp'       => $feeSnap['buyer_fee_bp'],
                'buyer_fee_amount'   => $feeSnap['buyer_fee_amount'],
                'platform_commission'=> $feeSnap['platform_commission'],
                'buyer_total'        => $feeSnap['buyer_total'],
                'seller_earning'     => $feeSnap['seller_earning'],
                'currency'           => 'BDT',
                'status'             => OrderStatus::PendingPayment,
                'payment_status'     => 'pending',
                'delivery_status'    => 'not_started',
                'payment_gateway'    => 'uddoktapay',
                'auto_complete_at'   => null, // set after payment
            ]);

            $payment = Payment::create([
                'order_id' => $order->id,
                'gateway'  => 'uddoktapay',
                'amount'   => $order->buyer_total,
                'currency' => 'BDT',
                'status'   => 'pending',
            ]);

            $this->recordHistory($order, null, 'pending_payment', null, 'Order initiated, awaiting payment');

            $checkoutUrl = $this->gateway->initiate($order, $payment);

            return compact('order', 'payment', 'checkoutUrl');
        });
    }

    /**
     * Handle verified successful payment (called from callback/webhook).
     * IDEMPOTENT — safe to call multiple times for the same gateway transaction.
     */
    public function confirmPayment(string $invoiceId, string $gatewayTransactionId, array $metadata): Order
    {
        $orderId = $metadata['order_id'] ?? null;
        abort_unless($orderId, 422, 'Invalid payment metadata.');

        return DB::transaction(function () use ($orderId, $invoiceId, $gatewayTransactionId) {
            // Lock the order row
            $order = Order::where('id', $orderId)
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotency: if already paid, return without re-processing
            if ($order->payment_status === 'paid') {
                return $order;
            }

            abort_unless($order->payment_status === 'pending', 422, 'Order is not in a payable state.');

            // Lock the asset row and re-verify quantity
            $asset = Asset::where('id', $order->asset_id)->lockForUpdate()->firstOrFail();

            // A listing held at "Bid Accepted" is payable only by the order that
            // carries the winning bid — that is what took it off the market.
            $viaAcceptedBid = $order->bid_id !== null
                && (int) $asset->accepted_bid_id === (int) $order->bid_id;

            abort_unless(
                $asset->status->value === 'published' || ($viaAcceptedBid && $asset->status->value === 'bid_accepted'),
                422,
                'Listing is no longer available.'
            );

            $consumesInventory = $asset->inventoryType()->consumesInventory();

            if ($consumesInventory) {
                abort_if($asset->available_quantity < $order->quantity, 422, 'Insufficient quantity.');

                // Atomically decrement quantity
                $asset->decrement('available_quantity', $order->quantity);
                $asset->increment('sold_quantity', $order->quantity);

                // Mark sold out if depleted
                if ($asset->available_quantity <= 0) {
                    $asset->update(['status' => 'sold_out']);
                    // Nobody can pay for it now, so open bids are done with.
                    $this->bids->expireOpenBids((int) $asset->id, $order->bid_id ? (int) $order->bid_id : null);
                } elseif ($viaAcceptedBid) {
                    // Stock left over on a bid sale: back on the market.
                    $asset->update(['status' => 'published', 'accepted_bid_id' => null]);
                }
            } else {
                // Unlimited stock: one listing can generate any number of orders
                // and stays Active. Only the sold counter moves.
                $asset->increment('sold_quantity', $order->quantity);
            }

            // Update order status
            $buyerProtHours = $this->settings->buyerProtectionHours();
            $order->update([
                'status'                 => OrderStatus::DeliveryPending,
                'payment_status'         => 'paid',
                'payment_reference'      => $gatewayTransactionId,
                'paid_at'                => now(),
                'auto_complete_at'       => now()->addHours($buyerProtHours),
            ]);

            // Update payment record (idempotent via gateway_payment_id unique)
            Payment::where('order_id', $order->id)->where('status', 'pending')->update([
                'gateway_payment_id'    => $invoiceId,
                'gateway_transaction_id'=> $gatewayTransactionId,
                'status'                => 'paid',
                'paid_at'               => now(),
            ]);

            // A custom offer that has been paid for is done negotiating.
            if ($order->offer_id) {
                Offer::where('id', $order->offer_id)->update([
                    'status'  => OfferStatus::Paid->value,
                    'paid_at' => now(),
                ]);
            }

            // Private order conversation. If the pair already has a thread about
            // this listing — they used Contact Seller — the order joins it rather
            // than opening a second one they would have to switch between.
            $conv = Conversation::query()
                ->whereNull('order_id')
                ->where('asset_id', $asset->id)
                ->whereHas('participants', fn ($q) => $q->where('users.id', $order->buyer_user_id))
                ->whereHas('participants', fn ($q) => $q->where('users.id', $order->seller_user_id))
                ->orderBy('id')
                ->first();

            if ($conv) {
                $conv->update(['order_id' => $order->id, 'last_message_at' => now()]);
            } else {
                $conv = Conversation::create([
                    'type'            => 'order',
                    'order_id'        => $order->id,
                    'asset_id'        => $asset->id,
                    'last_message_at' => now(),
                ]);
                $conv->participants()->attach([$order->buyer_user_id, $order->seller_user_id]);
            }

            $this->recordHistory($order, 'pending_payment', 'delivery_pending', null, 'Payment confirmed');
            $this->audit->log('order.paid', $order, ['status'=>'pending_payment'], ['status'=>'delivery_pending']);

            // Credit seller pending balance immediately after payment (8h lock applied via earning_released flag)
            // Pending balance increases; available_balance unchanged until lock releases.
            $this->walletSvc->creditPending(
                $order->seller,
                $order->seller_earning,
                \App\Enums\TransactionType::SellerEarningPending,
                $order,
                "Pending earning for order #{$order->order_number}"
            );

            return $order->fresh();
        });
    }

    /**
     * Seller delivers the order.
     */
    public function deliver(Order $order, User $seller, string $note, ?UploadedFile $file = null): OrderDelivery
    {
        abort_unless($order->seller_user_id === $seller->id, 403);
        abort_unless($order->status->canBeDelivered(), 422, 'Order cannot be delivered in its current state.');
        abort_unless($seller->canTransact(), 403, 'Your account is restricted.');

        return DB::transaction(function () use ($order, $seller, $note, $file) {
            $attachmentPath = null;
            if ($file) {
                $attachmentPath = $file->store("orders/{$order->id}/delivery", 'private');
            }

            $delivery = OrderDelivery::create([
                'order_id'      => $order->id,
                'delivered_by'  => $seller->id,
                'delivery_note' => $note,
                'attachment_path' => $attachmentPath,
            ]);

            $buyerProtHours = $this->settings->buyerProtectionHours();
            $order->update([
                'status'           => OrderStatus::Delivered,
                'delivery_status'  => 'delivered',
                'delivered_at'     => now(),
                'auto_complete_at' => now()->addHours($buyerProtHours), // reset 72h window
            ]);

            $this->recordHistory($order, 'delivery_pending', 'delivered', $seller->id, 'Seller delivered the asset');
            $this->audit->log('order.delivered', $order);

            return $delivery;
        });
    }

    /**
     * Buyer manually completes the order.
     */
    public function complete(Order $order, User $buyer): void
    {
        abort_unless($order->buyer_user_id === $buyer->id, 403);
        abort_unless($order->status->canBeCompleted(), 422, 'Order cannot be completed in its current state.');

        DB::transaction(function () use ($order, $buyer) {
            $earningLockHours = $this->settings->earningLockHours();
            $order->update([
                'status'                     => OrderStatus::Completed,
                'delivery_status'            => 'confirmed',
                'buyer_received_at'          => now(),
                'completed_at'               => now(),
                'seller_earning_available_at'=> now()->addHours($earningLockHours),
            ]);
            $this->recordHistory($order, 'delivered', 'completed', $buyer->id, 'Buyer confirmed completion');
            $this->audit->log('order.completed', $order);
        });
    }

    /**
     * Auto-complete order after 72h (called by scheduler).
     */
    public function autoComplete(Order $order): void
    {
        if ($order->status !== OrderStatus::Delivered) return;
        if ($order->auto_complete_at?->isFuture()) return;

        DB::transaction(function () use ($order) {
            $earningLockHours = $this->settings->earningLockHours();
            $order->update([
                'status'                     => OrderStatus::Completed,
                'delivery_status'            => 'auto_confirmed',
                'auto_completed_at'          => now(),
                'completed_at'               => now(),
                'seller_earning_available_at'=> now()->addHours($earningLockHours),
            ]);
            $this->recordHistory($order, 'delivered', 'completed', null, 'Auto-completed after buyer protection window');
            $this->audit->log('order.auto_completed', $order);
        });
    }

    /**
     * Mark a payment as failed (called from gateway callback).
     */
    public function markPaymentFailed(Order $order, string $reason = ''): void
    {
        DB::transaction(function () use ($order, $reason) {
            $order->update(['payment_status' => 'failed']);
            Payment::where('order_id', $order->id)->where('status', 'pending')
                ->update(['status' => 'failed']);
            $this->recordHistory($order, 'pending_payment', 'pending_payment', null, "Payment failed: {$reason}");
        });
    }

    private function generateOrderNumber(): string
    {
        do {
            $num = 'ORD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (Order::where('order_number', $num)->exists());
        return $num;
    }

    private function recordHistory(Order $order, ?string $from, string $to, ?int $userId, string $note = ''): void
    {
        $order->statusHistory()->create([
            'from_status' => $from,
            'to_status'   => $to,
            'changed_by'  => $userId,
            'note'        => $note,
            'created_at'  => now(),
        ]);
    }
}

