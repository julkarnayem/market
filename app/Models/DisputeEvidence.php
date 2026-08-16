<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DisputeEvidence extends Model
{
    protected $fillable = ['dispute_id','submitted_by','role','message','file_path','file_disk','file_original_name'];
    protected $hidden   = ['file_path','file_disk'];

    public function dispute(): BelongsTo    { return $this->belongsTo(Dispute::class); }
    public function submitter(): BelongsTo  { return $this->belongsTo(User::class,'submitted_by'); }
    public function hasFile(): bool         { return (bool) $this->file_path; }
}
