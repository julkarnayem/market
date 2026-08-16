<?php
namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class TicketService
{
    public function __construct(
        private readonly AuditLogger        $audit,
        private readonly NotificationService $notifs,
        private readonly TelegramService    $telegram,
    ) {}

    /** Create ticket + first message. */
    public function create(User $user, array $data, ?UploadedFile $file = null): SupportTicket
    {
        $ticket = SupportTicket::create([
            'reference'     => 'TKT-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
            'user_id'       => $user->id,
            'order_id'      => $data['order_id'] ?? null,
            'asset_id'      => $data['asset_id'] ?? null,
            'withdrawal_id' => $data['withdrawal_id'] ?? null,
            'category'      => $data['category'],
            'subject'       => $data['subject'],
            'priority'      => $data['priority'] ?? 'normal',
            'status'        => TicketStatus::Open,
            'last_reply_at' => now(),
        ]);

        $this->addMessage($ticket, $user, $data['message'], $file, false);
        return $ticket;
    }

    /** Add a reply (user or staff). */
    public function reply(SupportTicket $ticket, User $sender, string $body, ?UploadedFile $file = null, bool $isStaff = false): SupportTicketMessage
    {
        abort_if($ticket->isClosed() && !$isStaff, 422, 'This ticket is closed.');

        $msg = $this->addMessage($ticket, $sender, $body, $file, $isStaff);

        $newStatus = $isStaff ? TicketStatus::WaitingForUser : TicketStatus::WaitingForStaff;
        $ticket->update(['last_reply_at' => now(), 'status' => $newStatus]);

        // Notify the other party
        if ($isStaff) {
            $this->notifs->inApp($ticket->user, 'ticket_reply',
                'Staff replied to your ticket',
                "Support replied to: {$ticket->subject}",
                ['ticket_id' => $ticket->id]
            );
        }
        return $msg;
    }

    /** Assign ticket to staff member. */
    public function assign(SupportTicket $ticket, ?User $staff, User $admin): void
    {
        $ticket->update(['assigned_to' => $staff?->id, 'assigned_at' => now()]);
        $this->audit->log('ticket.assigned', $ticket, [], ['assigned_to' => $staff?->name], '', 'ticket');
    }

    /** Change status (staff only). */
    public function changeStatus(SupportTicket $ticket, string $status, User $staff, string $reason = ''): void
    {
        $old = $ticket->status->value;
        $updates = ['status' => $status];
        if ($status === 'resolved') $updates['resolved_at'] = now();
        if ($status === 'closed')   $updates['closed_at']   = now();
        $ticket->update($updates);
        $this->audit->log('ticket.status_changed', $ticket, ['status' => $old], ['status' => $status], $reason, 'ticket');
        $this->notifs->inApp($ticket->user, 'ticket_status_changed',
            'Ticket status updated', "Ticket #{$ticket->reference} status: " . ucwords(str_replace('_',' ',$status)),
            ['ticket_id' => $ticket->id]
        );
    }

    private function addMessage(SupportTicket $ticket, User $sender, string $body, ?UploadedFile $file, bool $isStaff): SupportTicketMessage
    {
        $attachPath = null;
        $attachName = null;
        if ($file) {
            // Validate: no executables
            abort_if(in_array($file->getClientOriginalExtension(), ['php','exe','sh','bat','js','py']), 422, 'File type not allowed.');
            $attachPath = $file->store("tickets/{$ticket->id}", 'private');
            $attachName = $file->getClientOriginalName();
        }
        return SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id'           => $sender->id,
            'body'              => $body,
            'attachment_path'   => $attachPath,
            'attachment_name'   => $attachName,
            'is_staff_reply'    => $isStaff,
        ]);
    }
}
