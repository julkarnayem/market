<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file a buyer, seller or admin attached to a dispute.
 *
 * Files live on the `private` disk and are never web-reachable: the only way to
 * one is DisputeController::evidence(), which authorizes the reader first.
 * file_path and file_disk are hidden so a careless `->toArray()` cannot leak the
 * storage layout into an Inertia payload.
 */
class DisputeEvidence extends Model
{
    protected $fillable = [
        'dispute_id', 'submitted_by', 'message_id', 'role', 'message',
        'file_path', 'file_disk', 'file_original_name', 'file_mime', 'file_size', 'metadata',
    ];

    protected $hidden = ['file_path', 'file_disk'];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'metadata'  => 'array',
        ];
    }

    public function dispute(): BelongsTo   { return $this->belongsTo(Dispute::class); }
    public function submitter(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by'); }

    /** Where this file sits in the thread. */
    public function threadMessage(): BelongsTo
    {
        return $this->belongsTo(DisputeMessage::class, 'message_id');
    }

    public function hasFile(): bool
    {
        return (bool) $this->file_path;
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->file_mime, 'image/');
    }

    /** Human size for the UI, e.g. "1.4 MB". */
    public function sizeLabel(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes <= 0) return '—';
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';

        return round($bytes / 1048576, 1) . ' MB';
    }
}
