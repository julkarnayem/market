<script setup lang="ts">
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import type { Paginated } from '@/types';

/** One order row — whitelisted by Admin\OrderController::index(). */
interface OrderRow {
    id: number;
    order_number: string;
    asset_title: string;
    buyer_name: string;
    seller_name: string;
    total_formatted: string;
    status: string;
    payment_status: string;
    created: string;
    url: string;
}
interface StatusOption {
    value: string;
    label: string;
}

const props = defineProps<{
    orders: Paginated<OrderRow>;
    filters: { q: string; status: string };
    statuses: StatusOption[];
}>();

// Local mirror of the server-echoed filters so the controls follow the URL.
const filterState = reactive({
    q: props.filters.q,
    status: props.filters.status,
});

function applyFilters() {
    router.get(
        route('admin.orders'),
        {
            q: filterState.q || undefined,
            status: filterState.status === 'all' ? undefined : filterState.status,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}
</script>

<template>
    <AdminLayout title="Orders" heading="Order Management">
        <!-- Filters -->
        <form class="mb-3 flex flex-wrap items-center gap-2" @submit.prevent="applyFilters">
            <input v-model="filterState.q" type="search" placeholder="Order number…" class="input max-w-xs" />
            <select v-model="filterState.status" class="select w-auto" @change="applyFilters">
                <option value="all">All status</option>
                <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
            <button type="submit" class="btn-outline">Filter</button>
        </form>

        <!-- Desktop table -->
        <div class="table-wrap hidden sm:block">
            <table class="table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Asset</th>
                        <th>Buyer</th>
                        <th>Seller</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="o in orders.data" :key="o.id">
                        <td class="font-mono text-xs">{{ o.order_number }}</td>
                        <td class="max-w-[140px] truncate font-medium">{{ o.asset_title }}</td>
                        <td class="text-sm">{{ o.buyer_name }}</td>
                        <td class="text-sm">{{ o.seller_name }}</td>
                        <td class="money font-semibold">{{ o.total_formatted }}</td>
                        <td><StatusBadge :status="o.status" /></td>
                        <td><StatusBadge :status="o.payment_status" /></td>
                        <td class="text-xs text-slate-500">{{ o.created }}</td>
                        <td><Link :href="o.url" class="btn-ghost btn-sm">View</Link></td>
                    </tr>
                    <tr v-if="orders.data.length === 0">
                        <td colspan="9" class="py-4 text-center text-slate-500">No orders found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards -->
        <div class="flex flex-col gap-2 sm:hidden">
            <Link v-for="o in orders.data" :key="o.id" :href="o.url" class="card-p block">
                <div class="flex justify-between gap-2">
                    <p class="font-mono text-xs text-slate-500">{{ o.order_number }}</p>
                    <StatusBadge :status="o.status" />
                </div>
                <p class="mt-1 truncate text-sm font-semibold text-slate-900">{{ o.asset_title }}</p>
                <div class="mt-1 flex justify-between text-xs text-slate-500">
                    <span>{{ o.buyer_name }}</span>
                    <span class="money">{{ o.total_formatted }}</span>
                </div>
            </Link>
            <p v-if="orders.data.length === 0" class="card-p text-center text-sm text-slate-500">No orders found.</p>
        </div>

        <div class="mt-3">
            <Pagination :links="orders.links" :total="orders.total" />
        </div>
    </AdminLayout>
</template>
