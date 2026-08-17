<script setup lang="ts">
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import type { Paginated } from '@/types';

/** One offer row — whitelisted by Admin\OfferController::index(). */
interface OfferRow {
    id: number;
    asset_title: string;
    buyer_name: string;
    seller_name: string;
    amount: string;
    status: string;
    expires: string;
    created: string;
}
interface StatusOption {
    value: string;
    label: string;
}

const props = defineProps<{
    offers: Paginated<OfferRow>;
    filters: { status: string };
    statuses: StatusOption[];
}>();

// Local mirror of the server-echoed filter so the control follows the URL.
const filterState = reactive({
    status: props.filters.status,
});

function applyFilters() {
    router.get(
        route('admin.offers'),
        { status: filterState.status === 'all' ? undefined : filterState.status },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}
</script>

<template>
    <AdminLayout title="Offers" heading="Offer Management">
        <!-- Filter (status only — offers have no search box) -->
        <form class="mb-3 flex flex-wrap items-center gap-2" @submit.prevent="applyFilters">
            <select v-model="filterState.status" class="select w-auto" @change="applyFilters">
                <option value="all">All</option>
                <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
        </form>

        <!-- Desktop table -->
        <div class="table-wrap hidden sm:block">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Asset</th>
                        <th>Buyer</th>
                        <th>Seller</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Expires</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="o in offers.data" :key="o.id">
                        <td class="font-mono text-xs text-slate-500">#{{ o.id }}</td>
                        <td class="max-w-[140px] truncate font-medium">{{ o.asset_title }}</td>
                        <td class="text-sm">{{ o.buyer_name }}</td>
                        <td class="text-sm">{{ o.seller_name }}</td>
                        <td class="money font-semibold">{{ o.amount }}</td>
                        <td><StatusBadge :status="o.status" /></td>
                        <td class="text-xs text-slate-500">{{ o.expires }}</td>
                        <td class="text-xs text-slate-500">{{ o.created }}</td>
                    </tr>
                    <tr v-if="offers.data.length === 0">
                        <td colspan="8" class="py-4 text-center text-slate-500">No offers found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards -->
        <div class="flex flex-col gap-2 sm:hidden">
            <div v-for="o in offers.data" :key="o.id" class="card-p">
                <div class="flex justify-between gap-2">
                    <p class="truncate font-medium text-slate-900">{{ o.asset_title }}</p>
                    <StatusBadge :status="o.status" />
                </div>
                <div class="mt-2 flex justify-between text-xs text-slate-500">
                    <span>{{ o.buyer_name }}</span>
                    <span class="money font-bold text-slate-900">{{ o.amount }}</span>
                </div>
            </div>
            <p v-if="offers.data.length === 0" class="card-p text-center text-sm text-slate-500">No offers found.</p>
        </div>

        <div class="mt-3">
            <Pagination :links="offers.links" :total="offers.total" />
        </div>
    </AdminLayout>
</template>
