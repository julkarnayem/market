<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import type { Paginated } from '@/types';

/** One wallet transaction row — whitelisted by WalletController::index(). */
interface TransactionRow {
    id: number;
    /** created_at, "d M Y, H:i" — desktop table. */
    datetime: string;
    /** created_at, "d M Y" — mobile card. */
    date: string;
    /** ucwords of the TransactionType value, e.g. "Seller Earning Released". */
    type_label: string;
    /** amount > 0 — drives badge/amount colour. */
    is_credit: boolean;
    /** Signed, symbol-included, e.g. "+৳50.00" / "৳-20.00". */
    amount_formatted: string;
    balance_after_formatted: string;
    description: string | null;
}

defineProps<{
    stats: {
        available_formatted: string;
        pending_formatted: string;
        earned_formatted: string;
        withdrawn_formatted: string;
    };
    transactions: Paginated<TransactionRow>;
}>();
</script>

<template>
    <DashboardLayout title="Wallet" heading="My Wallet">
        <!-- Balance cards -->
        <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="stat-card bg-mint-50">
                <p class="stat-label !text-mint-700">Available</p>
                <p class="stat-value money !text-mint-700">{{ stats.available_formatted }}</p>
                <Link
                    :href="route('dashboard.withdrawals')"
                    class="mt-1 inline-block text-xs text-mint-700 hover:underline"
                >
                    Withdraw →
                </Link>
            </div>
            <div class="stat-card bg-amber-50">
                <p class="stat-label !text-amber-600">Pending (locked)</p>
                <p class="stat-value money !text-amber-600">{{ stats.pending_formatted }}</p>
                <p class="mt-1 text-xs text-amber-600">Released 8h after order</p>
            </div>
            <div class="stat-card">
                <p class="stat-label">Total earned</p>
                <p class="stat-value money">{{ stats.earned_formatted }}</p>
            </div>
            <div class="stat-card">
                <p class="stat-label">Total withdrawn</p>
                <p class="stat-value money">{{ stats.withdrawn_formatted }}</p>
            </div>
        </div>

        <div class="mb-3 flex items-center justify-between">
            <h2 class="section-title">Transaction History</h2>
            <Link :href="route('dashboard.withdrawals')" class="btn-outline btn-sm">Request withdrawal</Link>
        </div>

        <div v-if="transactions.data.length === 0" class="card-p text-center">
            <p class="mb-2 text-4xl">💳</p>
            <h2 class="font-display text-lg font-bold text-slate-900">No transactions yet</h2>
            <p class="mt-1 text-sm text-slate-500">
                Your wallet activity — earnings, purchases and withdrawals — will appear here.
            </p>
        </div>

        <template v-else>
            <!-- Desktop table -->
            <div class="table-wrap hidden sm:block">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th class="text-right">Amount</th>
                            <th class="text-right">Balance after</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="tx in transactions.data" :key="tx.id">
                            <td class="text-xs text-slate-500">{{ tx.datetime }}</td>
                            <td>
                                <span class="text-xs" :class="tx.is_credit ? 'badge-mint' : 'badge-rose'">
                                    {{ tx.type_label }}
                                </span>
                            </td>
                            <td class="max-w-xs truncate text-sm text-slate-500">{{ tx.description ?? '—' }}</td>
                            <td
                                class="money text-right font-semibold"
                                :class="tx.is_credit ? 'text-mint-700' : 'text-rose-600'"
                            >
                                {{ tx.amount_formatted }}
                            </td>
                            <td class="money text-right text-slate-500">{{ tx.balance_after_formatted }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <div class="flex flex-col gap-2 sm:hidden">
                <div v-for="tx in transactions.data" :key="tx.id" class="card-p">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <span class="text-xs" :class="tx.is_credit ? 'badge-mint' : 'badge-rose'">
                                {{ tx.type_label }}
                            </span>
                            <p class="mt-1 text-xs text-slate-500">{{ tx.date }}</p>
                        </div>
                        <span class="money font-bold" :class="tx.is_credit ? 'text-mint-700' : 'text-rose-600'">
                            {{ tx.amount_formatted }}
                        </span>
                    </div>
                    <p v-if="tx.description" class="mt-1 text-xs text-slate-500">{{ tx.description }}</p>
                </div>
            </div>

            <div class="mt-3">
                <Pagination :links="transactions.links" :total="transactions.total" />
            </div>
        </template>
    </DashboardLayout>
</template>
