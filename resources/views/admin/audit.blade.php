<x-layouts.admin title="Audit Logs" heading="Audit Logs">
    <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
        <input name="q" value="{{ request('q') }}" placeholder="Search action or user…" class="input max-w-xs">
        <input type="date" name="from" value="{{ request('from') }}" class="input w-auto">
        <input type="date" name="to" value="{{ request('to') }}" class="input w-auto">
        <x-button type="submit" variant="outline">Filter</x-button>
        <a href="{{ route('admin.audit') }}" class="btn-ghost">Clear</a>
    </form>

    {{-- Desktop --}}
    <div class="table-wrap d-none d-sm-block">
        <table class="table">
            <thead><tr><th>Actor</th><th>Action</th><th>Entity</th><th>ID</th><th>IP</th><th>Date</th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td class="fw-medium">{{ $log->actor?->name ?? 'System' }}</td>
                    <td><code class="fs-xs bg-light px-1 py-1 rounded">{{ $log->action }}</code></td>
                    <td class="text-muted">{{ class_basename($log->auditable_type??'') }}</td>
                    <td class="text-muted">{{ $log->auditable_id ?? '—' }}</td>
                    <td class="text-muted fs-xs font-mono">{{ $log->ip_address ?? '—' }}</td>
                    <td class="text-muted fs-xs">{{ $log->created_at->format('d M Y, H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-4 text-muted">No audit logs found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{-- Mobile --}}
    <div class="d-sm-none vstack gap-2">
    @foreach($logs as $log)
        <div class="card-p fs-sm">
            <div class="d-flex justify-content-between gap-2">
                <code class="bg-light px-2 py-1 rounded fs-xs">{{ $log->action }}</code>
                <span class="text-secondary fs-xs">{{ $log->created_at->format('d M, H:i') }}</span>
            </div>
            <p class="mt-1 text-dark">{{ $log->actor?->name ?? 'System' }}</p>
        </div>
    @endforeach
    </div>
    <div class="mt-3">{{ $logs->withQueryString()->links() }}</div>
</x-layouts.admin>
