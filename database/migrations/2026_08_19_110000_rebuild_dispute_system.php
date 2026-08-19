<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rebuilds the dispute system around the order.
 *
 * Nothing is deleted. `disputes` keeps its id, order, opener and resolution
 * columns; the free-text `reason` is folded into `description` (which is what it
 * always actually held) and replaced by a `reason_code` from the fixed reason
 * list, and every legacy status is carried onto the status that means the same
 * thing under the new lifecycle. `admin_notes` — a single overwritable text box
 * that the old admin screen reused for resolution notes — is replaced by real
 * internal notes in dispute_messages, so its content is moved into a note row
 * before the column goes.
 *
 * The dispute thread gets its own table rather than a `type='dispute'`
 * conversation: Order::conversation() is an unconstrained hasOne, so a second
 * conversation on the same order would make the buyer↔seller thread ambiguous,
 * and dispute messages must never surface in it.
 */
return new class extends Migration
{
    /**
     * Legacy dispute status → new lifecycle. 'resolved' is not here: it maps on
     * the row's resolution_type, since that is what recorded the outcome.
     */
    private const STATUS_MAP = [
        'open'               => 'open',
        'under_review'       => 'escalated',       // an admin was already on it
        'waiting_for_buyer'  => 'negotiating',
        'waiting_for_seller' => 'open',            // seller has not answered yet
        'rejected'           => 'resolved_seller', // decided against the buyer
        'closed'             => 'cancelled',
    ];

    /** Legacy resolution_type on a 'resolved' row → new terminal status. */
    private const RESOLVED_MAP = [
        'full_refund'            => 'resolved_buyer',
        'partial_refund'         => 'resolved_partial',
        'seller_payment_release' => 'resolved_seller',
    ];

    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            // Buyer-facing handle (D-10025). Backfilled below, then made unique.
            $table->string('reference', 24)->nullable()->after('id');
            $table->string('reason_code', 40)->default('other')->after('opened_by');
            $table->timestamp('seller_responded_at')->nullable()->after('status');
            $table->timestamp('escalated_at')->nullable()->after('seller_responded_at');
            // Drives the admin queue's "Last activity" column and its ordering.
            $table->timestamp('last_activity_at')->nullable()->after('escalated_at');
        });

        // ── The dispute thread ───────────────────────────────────────────
        // Buyer ↔ Seller ↔ Admin. `is_internal` rows are admin-only and are
        // filtered out server-side for every non-admin reader.
        Schema::create('dispute_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispute_id')->constrained()->cascadeOnDelete();
            // Nullable: a system event has no author.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 24)->default('text');
            $table->string('role', 16)->nullable();       // buyer|seller|admin
            $table->text('body')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_internal')->default(false);
            // Dedupe key for a double-submitted composer, as messages does.
            $table->string('client_message_id', 64)->nullable();
            $table->timestamps();
            $table->index(['dispute_id', 'id']);
            $table->index(['dispute_id', 'is_internal']);
            $table->unique(['dispute_id', 'client_message_id']);
        });

        // ── Negotiated + admin resolutions ──────────────────────────────
        // Buyer/seller proposals and the admin's final decision share one table
        // so §15's audit trail and §10-12's negotiation are the same history.
        Schema::create('dispute_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposed_by')->constrained('users')->cascadeOnDelete();
            $table->string('role', 16);                    // buyer|seller|admin
            $table->string('type', 32);                    // full_refund|partial_refund|replacement|release_seller
            $table->unsignedBigInteger('amount')->nullable(); // poisha
            $table->text('note')->nullable();
            $table->string('status', 16)->default('proposed');
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            // Set the moment money moves — the idempotency guard against a
            // second execution of the same decision.
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
            $table->index(['dispute_id', 'status']);
        });

        Schema::table('dispute_evidence', function (Blueprint $table) {
            // Links a piece of evidence to its place in the thread.
            $table->foreignId('message_id')->nullable()->after('submitted_by')
                ->constrained('dispute_messages')->nullOnDelete();
            $table->string('file_mime', 128)->nullable()->after('file_original_name');
            $table->unsignedBigInteger('file_size')->nullable()->after('file_mime');
            $table->json('metadata')->nullable()->after('file_size');
        });

        $this->migrateExistingDisputes();

        Schema::table('disputes', function (Blueprint $table) {
            $table->unique('reference');
            $table->index('last_activity_at');
        });

        // The old free-text reason now lives in description; the single
        // admin_notes box is superseded by internal note messages.
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropColumn(['reason', 'admin_notes']);
        });
    }

    /**
     * Carry the live rows onto the new shape. Runs row by row: there is a
     * handful of them, and the status mapping depends on resolution_type.
     */
    private function migrateExistingDisputes(): void
    {
        foreach (DB::table('disputes')->orderBy('id')->get() as $row) {
            $legacy = (string) ($row->status ?? 'open');

            $status = $legacy === 'resolved'
                ? (self::RESOLVED_MAP[(string) $row->resolution_type] ?? 'resolved_buyer')
                : (self::STATUS_MAP[$legacy] ?? 'open');

            // The old `reason` column held free prose, not a code — that is what
            // `description` is for now. Keep both if description was also used.
            $description = trim((string) ($row->description ?? ''));
            $reason      = trim((string) ($row->reason ?? ''));
            if ($reason !== '' && $reason !== $description) {
                $description = $description === '' ? $reason : $description . "\n\n" . $reason;
            }

            DB::table('disputes')->where('id', $row->id)->update([
                'reference'        => 'D-' . (10000 + (int) $row->id),
                'reason_code'      => 'other', // legacy prose cannot be classified
                'status'           => $status,
                'description'      => $description !== '' ? $description : null,
                'escalated_at'     => $status === 'escalated' ? ($row->updated_at ?? $row->created_at) : null,
                'last_activity_at' => $row->updated_at ?? $row->created_at,
            ]);

            // Keep the order's mirror column in step with the remapped status.
            DB::table('orders')->where('id', $row->order_id)->update(['dispute_status' => $status]);

            // The old admin_notes box is the only place staff commentary lived.
            // It becomes a real internal note so it stays admin-only and is not
            // lost with the column — attributed to the resolver when known.
            $adminNote = trim((string) ($row->admin_notes ?? ''));
            if ($adminNote !== '') {
                DB::table('dispute_messages')->insert([
                    'dispute_id'  => $row->id,
                    'user_id'     => $row->resolved_by,
                    'type'        => 'internal_note',
                    'role'        => 'admin',
                    'body'        => $adminNote,
                    'is_internal' => true,
                    'created_at'  => $row->updated_at ?? $row->created_at,
                    'updated_at'  => $row->updated_at ?? $row->created_at,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('dispute_evidence', function (Blueprint $table) {
            $table->dropForeign(['message_id']);
            $table->dropColumn(['message_id', 'file_mime', 'file_size', 'metadata']);
        });

        Schema::dropIfExists('dispute_resolutions');
        Schema::dropIfExists('dispute_messages');

        Schema::table('disputes', function (Blueprint $table) {
            $table->text('reason')->nullable();
            $table->text('admin_notes')->nullable();
        });

        // Put the prose back where the old code read it from.
        DB::table('disputes')->update(['reason' => DB::raw('description')]);

        Schema::table('disputes', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropIndex(['last_activity_at']);
            $table->dropColumn([
                'reference', 'reason_code', 'seller_responded_at', 'escalated_at', 'last_activity_at',
            ]);
        });
    }
};
