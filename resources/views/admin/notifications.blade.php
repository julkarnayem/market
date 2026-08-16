<x-layouts.admin title="Notifications" heading="Notification Management">
    <div class="row row-cols-3 gap-3 mb-4">
        <a href="{{ route('admin.sms-logs') }}" class="stat-card transition-shadow">
            <p class="stat-label">SMS Logs</p>
            <p class="stat-value">{{ \App\Models\SmsLog::count() }}</p>
            <p class="fs-xs text-muted mt-1">View all → </p>
        </a>
        <div class="stat-card bg-success bg-opacity-10">
            <p class="stat-label text-success">SMS sent</p>
            <p class="stat-value text-success">{{ \App\Models\SmsLog::where('status','sent')->count() }}</p>
        </div>
        <div class="stat-card bg-danger bg-opacity-10">
            <p class="stat-label text-danger">SMS failed</p>
            <p class="stat-value text-danger">{{ \App\Models\SmsLog::where('status','failed')->count() }}</p>
        </div>
    </div>

    <x-card>
        <h2 class="section-title mb-2">SMS Provider Status</h2>
        @php $enabled = config('bulksmsbd.enabled') && config('bulksmsbd.api_key'); @endphp
        <div class="d-flex align-items-center gap-3">
            <span class="h-3 w-3 rounded-full {{ $enabled?'bg-mint-500':'bg-rose-500' }} ring-2 ring-offset-1 {{ $enabled?'ring-mint-300':'ring-rose-300' }}"></span>
            <span class="fs-sm fw-medium">BulkSMSBD {{ $enabled?'configured':'not configured' }}</span>
        </div>
        @if(!$enabled)
            <p class="fs-xs text-muted mt-2">Set <code>BULKSMSBD_API_KEY</code>, <code>BULKSMSBD_SENDER_ID</code> and <code>BULKSMSBD_ENABLED=true</code> in <code>.env</code>.</p>
        @endif
    </x-card>
</x-layouts.admin>
