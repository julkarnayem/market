<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import type { Paginated } from '@/types';

/** One dispute row — whitelisted by Admin\DisputeController::index(). */
interface DisputeRow {
    id: number;
    order_number: string;
    order_url: string | null;
    buyer: string;
    seller: string;
    order_total: string;
    status: string;
    status_label: string;
    opened: string;
    url: string;
}
interface Tab {
    value: string;
    label: string;
}

const props = defineProps<{
    disputes: Paginated<DisputeRow>;
    filters: { status: string };
    tabs: Tab[];
}>();

// Dispute status → badge class. Open needs attention (amber); resolved is settled (mint).
const badgeFor = (status: string): string =>
    ({
        open: 'badge-amber',
        under_review: 'badge-brand',
        waiting_for_buyer: 'badge-slate',
        waiting_for_seller: 'badge-slate',
        resolved: 'badge-mint',
        rejected: 'badge-rose',
        closed: 'badge-slate',
    })[status] ?? 'badge-slate';
</script>

<template>
    <AdminLayout title="Disputes" heading="Dispute Management">
        <!-- Status tabs (Inertia links carrying ?status=) -->
        <div class="mb-3 flex flex-wrap gap-1 border-b border-slate-200">
            <Link
                v-for="t in tabs"
                :key="t.value"
                :href="route('admin.disputes', { status: t.value })"
                class="tab"
                :class="filters.status === t.value && 'tab-active'"
            >
                {{ t.label }}
            </Link>
        </div>

        <!-- Desktop table -->
        <div class="table-wrap hidden sm:block">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Order</th>
                        <th>Buyer</th>
                        <th>Seller</th>
                        <th>Order total</th>
                        <th>Status</th>
                        <th>Opened</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="d in disputes.data" :key="d.id">
                        <td class="font-mono text-xs text-slate-400">#{{ d.id }}</td>
                        <td class="font-mono text-xs">
                            <Link v-if="d.order_url" :href="d.order_url" class="text-brand-600">{{ d.order_number }}</Link>
                            <span v-else>{{ d.order_number }}</span>
                        </td>
                        <td class="text-sm">{{ d.buyer }}</td>
                        <td class="text-sm">{{ d.seller }}</td>
                        <td class="money text-sm font-semibold">{{ d.order_total }}</td>
                        <td><span :class="badgeFor(d.status)">{{ d.status_label }}</span></td>
                        <td class="text-xs text-slate-500">{{ d.opened }}</td>
                        <td><Link :href="d.url" class="btn-ghost btn-sm">Review</Link></td>
                    </tr>
                    <tr v-if="disputes.data.length === 0">
                        <td colspan="8" class="py-4 text-center text-slate-500">No disputes.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards -->
        <div class="flex flex-col gap-2 sm:hidden">
            <Link v-for="d in disputes.data" :key="d.id" :href="d.url" class="card-p block">
                <div class="flex justify-between gap-2">
                    <p class="font-mono text-xs text-slate-500">{{ d.order_number }}</p>
                    <span :class="badgeFor(d.status)">{{ d.status_label }}</span>
                </div>
                <p class="mt-1 text-sm text-slate-900">{{ d.buyer }} vs {{ d.seller }}</p>
                <div class="mt-1 flex justify-between text-xs text-slate-500">
                    <span>{{ d.opened }}</span>
                    <span class="money">{{ d.order_total }}</span>
                </div>
            </Link>
            <p v-if="disputes.data.length === 0" class="card-p text-center text-sm text-slate-500">No disputes.</p>
        </div>

        <div class="mt-3">
            <Pagination :links="disputes.links" :total="disputes.total" />
        </div>
    </AdminLayout>
</template>
