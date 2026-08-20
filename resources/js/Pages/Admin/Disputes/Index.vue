<script setup lang="ts">
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import type { Paginated } from '@/types';

/** One dispute row — whitelisted by Admin\DisputeController::index(). */
interface DisputeRow {
    id: number;
    reference: string;
    order_number: string;
    order_url: string | null;
    buyer: string;
    seller: string;
    order_total: string;
    reason: string;
    status: string;
    status_label: string;
    /** A party asked us to decide it — these jump the queue. */
    is_escalated: boolean;
    opened: string;
    activity: string;
    url: string;
}
interface Tab {
    value: string;
    label: string;
}

const props = defineProps<{
    disputes: Paginated<DisputeRow>;
    filters: { status: string; q: string };
    tabs: Tab[];
}>();

// Local mirror of the server-echoed filters so the search box follows the URL.
const filterState = reactive({ q: props.filters.q });

function applyFilters(): void {
    router.get(
        route('admin.disputes'),
        {
            status: props.filters.status,
            q: filterState.q || undefined,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}
</script>

<template>
    <AdminLayout title="Disputes" heading="Dispute Management">
        <!-- Status tabs (Inertia links carrying ?status=), search kept across them -->
        <div class="mb-3 flex flex-wrap gap-1 border-b border-slate-200">
            <Link
                v-for="t in tabs"
                :key="t.value"
                :href="route('admin.disputes', { status: t.value, q: filters.q || undefined })"
                class="tab"
                :class="filters.status === t.value && 'tab-active'"
            >
                {{ t.label }}
            </Link>
        </div>

        <form class="mb-3 flex flex-wrap items-center gap-2" @submit.prevent="applyFilters">
            <input
                v-model="filterState.q"
                type="search"
                placeholder="Search by dispute ref or order number…"
                class="input max-w-xs"
            />
            <button type="submit" class="btn-outline">Search</button>
        </form>

        <!-- Desktop table -->
        <div class="table-wrap hidden sm:block">
            <table class="table">
                <thead>
                    <tr>
                        <th>Ref</th>
                        <th>Order</th>
                        <th>Buyer</th>
                        <th>Seller</th>
                        <th>Reason</th>
                        <th>Order total</th>
                        <th>Status</th>
                        <th>Last activity</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="d in disputes.data" :key="d.id" :class="d.is_escalated && 'bg-rose-50/60'">
                        <td class="font-mono text-xs font-semibold">{{ d.reference }}</td>
                        <td class="font-mono text-xs">
                            <Link v-if="d.order_url" :href="d.order_url" class="text-brand-600">
                                {{ d.order_number }}
                            </Link>
                            <span v-else>{{ d.order_number }}</span>
                        </td>
                        <td class="text-sm">{{ d.buyer }}</td>
                        <td class="text-sm">{{ d.seller }}</td>
                        <td class="text-sm text-slate-500">{{ d.reason }}</td>
                        <td class="money text-sm font-semibold">{{ d.order_total }}</td>
                        <td><StatusBadge :status="d.status" /></td>
                        <td class="text-xs text-slate-500">{{ d.activity }}</td>
                        <td><Link :href="d.url" class="btn-ghost btn-sm">Review</Link></td>
                    </tr>
                    <tr v-if="disputes.data.length === 0">
                        <td colspan="9" class="py-4 text-center text-slate-500">No disputes.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards -->
        <div class="flex flex-col gap-2 sm:hidden">
            <Link v-for="d in disputes.data" :key="d.id" :href="d.url" class="card-p block">
                <div class="flex justify-between gap-2">
                    <p class="font-mono text-xs font-semibold text-slate-900">{{ d.reference }}</p>
                    <StatusBadge :status="d.status" />
                </div>
                <p class="mt-1 text-sm text-slate-900">{{ d.buyer }} vs {{ d.seller }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ d.reason }} · {{ d.order_number }}</p>
                <div class="mt-1 flex justify-between text-xs text-slate-500">
                    <span>{{ d.activity }}</span>
                    <span class="money">{{ d.order_total }}</span>
                </div>
            </Link>
            <p v-if="disputes.data.length === 0" class="card-p text-center text-sm text-slate-500">
                No disputes.
            </p>
        </div>

        <div class="mt-3">
            <Pagination :links="disputes.links" :total="disputes.total" />
        </div>
    </AdminLayout>
</template>
