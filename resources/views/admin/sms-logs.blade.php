<x-layouts.admin title="SMS Logs" heading="SMS Logs">
    <x-breadcrumb :items="[['label'=>'Notifications','url'=>route('admin.notifications')],['label'=>'SMS Logs']]" />
    <div class="d-flex gap-2 mb-3 flex-wrap">
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <select name="status" class="select w-auto" onchange="this.form.submit()">
                @foreach(['all'=>'All','sent'=>'Sent','failed'=>'Failed','pending'=>'Pending'] as $k=>$l)
                    <option value="{{ $k }}" @selected(request('status','all')===$k)>{{ $l }}</option>
                @endforeach
            </select>
            <select name="template" class="select w-auto" onchange="this.form.submit()">
                <option value="">All templates</option>
                @foreach(array_keys(\App\Services\Sms\SmsTemplates::TEMPLATES) as $t)
                    <option value="{{ $t }}" @selected(request('template')===$t)>{{ $t }}</option>
                @endforeach
            </select>
        </form>
    </div>
    <div class="table-wrap d-none d-sm-block">
        <table class="table">
            <thead><tr><th>User</th><th>Phone</th><th>Template</th><th>Status</th><th>Attempts</th><th>Sent</th><th>Error</th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td class="fs-sm">{{ $log->user?->name ?? '—' }}</td>
                    <td class="font-mono fs-xs">{{ $log->maskedPhone() }}</td>
                    <td class="fs-xs">{{ $log->template }}</td>
                    <td><x-status-badge :status="$log->status" /></td>
                    <td class="text-center">{{ $log->attempts }}</td>
                    <td class="fs-xs text-muted">{{ $log->sent_at?->format('d M, H:i') ?? '—' }}</td>
                    <td class="fs-xs text-danger max-w-[200px] text-truncate">{{ $log->error_message ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center py-4 text-muted">No SMS logs.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $logs->withQueryString()->links() }}</div>
</x-layouts.admin>
