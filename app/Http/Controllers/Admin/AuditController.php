<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        // This class had no authorization at all. The `admin` middleware only
        // requires *some* admin role, so support and finance staff could read
        // the entire audit trail — while both sidebars gate the nav item on
        // `audit.view`. Authorize what the menu already claims to require.
        $this->authorize('audit.view');

        // The dates reached whereDate() rather than Carbon::parse(), so garbage
        // never 500'd — but a reversed range silently matched nothing with no
        // explanation. It is now a field error the form renders.
        $filters = $request->validate([
            'q'    => 'nullable|string|max:100',
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $q    = $filters['q'] ?? null;
        $from = $filters['from'] ?? null;
        $to   = $filters['to'] ?? null;

        $logs = AuditLog::query()
            // The box has always said "Search action or user…", but only
            // `action` was ever matched — searching a staff name found nothing.
            ->when($q, fn ($query) => $query->where(fn ($group) => $group
                ->where('action', 'like', "%{$q}%")
                ->orWhereHas('actor', fn ($actor) => $actor
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%"))))
            ->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to))
            ->with('actor')
            ->latest()
            ->paginate(30)
            ->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id'        => $log->id,
                'actor'     => $log->actor?->name ?? 'System',
                'action'    => $log->action,
                'entity'    => $log->auditable_type ? class_basename($log->auditable_type) : null,
                'entity_id' => $log->auditable_id !== null ? (int) $log->auditable_id : null,
                'ip'        => $log->ip_address,
                // created_at is nullable and the model sets $timestamps = false,
                // so a row written without one must not fatal the whole page.
                'date'       => $log->created_at?->format('d M Y, H:i'),
                'short_date' => $log->created_at?->format('d M, H:i'),
            ]);

        return Inertia::render('Admin/Audit/Index', [
            'logs'    => $logs,
            'filters' => [
                'q'    => $q    ?? '',
                'from' => $from ?? '',
                'to'   => $to   ?? '',
            ],
            // /admin/activity-logs is a second route onto this same controller.
            // Submitting the filter form to a hardcoded admin.audit would bounce
            // the user off whichever URL they arrived on.
            'action' => $request->url(),
        ]);
    }
}
