<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;

class AuditController extends Controller
{
    public function index()
    {
        $logs = AuditLog::query()
            ->when(request('q'), fn($q) => $q->where('action', 'like', '%'.request('q').'%'))
            ->when(request('from'), fn($q) => $q->whereDate('created_at', '>=', request('from')))
            ->when(request('to'), fn($q) => $q->whereDate('created_at', '<=', request('to')))
            ->with('actor')
            ->latest()
            ->paginate(30);

        return view('admin.audit', compact('logs'));
    }
}
