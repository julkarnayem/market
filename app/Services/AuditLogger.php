<?php
namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public function log(
        string  $action,
        ?Model  $auditable = null,
        array   $old       = [],
        array   $new       = [],
        string  $reason    = '',
        ?string $module    = null,
    ): AuditLog {
        // Derive module from action prefix (e.g. "user.suspend" → "user")
        $module ??= explode('.', $action)[0];

        return AuditLog::create([
            'user_id'        => Auth::id(),
            'action'         => $action,
            'module'         => $module,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id'   => $auditable?->getKey(),
            'old_values'     => $old ?: null,
            'new_values'     => $new ?: null,
            'reason'         => $reason ?: null,
            'ip_address'     => request()->ip(),
            'user_agent'     => request()->userAgent(),
            'created_at'     => now(),
        ]);
    }
}
