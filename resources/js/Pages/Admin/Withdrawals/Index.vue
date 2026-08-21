<script setup lang="ts">
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import WithdrawalActions from '@/Components/WithdrawalActions.vue';
import type { Paginated } from '@/types';

/** One withdrawal row — whitelisted by Admin\WithdrawalController::index(). */
interface WithdrawalRow {
    id: number;
    /** Buyer-facing handle, e.g. WD-10007. */
    reference: string;
    user_name: string;
    user_email: string;
    /** Which side of the marketplace the balance came from. */
    user_role: string;
    amount_formatted: string;
    fee_formatted: string;
    net_formatted: string;
    provider: string;
    account: string;
    status: string;
    status_label: string;
    created: string;
    url: string;
}
interface StatusOption {
    value: string;
    label: string;
}

const props = defineProps<{
    withdrawals: Paginated<WithdrawalRow>;
    filters: { q: string; status: string };
    statuses: StatusOption[];
}>();

const filterState = reactive({
    q: props.filters.q,
    status: props.filters.status,
});

function applyFilters() {
    router.get(
        route('admin.withdrawals'),
        {
            q: filterState.q || undefined,
            status: filterState.status === 'pending' ? undefined : filterState.status,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}
</script>

<template>
    <AdminLayout title="Withdrawals" heading="Withdrawal Requests">
        <!-- Filters -->
        <form class="mb-3 flex flex-wrap items-center gap-2" @submit.prevent="applyFilters">
            <input v-model="filterState.q" type="search" placeholder="User name or email…" class="input max-w-xs" />
            <select v-model="filterState.status" class="select w-auto" @change="applyFilters">
                <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
            <button type="submit" class="btn-outline">Filter</button>
        </form>

        <!-- Desktop table -->
        <div class="table-wrap hidden sm:block">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Amount</th>
                        <th>Fee</th>
                        <th>Net</th>
                        <th>Provider</th>
                        <th>Account</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="w in withdrawals.data" :key="w.id">
                        <td>
                            <Link :href="w.url" class="font-mono text-xs text-slate-500 hover:text-brand-600">
                                {{ w.reference }}
                            </Link>
                        </td>
                        <td>
                            <p class="text-sm font-medium text-slate-900">{{ w.user_name }}</p>
                            <p class="text-xs text-slate-500">{{ w.user_email }}</p>
                            <p class="text-xs font-semibold text-brand-600">{{ w.user_role }}</p>
                        </td>
                        <td class="money font-semibold">{{ w.amount_formatted }}</td>
                        <td class="money text-xs text-rose-600">{{ w.fee_formatted }}</td>
                        <td class="money font-bold text-emerald-600">{{ w.net_formatted }}</td>
                        <td class="text-xs font-semibold uppercase">{{ w.provider }}</td>
                        <td class="font-mono text-xs">{{ w.account }}</td>
                        <td><StatusBadge :status="w.status" :label="w.status_label" /></td>
                        <td class="text-xs text-slate-500">{{ w.created }}</td>
                        <td><WithdrawalActions :id="w.id" :status="w.status" /></td>
                    </tr>
                    <tr v-if="withdrawals.data.length === 0">
                        <td colspan="10" class="py-4 text-center text-slate-500">No withdrawals found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards -->
        <div class="flex flex-col gap-2 sm:hidden">
            <div v-for="w in withdrawals.data" :key="w.id" class="card-p flex flex-col gap-2">
                <div class="flex justify-between gap-2">
                    <div>
                        <Link :href="w.url" class="font-semibold text-slate-900">{{ w.user_name }}</Link>
                        <p class="text-xs text-slate-500">{{ w.provider }} · {{ w.account }}</p>
                    </div>
                    <StatusBadge :status="w.status" :label="w.status_label" />
                </div>
                <div class="flex justify-between">
                    <span class="money font-bold text-emerald-600">{{ w.net_formatted }}</span>
                    <span class="text-xs text-slate-500">{{ w.created }}</span>
                </div>
                <div class="border-t border-slate-100 pt-2">
                    <WithdrawalActions :id="w.id" :status="w.status" />
                </div>
            </div>
            <p v-if="withdrawals.data.length === 0" class="card-p text-center text-sm text-slate-500">
                No withdrawals found.
            </p>
        </div>

        <div class="mt-3">
            <Pagination :links="withdrawals.links" :total="withdrawals.total" />
        </div>
    </AdminLayout>
</template>
