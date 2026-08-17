<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import type { Paginated } from '@/types';

/** One row of the purchases list — whitelisted by DashboardController::purchases(). */
interface PurchaseRow {
    order_number: string;
    asset_title: string;
    seller_name: string;
    total_formatted: string;
    status: string;
    date: string;
    show_url: string;
}

defineProps<{
    orders: Paginated<PurchaseRow>;
    /** Currently-active tab key. */
    tab: string;
    /** tab key -> label, in display order. */
    statuses: Record<string, string>;
}>();
</script>

<template>
    <DashboardLayout title="My Purchases" heading="My Purchases">
        <div class="tabs mb-3 overflow-x-auto whitespace-nowrap">
            <Link
                v-for="(label, value) in statuses"
                :key="value"
                :href="route('dashboard.purchases', { tab: value })"
                class="tab"
                :class="{ 'tab-active': tab === value }"
            >
                {{ label }}
            </Link>
        </div>

        <div v-if="orders.data.length === 0" class="card-p text-center">
            <p class="mb-2 text-4xl">🛍️</p>
            <h2 class="font-display text-lg font-bold text-slate-900">No purchases yet</h2>
            <p class="mt-1 text-sm text-slate-500">
                Browse the marketplace to find digital assets to purchase.
            </p>
            <Link :href="route('marketplace.index')" class="btn-primary mt-4">Browse marketplace</Link>
        </div>

        <template v-else>
            <!-- Desktop table -->
            <div class="table-wrap hidden sm:block">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Asset</th>
                            <th>Seller</th>
                            <th>Total paid</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="o in orders.data" :key="o.order_number">
                            <td class="font-mono text-xs text-slate-500">{{ o.order_number }}</td>
                            <td class="max-w-[160px] truncate font-medium">{{ o.asset_title }}</td>
                            <td class="text-sm text-slate-500">{{ o.seller_name }}</td>
                            <td class="money font-semibold">{{ o.total_formatted }}</td>
                            <td><StatusBadge :status="o.status" /></td>
                            <td class="text-xs text-slate-500">{{ o.date }}</td>
                            <td><Link :href="o.show_url" class="btn-ghost btn-sm">View</Link></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <div class="flex flex-col gap-2 sm:hidden">
                <Link
                    v-for="o in orders.data"
                    :key="o.order_number"
                    :href="o.show_url"
                    class="card-p block"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="truncate font-semibold text-slate-900">{{ o.asset_title }}</p>
                            <p class="mt-1 font-mono text-xs text-slate-500">{{ o.order_number }}</p>
                        </div>
                        <StatusBadge :status="o.status" />
                    </div>
                    <div class="mt-2 flex justify-between text-sm">
                        <span class="text-slate-500">{{ o.seller_name }}</span>
                        <span class="money font-bold">{{ o.total_formatted }}</span>
                    </div>
                </Link>
            </div>

            <div class="mt-3">
                <Pagination :links="orders.links" :total="orders.total" />
            </div>
        </template>
    </DashboardLayout>
</template>
