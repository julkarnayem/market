@php
    $r = Route::currentRouteName();
    $user = auth()->user();
    $groups = [
        'Overview' => [
            ['admin.dashboard', 'Dashboard', '▦', null],
        ],
        'Marketplace' => [
            ['admin.users',        'Users',        '👥', 'users.view'],
            ['admin.verification', 'Verification', '✅', 'verification.view'],
            ['admin.listings',     'Listings',     '🏷️', 'listings.view'],
            ['admin.categories',   'Categories',   '🗂️', 'categories.manage'],
            ['admin.orders',       'Orders',       '📦', 'orders.view'],
            ['admin.offers',       'Offers',       '🤝', 'offers.view'],
            ['admin.promotions',   'Promotions',   '📣', 'promotions.view'],
        ],
        'Finance' => [
            ['admin.payments',     'Payments',     '💳', 'payments.view'],
            ['admin.wallets',      'Wallets',      '👛', 'wallets.view'],
            ['admin.withdrawals',  'Withdrawals',  '🏦', 'withdrawals.view'],
            ['admin.disputes',     'Disputes',     '⚖️', 'disputes.view'],
            ['admin.reports',      'Reports',      '📊', 'reports.view'],
        ],
        'Support' => [
            ['admin.tickets',      'Support Tickets', '🎧', 'tickets.view'],
            ['admin.notifications','Notifications', '🔔', 'notifications.view'],
            ['admin.sms-logs',       'SMS Logs',        '📱', 'sms.view'],
            ['admin.message-reports', 'Msg Reports',     '🚩', 'disputes.manage'],
            ['admin.support-templates','Templates',       '📝', 'tickets.manage'],
        ],
        'Security' => [
            ['admin.fraud', 'Fraud Review', '🚨', 'users.suspend'],
        ],
        'Staff & System' => [
            ['admin.staff',        'Staff',        '👔', 'staff.view'],
            ['admin.roles',        'Roles',        '🔑', 'roles.view'],
            ['admin.settings',     'Settings',     '⚙️', 'settings.view'],
            ['admin.audit',        'Audit Logs',   '🧾', 'audit.view'],
        ],
    ];
@endphp
<a href="{{ route('admin.dashboard') }}" class="d-flex align-items-center gap-2 px-2 py-1 mb-3">
    <span class="h-8 w-8 d-grid place-items-center rounded-3 bg-primary text-white font-display fw-bold">M</span>
    <span class="font-display fw-bold text-white">Admin</span>
</a>
<nav class="vstack gap-3 fs-sm overflow-y-auto">
    @foreach($groups as $group => $items)
        @php $visible = collect($items)->filter(fn($i) => $i[3] === null || $user->can($i[3])); @endphp
        @if($visible->isNotEmpty())
            <div>
                <p class="px-2 mb-1 fw-semibold text-uppercase text-muted">{{ $group }}</p>
                <div class="space-y-0.5">
                    @foreach($visible as [$route, $label, $icon, $perm])
                        <a href="{{ route($route) }}"
                           class="flex items-center gap-3 rounded-lg px-3 py-2 transition-colors
                                  {{ str_starts_with($r ?? '', $route) ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <span class="w-5 text-center">{{ $icon }}</span>{{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
</nav>
