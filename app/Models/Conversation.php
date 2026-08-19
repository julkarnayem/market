<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = ['type','order_id','asset_id','last_message_at','status','last_message_id'];
    protected $casts    = ['last_message_at'=>'datetime'];

    public function order(): BelongsTo     { return $this->belongsTo(Order::class); }
    public function asset(): BelongsTo     { return $this->belongsTo(Asset::class); }
    public function messages(): HasMany    { return $this->hasMany(Message::class)->withTrashed(); }
    public function activeMessages(): HasMany { return $this->hasMany(Message::class); }
    public function notes(): HasMany       { return $this->hasMany(ConversationNote::class); }
    public function offers(): HasMany      { return $this->hasMany(Offer::class); }

    /**
     * The listing this conversation is about: either its own listing context
     * (a "Contact Seller" chat) or the listing behind its order.
     */
    public function contextAsset(): ?Asset
    {
        return $this->asset ?? $this->order?->asset;
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class,'conversation_participants')
            ->withPivot('last_read_at');
    }

    /** Count unread messages for a given user. */
    public function unreadCountFor(int $userId): int
    {
        $lastRead = $this->participants()->where('users.id',$userId)->value('last_read_at');
        $q = $this->activeMessages()->where('sender_user_id','!=',$userId);
        if ($lastRead) $q->where('created_at','>',$lastRead);
        return $q->count();
    }

    /** Has this user participated in this conversation? */
    public function hasParticipant(int $userId): bool
    {
        return $this->participants()->where('users.id',$userId)->exists();
    }

    public function markReadFor(int $userId): void
    {
        $this->participants()->updateExistingPivot($userId,['last_read_at'=>now()]);
    }
}
