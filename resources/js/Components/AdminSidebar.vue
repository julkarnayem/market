<script setup lang="ts">
/**
 * Admin section navigation — dark rail. Ported from partials/admin-sidebar.blade.php.
 *
 * Every destination except the dashboard is still a Blade page, so links are
 * plain <a href> full-page loads, not Inertia <Link>. As each admin page
 * migrates to Inertia its link can become a <Link>; until then a hard visit is
 * the correct coexistence behaviour.
 *
 * Visibility mirrors the server Gate: an item with no permission is always
 * shown; a super-admin (role literally named 'admin') sees everything;
 * otherwise the user must hold the item's permission. Laravel stays
 * authoritative — this only decides what the UI *shows*, never what it allows.
 */
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

type NavItem = { route: string; label: string; icon: string; perm: string | null };
type NavGroup = { label: string; items: NavItem[] };

const groups: NavGroup[] = [
    {
        label: 'Overview',
        items: [{ route: 'admin.dashboard', label: 'Dashboard', icon: '▦', perm: null }],
    },
    {
        label: 'Marketplace',
        items: [
            { route: 'admin.users', label: 'Users', icon: '👥', perm: 'users.view' },
            { route: 'admin.verification', label: 'Verification', icon: '✅', perm: 'verification.view' },
            { route: 'admin.listings', label: 'Listings', icon: '🏷️', perm: 'listings.view' },
            { route: 'admin.categories', label: 'Categories', icon: '🗂️', perm: 'categories.manage' },
            { route: 'admin.orders', label: 'Orders', icon: '📦', perm: 'orders.view' },
            { route: 'admin.offers', label: 'Offers', icon: '🤝', perm: 'offers.view' },
            { route: 'admin.promotions', label: 'Promotions', icon: '📣', perm: 'promotions.view' },
        ],
    },
    {
        label: 'Finance',
        items: [
            { route: 'admin.payments', label: 'Payments', icon: '💳', perm: 'payments.view' },
            { route: 'admin.wallets', label: 'Wallets', icon: '👛', perm: 'wallets.view' },
            { route: 'admin.withdrawals', label: 'Withdrawals', icon: '🏦', perm: 'withdrawals.view' },
            { route: 'admin.disputes', label: 'Disputes', icon: '⚖️', perm: 'disputes.view' },
            { route: 'admin.reports', label: 'Reports', icon: '📊', perm: 'reports.view' },
        ],
    },
    {
        label: 'Support',
        items: [
            { route: 'admin.tickets', label: 'Support Tickets', icon: '🎧', perm: 'tickets.view' },
            { route: 'admin.notifications', label: 'Notifications', icon: '🔔', perm: 'notifications.view' },
            { route: 'admin.sms-logs', label: 'SMS Logs', icon: '📱', perm: 'sms.view' },
            { route: 'admin.message-reports', label: 'Msg Reports', icon: '🚩', perm: 'disputes.manage' },
            { route: 'admin.support-templates', label: 'Templates', icon: '📝', perm: 'tickets.manage' },
        ],
    },
    {
        label: 'Security',
        items: [{ route: 'admin.fraud', label: 'Fraud Review', icon: '🚨', perm: 'users.suspend' }],
    },
    {
        label: 'Staff & System',
        items: [
            { route: 'admin.staff', label: 'Staff', icon: '👔', perm: 'staff.view' },
            { route: 'admin.roles', label: 'Roles', icon: '🔑', perm: 'roles.view' },
            { route: 'admin.settings', label: 'Settings', icon: '⚙️', perm: 'settings.view' },
            { route: 'admin.audit', label: 'Audit Logs', icon: '🧾', perm: 'audit.view' },
        ],
    },
];

const user = computed(() => usePage().props.auth.user);
const isSuperAdmin = computed(() => user.value?.roles.includes('admin') ?? false);

function canSee(perm: string | null): boolean {
    if (perm === null) return true;
    if (!user.value) return false;
    return isSuperAdmin.value || user.value.permissions.includes(perm);
}

const visibleGroups = computed(() =>
    groups
        .map((g) => ({ label: g.label, items: g.items.filter((i) => canSee(i.perm)) }))
        .filter((g) => g.items.length > 0),
);

// Prefix match so admin.users.show still lights up the "Users" item, mirroring
// the Blade's str_starts_with(currentRouteName, item.route).
const currentName = computed(() => route().current() || '');
const isActive = (item: NavItem) => currentName.value.startsWith(item.route);
</script>

<template>
    <a :href="route('admin.dashboard')" class="mb-4 flex items-center gap-2 px-2">
        <span class="grid h-8 w-8 place-items-center rounded-lg bg-brand-600 font-display font-bold text-white">M</span>
        <span class="font-display font-bold text-white">Admin</span>
    </a>
    <nav class="flex flex-col gap-4 text-sm" aria-label="Admin sections">
        <div v-for="group in visibleGroups" :key="group.label">
            <p class="mb-1 px-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ group.label }}</p>
            <div class="space-y-0.5">
                <a
                    v-for="item in group.items"
                    :key="item.route"
                    :href="route(item.route)"
                    class="flex items-center gap-3 rounded-lg px-3 py-2 transition-colors"
                    :class="isActive(item) ? 'bg-brand-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white'"
                    :aria-current="isActive(item) ? 'page' : undefined"
                >
                    <span class="w-5 flex-shrink-0 text-center" aria-hidden="true">{{ item.icon }}</span>
                    <span>{{ item.label }}</span>
                </a>
            </div>
        </div>
    </nav>
</template>
