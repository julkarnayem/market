<x-layouts.dashboard title="Notifications" heading="Notifications">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div class="tabs">
            <a href="{{ route('dashboard.notifications',['tab'=>'all']) }}" class="tab {{ request('tab','all')==='all'?'tab-active':'' }}">All</a>
            <a href="{{ route('dashboard.notifications',['tab'=>'unread']) }}" class="tab {{ request('tab')==='unread'?'tab-active':'' }}">Unread
                @if($unreadCount > 0)<span class="ms-1 badge-rose">{{ $unreadCount }}</span>@endif
            </a>
        </div>
        @if($unreadCount > 0)
            <form method="POST" action="{{ route('dashboard.notifications.read-all') }}">@csrf
                <x-button type="submit" variant="ghost" size="sm">Mark all read</x-button>
            </form>
        @endif
    </div>

    @if($notifications->isEmpty())
        <x-empty-state icon="🔔" title="No notifications">You're all caught up!</x-empty-state>
    @else
        <div class="vstack gap-2">
        @foreach($notifications as $n)
            @php
                $data    = $n->data;
                $isRead  = !is_null($n->read_at);
                $type    = $data['type'] ?? 'system';
                $title   = $data['title'] ?? 'Notification';
                $message = $data['message'] ?? '';
                $icon = match(true) {
                    str_starts_with($type,'order')    => '📦',
                    str_starts_with($type,'payment')  => '💳',
                    str_starts_with($type,'listing')  => '🏷️',
                    str_starts_with($type,'offer')    => '🤝',
                    str_starts_with($type,'withdraw') => '🏦',
                    str_starts_with($type,'dispute')  => '⚑',
                    str_starts_with($type,'wallet')   => '👛',
                    str_starts_with($type,'promotion')=> '⭐',
                    str_starts_with($type,'verif')    => '✅',
                    default                            => '🔔',
                };
            @endphp
            <div class="card-p flex gap-3 {{ $isRead ? '' : 'ring-1 ring-brand-200 bg-brand-50/30' }}">
                <span class="fs-4 flex-shrink-0 mt-1">{{ $icon }}</span>
                <div class="flex-grow-1">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <p class="font-semibold text-sm text-slate-900 {{ $isRead?'':'text-brand-900' }}">{{ $title }}</p>
                        <span class="fs-xs text-secondary flex-shrink-0">{{ $n->created_at->diffForHumans() }}</span>
                    </div>
                    <p class="fs-sm text-muted mt-1">{{ $message }}</p>
                    <div class="d-flex align-items-center gap-2 mt-2">
                        @if(!$isRead)
                            <form method="POST" action="{{ route('dashboard.notifications.read', $n->id) }}">@csrf
                                <button type="submit" class="fs-xs text-primary">Mark read</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('dashboard.notifications.destroy', $n->id) }}">@csrf @method('DELETE')
                            <button type="submit" class="fs-xs text-secondary">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
        </div>
        <div class="mt-3">{{ $notifications->withQueryString()->links() }}</div>
    @endif
</x-layouts.dashboard>
