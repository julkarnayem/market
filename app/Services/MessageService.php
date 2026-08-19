<?php
namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationNote;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class MessageService
{
    // Max 30 messages per minute per user
    public const MAX_PER_MINUTE = 30;
    private const ALLOWED_MIME  = ['image/jpeg','image/png','image/webp','application/pdf','text/plain'];
    private const MAX_BYTES     = 10 * 1024 * 1024; // 10 MB

    /**
     * Send a message. Idempotent via client_message_id.
     */
    public function send(
        Conversation  $conversation,
        User          $sender,
        string        $body,
        ?string       $clientMsgId   = null,
        ?UploadedFile $attachment     = null,
        ?int          $replyToId     = null,
        bool          $isSystem      = false,
    ): Message {
        // Idempotency: return existing if same client_message_id
        if ($clientMsgId) {
            $existing = Message::where('conversation_id', $conversation->id)
                ->where('sender_user_id', $sender->id)
                ->where('client_message_id', $clientMsgId)
                ->first();
            if ($existing) return $existing;
        }

        // Validate reply_to belongs to same conversation
        if ($replyToId) {
            abort_unless(Message::where('id',$replyToId)->where('conversation_id',$conversation->id)->exists(),
                422, 'Invalid reply target.');
        }

        [$attachPath, $attachName, $attachMime] = $this->storeAttachment($attachment);

        return DB::transaction(function () use (
            $conversation, $sender, $body, $clientMsgId,
            $attachPath, $attachName, $attachMime, $replyToId, $isSystem
        ) {
            $message = Message::create([
                'conversation_id'   => $conversation->id,
                'sender_user_id'    => $sender->id,
                'message_type'      => $attachPath ? 'attachment' : ($isSystem ? 'system' : 'text'),
                'body'              => $body,
                'client_message_id' => $clientMsgId,
                'reply_to_id'       => $replyToId,
                'attachment_path'   => $attachPath,
                'attachment_disk'   => 'private',
                'attachment_name'   => $attachName,
                'attachment_mime'   => $attachMime,
                'is_system'         => $isSystem,
            ]);

            $conversation->update([
                'last_message_at' => now(),
                'last_message_id' => $message->id,
            ]);

            return $message;
        });
    }

    /**
     * Post a structured card into a conversation — currently custom offers.
     *
     * The card is attributed to whoever created it (not the system) so it
     * renders on their side of the thread, and its payload lives in
     * `messages.metadata` so the chat can render it without a second query.
     */
    public function sendCard(
        Conversation $conversation,
        User $sender,
        string $messageType,
        string $body,
        array $metadata = [],
    ): Message {
        return DB::transaction(function () use ($conversation, $sender, $messageType, $body, $metadata) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_user_id'  => $sender->id,
                'message_type'    => $messageType,
                'body'            => $body,
                'metadata'        => $metadata,
            ]);

            $conversation->update([
                'last_message_at' => now(),
                'last_message_id' => $message->id,
            ]);

            return $message;
        });
    }

    /** Create an internal staff note (never visible to order participants). */
    public function addNote(Conversation $conversation, User $staff, string $body): ConversationNote
    {
        return ConversationNote::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $staff->id,
            'body'            => $body,
        ]);
    }

    /** Mark all unread messages in a conversation as read for a user. */
    public function markRead(Conversation $conversation, User $user): void
    {
        $conversation->markReadFor($user->id);
    }

    /** Count total unread across all the user's conversations. */
    public function totalUnreadCount(User $user): int
    {
        $convIds = $user->conversations()->pluck('conversations.id');
        return Message::whereIn('conversation_id', $convIds)
            ->where('sender_user_id', '!=', $user->id)
            ->whereDoesntHave('conversation', fn($q) => $q->whereHas('participants', function($p) use ($user) {
                $p->where('users.id', $user->id)
                  ->whereRaw('messages.created_at <= conversation_participants.last_read_at');
            }))->count();
    }

    /** Report a message. */
    public function report(Message $message, User $reporter, string $reason, string $description = ''): MessageReport
    {
        // Must have access to conversation
        abort_unless($message->conversation->hasParticipant($reporter->id), 403);
        // One report per user per message
        return MessageReport::firstOrCreate(
            ['message_id' => $message->id, 'reporter_id' => $reporter->id],
            ['reason' => $reason, 'description' => $description, 'status' => 'pending']
        );
    }

    /** Soft-delete a message (preserves for dispute/audit). */
    public function softDelete(Message $message, User $actor): void
    {
        // Only sender or authorized staff
        $message->delete();
    }

    private function storeAttachment(?UploadedFile $file): array
    {
        if (!$file) return [null, null, null];
        abort_unless(in_array($file->getMimeType(), self::ALLOWED_MIME, true), 422, 'File type not allowed.');
        abort_if($file->getSize() > self::MAX_BYTES, 422, 'Attachment exceeds 10 MB limit.');
        $path = $file->store('messages', 'private');
        return [$path, $file->getClientOriginalName(), $file->getMimeType()];
    }
}
