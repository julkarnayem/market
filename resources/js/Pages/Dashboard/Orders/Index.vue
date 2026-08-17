<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import type { Paginated } from '@/types';

/** One order row — whitelisted by OrderController::index(). */
interface OrderRow {
    id: number;
    order_number: string;
    asset_title: string;
    /** Counterparty name — seller on the buyer view, buyer on the seller view. */
    party_name: string;
    quantity: number;
    total_formatted: string;
    status: string;
    payment_status: string;
    date: string;
}

const props = defineProps<{
    /** buyer | seller. */
    role: string;
    tab: string;
    orders: Paginated<OrderRow>;
}>();

const ROLES = [
    { key: 'buyer', label: 'My Purchases' },
    { key: 'seller', label: 'My Sales' },
] as const;

const STATUSES = [
    { key: 'all', label: 'All' },
    { key: 'pending_payment', label: 'Pending' },
    { key: 'delivery_pending', label: 'Delivery Pending' },
    { key: 'delivered', label: 'Delivered' },
    { key: 'completed', label: 'Completed' },
    { key: 'disputed', label: 'Disputed' },
    { key: 'refunded', label: 'Refunded' },
] as const;
</script>

<template>
    <DashboardLayout title="Orders" heading="Orders">
        <!-- Role toggle -->
        <div class="mb-3 flex items-center gap-2">
            <Link
                v-for="r in ROLES"
                :key="r.key"
                :href="route('dashboard.orders', { role: r.key, tab })"
                :class="role === r.key ? 'btn-primary btn-sm' : 'btn-outline btn-sm'"
            >
                {{ r.label }}
            </Link>
        </div>

        <!-- Status tabs -->
        <div class="tabs mb-3 overflow-x-auto whitespace-nowrap">
            <Link
                v-for="s in STATUSES"
                :key="s.key"
                :href="route('dashboard.orders', { role, tab: s.key })"
                class="tab"
                :class="tab === s.key && 'tab-active'"
            >
                {{ s.label }}
            </Link>
        </div>

        <div v-if="orders.data.length === 0" class="card-p text-center">
            <p class="mb-2 text-4xl">{{ role === 'seller' ? '📦' : '🛍️' }}</p>
            <h2 class="font-display text-lg font-bold text-slate-900">
                {{ role === 'seller' ? 'No sales yet' : 'No purchases yet' }}
            </h2>
            <Link
                v-if="role === 'buyer'"
                :href="route('marketplace.index')"
                class="btn-outline mt-3"
            >
                Browse marketplace
            </Link>
        </div>

        <template v-else>
            <!-- Desktop table -->
            <div class="table-wrap hidden sm:block">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Asset</th>
                            <th>{{ role === 'seller' ? 'Buyer' : 'Seller' }}</th>
                            <th>Qty</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="o in orders.data" :key="o.id">
                            <td class="font-mono text-xs text-slate-500">{{ o.order_number }}</td>
                            <td class="max-w-[160px] truncate font-medium">{{ o.asset_title }}</td>
                            <td class="text-sm">{{ o.party_name }}</td>
                            <td>{{ o.quantity }}</td>
                            <td class="money font-semibold">{{ o.total_formatted }}</td>
                            <td><StatusBadge :status="o.status" /></td>
                            <td><StatusBadge :status="o.payment_status" /></td>
                            <td class="text-xs text-slate-500">{{ o.date }}</td>
                            <td>
                                <Link :href="route('dashboard.orders.show', o.id)" class="btn-ghost btn-sm">
                                    View
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <div class="flex flex-col gap-2 sm:hidden">
                <Link
                    v-for="o in orders.data"
                    :key="o.id"
                    :href="route('dashboard.orders.show', o.id)"
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
                        <span class="text-slate-500">{{ o.date }}</span>
                        <span class="money font-bold text-slate-900">{{ o.total_formatted }}</span>
                    </div>
                </Link>
            </div>

            <div class="mt-3">
                <Pagination :links="orders.links" :total="orders.total" />
            </div>
        </template>
    </DashboardLayout>
</template>
