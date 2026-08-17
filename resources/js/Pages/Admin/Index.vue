<script setup lang="ts">
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

/** Prices arrive pre-formatted (App\Support\Money owns currency); counts are
 *  plain integers, formatted client-side with toLocaleString for thousands. */
interface Stats {
    users: number;
    active_users: number;
    published_listings: number;
    orders_month: number;
    revenue_month_formatted: string;
    active_promotions: number;
    open_tickets: number;
    unassigned_tickets: number;
    pending_verifications: number;
    pending_listings: number;
    open_disputes: number;
    pending_withdrawals: number;
    approved_withdrawals: number;
    suspended_users: number;
}
interface RecentOrder {
    id: number;
    order_number: string;
    asset_title: string;
    total_formatted: string;
    url: string;
}
interface RecentTicket {
    id: number;
    subject: string;
    priority_label: string;
    priority_color: string;
    user_name: string;
    url: string;
}

const props = defineProps<{
    stats: Stats;
    recentOrders: RecentOrder[];
    recentTickets: RecentTicket[];
}>();

const num = (n: number) => n.toLocaleString('en-US');

// The "Verified sellers" card historically shows active (non-staff) users, not
// the verified-seller count — preserved verbatim from admin/index.blade.php.
const quickStats = computed(() => [
    { label: 'Users', value: props.stats.users, icon: '👥' },
    { label: 'Verified sellers', value: props.stats.active_users, icon: '✅' },
    { label: 'Published listings', value: props.stats.published_listings, icon: '🏷️' },
    { label: 'Orders this month', value: props.stats.orders_month, icon: '📦' },
]);

const needsAttention = computed(() => [
    { label: 'Pending verification', value: props.stats.pending_verifications, href: route('admin.verification'), tone: 'text-brand-700' },
    { label: 'Pending listings', value: props.stats.pending_listings, href: route('admin.listings'), tone: 'text-brand-700' },
    { label: 'Open disputes', value: props.stats.open_disputes, href: route('admin.disputes'), tone: 'text-brand-700' },
    { label: 'Pending withdrawals', value: props.stats.pending_withdrawals, href: route('admin.withdrawals'), tone: 'text-brand-700' },
    { label: 'Approved withdrawals (to process)', value: props.stats.approved_withdrawals, href: route('admin.withdrawals', { status: 'approved' }), tone: 'text-amber-600' },
    { label: 'Suspended users', value: props.stats.suspended_users, href: route('admin.users', { status: 'suspended' }), tone: 'text-slate-900' },
]);
</script>

<template>
    <AdminLayout title="Dashboard" heading="Platform overview">
        <!-- Quick stats -->
        <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
            <div v-for="s in quickStats" :key="s.label" class="card-p">
                <p class="text-xs font-medium text-slate-500">{{ s.icon }} {{ s.label }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ num(s.value) }}</p>
            </div>
        </div>

        <!-- Revenue + promotions + tickets -->
        <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">
            <div class="card-p bg-emerald-50">
                <p class="text-xs font-medium text-emerald-700">💰 Commission this month</p>
                <p class="mt-1 text-2xl font-bold text-emerald-700">{{ stats.revenue_month_formatted }}</p>
                <a :href="route('admin.reports')" class="mt-1 inline-block text-xs text-emerald-700 hover:underline">Full report →</a>
            </div>
            <div class="card-p bg-amber-50">
                <p class="text-xs font-medium text-amber-700">⚠ Active promotions</p>
                <p class="mt-1 text-2xl font-bold text-amber-700">{{ num(stats.active_promotions) }}</p>
            </div>
            <div class="card-p">
                <p class="text-xs font-medium text-slate-500">🎧 Open tickets</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ num(stats.open_tickets) }}</p>
                <p v-if="stats.unassigned_tickets > 0" class="mt-1 text-xs text-rose-600">
                    {{ stats.unassigned_tickets }} unassigned
                </p>
            </div>
        </div>

        <!-- Action queues -->
        <div class="mb-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
            <div class="card-p">
                <h2 class="mb-2 font-semibold text-slate-900">Needs attention</h2>
                <ul class="divide-y divide-slate-100 text-sm">
                    <li v-for="row in needsAttention" :key="row.label" class="flex justify-between py-2">
                        <span class="text-slate-600">{{ row.label }}</span>
                        <a :href="row.href" class="font-medium hover:underline" :class="row.tone">{{ num(row.value) }} →</a>
                    </li>
                </ul>
            </div>
            <div class="card-p">
                <h2 class="mb-2 font-semibold text-slate-900">Recent paid orders</h2>
                <template v-if="recentOrders.length">
                    <div
                        v-for="o in recentOrders"
                        :key="o.id"
                        class="flex items-center gap-2 border-b border-slate-100 py-1.5 text-sm last:border-0"
                    >
                        <a :href="o.url" class="font-mono text-xs text-brand-700 hover:underline">{{ o.order_number }}</a>
                        <span class="flex-1 truncate text-slate-500">{{ o.asset_title }}</span>
                        <span class="font-medium text-slate-900">{{ o.total_formatted }}</span>
                    </div>
                </template>
                <p v-else class="text-sm text-slate-500">No recent orders.</p>
            </div>
        </div>

        <!-- Recent tickets -->
        <div v-if="recentTickets.length" class="card-p">
            <div class="mb-2 flex items-center justify-between">
                <h2 class="font-semibold text-slate-900">Open support tickets</h2>
                <a :href="route('admin.tickets')" class="text-xs text-brand-700 hover:underline">View all →</a>
            </div>
            <div class="flex flex-col gap-2">
                <div v-for="t in recentTickets" :key="t.id" class="flex items-center gap-2 text-sm">
                    <span class="shrink-0 text-[10px]" :class="`badge-${t.priority_color}`">{{ t.priority_label }}</span>
                    <a :href="t.url" class="flex-1 truncate text-slate-700 hover:underline">{{ t.subject }}</a>
                    <span class="text-xs text-slate-400">{{ t.user_name }}</span>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
