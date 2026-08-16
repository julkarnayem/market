@props(['status', 'size' => 'sm'])
@php
$map = [
    // User
    'active'                  => ['mint','Active','●'],
    'suspended'               => ['rose','Suspended','✕'],
    'restricted'              => ['amber','Restricted','!'],
    'pending_verification'    => ['amber','Pending Verification','◐'],
    // Verification
    'not_submitted'           => ['slate','Not Submitted','○'],
    'pending'                 => ['amber','Pending','◐'],
    'approved'                => ['mint','Approved','✓'],
    'rejected'                => ['rose','Rejected','✕'],
    // Asset
    'draft'                   => ['slate','Draft','○'],
    'pending_review'          => ['amber','Pending Review','◐'],
    'published'               => ['mint','Published','●'],
    'pending_edit_approval'   => ['amber','Edit Pending','◐'],
    'paused'                  => ['slate','Paused','❙❙'],
    'sold_out'                => ['slate','Sold Out','⊘'],
    'archived'                => ['slate','Archived','▣'],
    // Order status
    'pending_payment'         => ['amber','Awaiting Payment','◐'],
    'paid'                    => ['brand','Paid','✓'],
    'delivery_pending'        => ['brand','Delivery Pending','↓'],
    'delivered'               => ['brand','Delivered','↓'],
    'completed'               => ['mint','Completed','✓'],
    'disputed'                => ['rose','Disputed','⚑'],
    'refunded'                => ['rose','Refunded','↩'],
    'partially_refunded'      => ['rose','Partial Refund','↩'],
    'seller_payment_released' => ['mint','Seller Paid','✓'],
    'cancelled'               => ['slate','Cancelled','✕'],
    // Payment status
    'failed'                  => ['rose','Failed','✕'],
    // Delivery status
    'not_started'             => ['slate','Not Started','○'],
    'confirmed'               => ['mint','Confirmed','✓'],
    'auto_confirmed'          => ['mint','Auto-confirmed','✓'],
    // Withdrawal
    'processing'              => ['brand','Processing','⟳'],
    'completed'               => ['mint','Completed','✓'],
    // Offer
    'accepted'                => ['mint','Accepted','✓'],
    'expired'                 => ['slate','Expired','○'],
    // Ticket
    'open'                    => ['brand','Open','●'],
    'in_progress'             => ['amber','In Progress','◐'],
    'waiting_for_user'        => ['amber','Waiting on You','◐'],
    'waiting_for_staff'       => ['brand','Waiting on Staff','◐'],
    'resolved'                => ['mint','Resolved','✓'],
    'closed'                  => ['slate','Closed','○'],
    // Dispute
    'under_review'            => ['brand','Under Review','◐'],
    'waiting_for_buyer'       => ['amber','Waiting for Buyer','◐'],
    'waiting_for_seller'      => ['amber','Waiting for Seller','◐'],
    // Fraud / Review
    'reviewing'               => ['brand','Reviewing','◐'],
    'cleared'                 => ['mint','Cleared','✓'],
    'escalated'               => ['rose','Escalated','⚑'],
    // SMS
    'sent'                    => ['mint','Sent','✓'],
    'dismissed'               => ['slate','Dismissed','✕'],
    'actioned'                => ['rose','Actioned','!'],
    'reviewed'                => ['mint','Reviewed','✓'],
    // Message report
    'actioned'                => ['amber','Actioned','✓'],
];
[$tone,$label,$icon] = $map[$status] ?? ['slate', ucwords(str_replace('_',' ',(string)$status)), '●'];
@endphp
<span {{ $attributes->merge(['class' => "badge-{$tone}"]) }} aria-label="{{ $label }}">
    <span aria-hidden="true">{{ $icon }}</span> {{ $label }}
</span>
