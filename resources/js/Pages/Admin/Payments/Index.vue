<script setup lang="ts">
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import type { Paginated } from '@/types';

/** One payment row — whitelisted by Admin\PaymentController::index(). */
interface PaymentRow {
    id: number;
    order_number: string;
    order_url: string | null;
    buyer_name: string;
    amount: string;
    gateway: string;
    transaction_id: string;
    status: string;
    paid_at: string;
}
interface StatusOption {
    value: string;
    label: string;
}

const props = defineProps<{
    payments: Paginated<PaymentRow>;
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
        route('admin.payments'),
        {
            q: filterState.q || undefined,
            status: filterState.status === 'all' ? undefined : filterState.status,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}
</script>

<template>
    <AdminLayout title="Payments" heading="Payment Records">
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
                        <th>ID</th>
                        <th>Order #</th>
                        <th>Buyer</th>
                        <th>Amount</th>
                        <th>Gateway</th>
                        <th>TXN ID</th>
                        <th>Status</th>
                        <th>Paid at</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in payments.data" :key="p.id">
                        <td class="font-mono text-xs text-slate-500">#{{ p.id }}</td>
                        <td class="font-mono text-xs">
                            <Link v-if="p.order_url" :href="p.order_url" class="text-brand-600 hover:underline">
                                {{ p.order_number }}
                            </Link>
                            <span v-else>{{ p.order_number }}</span>
                        </td>
                        <td class="text-sm">{{ p.buyer_name }}</td>
                        <td class="money font-semibold">{{ p.amount }}</td>
                        <td class="text-sm">{{ p.gateway }}</td>
                        <td class="max-w-[100px] truncate font-mono text-xs text-slate-500">{{ p.transaction_id }}</td>
                        <td><StatusBadge :status="p.status" /></td>
                        <td class="text-xs text-slate-500">{{ p.paid_at }}</td>
                    </tr>
                    <tr v-if="payments.data.length === 0">
                        <td colspan="8" class="py-4 text-center text-slate-500">No payments found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards -->
        <div class="flex flex-col gap-2 sm:hidden">
            <div v-for="p in payments.data" :key="p.id" class="card-p">
                <div class="flex justify-between gap-2">
                    <Link v-if="p.order_url" :href="p.order_url" class="font-mono text-xs text-brand-600">
                        {{ p.order_number }}
                    </Link>
                    <span v-else class="font-mono text-xs text-slate-500">{{ p.order_number }}</span>
                    <StatusBadge :status="p.status" />
                </div>
                <div class="mt-1 flex justify-between text-xs text-slate-500">
                    <span>{{ p.buyer_name }}</span>
                    <span class="money font-semibold text-slate-900">{{ p.amount }}</span>
                </div>
                <div class="mt-1 flex justify-between text-xs text-slate-400">
                    <span>{{ p.gateway }}</span>
                    <span>{{ p.paid_at }}</span>
                </div>
            </div>
            <p v-if="payments.data.length === 0" class="card-p text-center text-sm text-slate-500">
                No payments found.
            </p>
        </div>

        <div class="mt-3">
            <Pagination :links="payments.links" :total="payments.total" />
        </div>
    </AdminLayout>
</template>
