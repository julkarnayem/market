<script setup lang="ts">
/**
 * Vue port of resources/views/components/status-badge.blade.php.
 * The status→[tone,label,icon] map is copied verbatim; where the Blade map had
 * duplicate keys (PHP keeps the last), the resolved value is used here:
 *   completed → mint/Completed/✓ (both entries identical)
 *   actioned  → amber/Actioned/✓ (message-report entry won over the SMS one)
 * Tone maps onto the badge-{tone} classes in app.css.
 */
import { computed } from 'vue';

const props = defineProps<{ status: string | null | undefined }>();

type Entry = [tone: string, label: string, icon: string];

const MAP: Record<string, Entry> = {
    // User
    active: ['mint', 'Active', '●'],
    suspended: ['rose', 'Suspended', '✕'],
    restricted: ['amber', 'Restricted', '!'],
    pending_verification: ['amber', 'Pending Verification', '◐'],
    // Verification
    not_submitted: ['slate', 'Not Submitted', '○'],
    pending: ['amber', 'Pending', '◐'],
    approved: ['mint', 'Approved', '✓'],
    rejected: ['rose', 'Rejected', '✕'],
    // Asset
    draft: ['slate', 'Draft', '○'],
    pending_review: ['amber', 'Pending Review', '◐'],
    published: ['mint', 'Published', '●'],
    pending_edit_approval: ['amber', 'Edit Pending', '◐'],
    paused: ['slate', 'Paused', '❙❙'],
    sold_out: ['slate', 'Sold Out', '⊘'],
    // A listing whose bid was accepted is off the market but *not* sold — it is
    // waiting for the winning bidder to pay.
    bid_accepted: ['amber', 'Bid Accepted', '🔒'],
    archived: ['slate', 'Archived', '▣'],
    // Order status
    pending_payment: ['amber', 'Awaiting Payment', '◐'],
    paid: ['brand', 'Paid', '✓'],
    delivery_pending: ['brand', 'Delivery Pending', '↓'],
    delivered: ['brand', 'Delivered', '↓'],
    completed: ['mint', 'Completed', '✓'],
    disputed: ['rose', 'Disputed', '⚑'],
    refunded: ['rose', 'Refunded', '↩'],
    partially_refunded: ['rose', 'Partial Refund', '↩'],
    seller_payment_released: ['mint', 'Seller Paid', '✓'],
    cancelled: ['slate', 'Cancelled', '✕'],
    // Payment status
    failed: ['rose', 'Failed', '✕'],
    // Delivery status
    not_started: ['slate', 'Not Started', '○'],
    confirmed: ['mint', 'Confirmed', '✓'],
    auto_confirmed: ['mint', 'Auto-confirmed', '✓'],
    // Withdrawal
    processing: ['brand', 'Processing', '⟳'],
    // Offer / Bid (both share accepted/rejected/cancelled/expired; a bid's
    // "active" reuses the User entry above, which reads the same)
    accepted: ['mint', 'Accepted', '✓'],
    expired: ['slate', 'Expired', '○'],
    outbid: ['slate', 'Outbid', '↓'],
    // Ticket
    open: ['brand', 'Open', '●'],
    in_progress: ['amber', 'In Progress', '◐'],
    waiting_for_user: ['amber', 'Waiting on You', '◐'],
    waiting_for_staff: ['brand', 'Waiting on Staff', '◐'],
    resolved: ['mint', 'Resolved', '✓'],
    closed: ['slate', 'Closed', '○'],
    // Dispute (the lifecycle in App\Enums\DisputeStatus; `open`, `refunded`,
    // `cancelled` and `escalated` reuse the entries above, which read the same).
    seller_responded: ['brand', 'Seller Responded', '↩'],
    negotiating: ['amber', 'Negotiating', '⇄'],
    resolved_buyer: ['mint', 'Resolved — Buyer', '✓'],
    resolved_seller: ['mint', 'Resolved — Seller', '✓'],
    resolved_partial: ['mint', 'Resolved — Partial', '✓'],
    // Fraud / Review
    reviewing: ['brand', 'Reviewing', '◐'],
    cleared: ['mint', 'Cleared', '✓'],
    escalated: ['rose', 'Escalated', '⚑'],
    // SMS
    sent: ['mint', 'Sent', '✓'],
    dismissed: ['slate', 'Dismissed', '✕'],
    reviewed: ['mint', 'Reviewed', '✓'],
    // Message report (last-wins over the SMS "actioned")
    actioned: ['amber', 'Actioned', '✓'],
};

const resolved = computed<Entry>(() => {
    const s = props.status ?? '';
    return (
        MAP[s] ?? [
            'slate',
            s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()),
            '●',
        ]
    );
});
</script>

<template>
    <span :class="`badge-${resolved[0]}`" :aria-label="resolved[1]">
        <span aria-hidden="true">{{ resolved[2] }}</span> {{ resolved[1] }}
    </span>
</template>
