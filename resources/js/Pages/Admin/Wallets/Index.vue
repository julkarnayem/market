<script setup lang="ts">
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import type { Paginated } from '@/types';

/** One wallet row — whitelisted by Admin\WalletController::index(). */
interface WalletRow {
    id: number;
    user_name: string;
    user_email: string;
    available: string;
    pending: string;
    total: string;
    url: string;
}

const props = defineProps<{
    wallets: Paginated<WalletRow>;
    filters: { q: string };
}>();

// Local mirror of the server-echoed filter so the control follows the URL.
const filterState = reactive({
    q: props.filters.q,
});

function applyFilters() {
    router.get(
        route('admin.wallets'),
        { q: filterState.q || undefined },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}
</script>

<template>
    <AdminLayout title="Wallets" heading="Wallet Overview">
        <!-- Filter (user search) -->
        <form class="mb-3 flex max-w-sm flex-wrap items-center gap-2" @submit.prevent="applyFilters">
            <input v-model="filterState.q" type="search" placeholder="Search user…" class="input" />
            <button type="submit" class="btn-outline">Filter</button>
        </form>

        <!-- Desktop table -->
        <div class="table-wrap hidden sm:block">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Available</th>
                        <th>Pending</th>
                        <th>Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="w in wallets.data" :key="w.id">
                        <td>
                            <p class="font-medium text-slate-900">{{ w.user_name }}</p>
                            <p class="text-xs text-slate-500">{{ w.user_email }}</p>
                        </td>
                        <td class="money font-semibold text-emerald-600">{{ w.available }}</td>
                        <td class="money text-amber-600">{{ w.pending }}</td>
                        <td class="money font-bold">{{ w.total }}</td>
                        <td><Link :href="w.url" class="btn-ghost btn-sm">View</Link></td>
                    </tr>
                    <tr v-if="wallets.data.length === 0">
                        <td colspan="5" class="py-4 text-center text-slate-500">No wallets.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards -->
        <div class="flex flex-col gap-2 sm:hidden">
            <Link v-for="w in wallets.data" :key="w.id" :href="w.url" class="card-p block">
                <div class="flex justify-between gap-2">
                    <p class="font-medium text-slate-900">{{ w.user_name }}</p>
                    <span class="money font-bold">{{ w.total }}</span>
                </div>
                <div class="mt-1 flex justify-between text-xs">
                    <span class="money text-emerald-600">{{ w.available }}</span>
                    <span class="money text-amber-600">{{ w.pending }}</span>
                </div>
            </Link>
            <p v-if="wallets.data.length === 0" class="card-p text-center text-sm text-slate-500">No wallets.</p>
        </div>

        <div class="mt-3">
            <Pagination :links="wallets.links" :total="wallets.total" />
        </div>
    </AdminLayout>
</template>
