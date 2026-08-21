<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import Pagination from '@/Components/Pagination.vue';
import type { Paginated } from '@/types';

interface WalletDetail {
    id: number;
    user_name: string;
    user_email: string;
    available: string;
    pending: string;
    total: string;
}
/** One ledger row — whitelisted by Admin\WalletController::show(). */
interface LedgerRow {
    id: number;
    date: string;
    type: string;
    amount_formatted: string;
    is_credit: boolean;
    available_after: string;
    description: string;
}

const props = defineProps<{
    wallet: WalletDetail;
    transactions: Paginated<LedgerRow>;
}>();

// Manual adjustment authorizes payments.view server-side (same perm as the page).
const canAdjust = computed(() => {
    const u = usePage().props.auth.user;
    if (!u) return false;
    return u.roles.includes('admin') || u.permissions.includes('payments.view');
});

const amount = ref('');
const reason = ref('');
const processing = ref(false);

// Guard client-side so the server's validation (reason >= 10, non-zero) never 422s.
const canSubmit = computed(
    () =>
        !processing.value &&
        amount.value.trim() !== '' &&
        Number(amount.value) !== 0 &&
        reason.value.trim().length >= 10,
);

function submitAdjustment() {
    processing.value = true;
    router.post(
        route('admin.wallets.adjust', props.wallet.id),
        { amount_bdt: amount.value, reason: reason.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                amount.value = '';
                reason.value = '';
            },
            onFinish: () => (processing.value = false),
        },
    );
}

const balanceTiles = computed(() => [
    { label: 'Available', value: props.wallet.available, tone: 'text-mint-600 bg-mint-50' },
    { label: 'Pending', value: props.wallet.pending, tone: 'text-amber-600 bg-amber-50' },
    { label: 'Total', value: props.wallet.total, tone: 'text-slate-900 bg-slate-100' },
]);
</script>

<template>
    <AdminLayout :title="`${wallet.user_name}'s Wallet`" heading="Wallet Detail">
        <Breadcrumb
            :items="[{ label: 'Wallets', url: route('admin.wallets') }, { label: wallet.user_name }]"
        />

        <div class="grid gap-4 lg:grid-cols-[1fr_20rem]">
            <!-- Main column -->
            <div class="flex flex-col gap-3">
                <!-- Balance -->
                <div class="card-p">
                    <h2 class="section-title mb-3">Balance</h2>
                    <div class="grid grid-cols-3 gap-3">
                        <div v-for="t in balanceTiles" :key="t.label" class="rounded-lg p-3" :class="t.tone">
                            <p class="text-xs font-semibold">{{ t.label }}</p>
                            <p class="money mt-1 text-lg font-bold">{{ t.value }}</p>
                        </div>
                    </div>
                </div>

                <!-- Transaction ledger -->
                <div class="card-p">
                    <h2 class="section-title mb-2">Transaction Ledger</h2>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Available after</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="tx in transactions.data" :key="tx.id">
                                    <td class="text-xs text-slate-500">{{ tx.date }}</td>
                                    <td>
                                        <span class="text-xs" :class="tx.is_credit ? 'badge-mint' : 'badge-rose'">
                                            {{ tx.type }}
                                        </span>
                                    </td>
                                    <td
                                        class="money text-sm font-semibold"
                                        :class="tx.is_credit ? 'text-mint-600' : 'text-rose-600'"
                                    >
                                        {{ tx.amount_formatted }}
                                    </td>
                                    <td class="money text-sm text-slate-500">{{ tx.available_after }}</td>
                                    <td class="max-w-xs truncate text-xs text-slate-500">{{ tx.description }}</td>
                                </tr>
                                <tr v-if="transactions.data.length === 0">
                                    <td colspan="5" class="py-4 text-center text-slate-500">No transactions yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2">
                        <Pagination :links="transactions.links" :total="transactions.total" />
                    </div>
                </div>
            </div>

            <!-- Manual adjustment sidebar -->
            <div v-if="canAdjust">
                <div class="card-p">
                    <h2 class="section-title mb-1">
                        Manual Adjustment <span class="badge-rose ml-1">Admin only</span>
                    </h2>
                    <p class="mb-3 text-xs text-slate-500">Use sparingly. Every adjustment is audit-logged.</p>
                    <form class="flex flex-col gap-2" @submit.prevent="submitAdjustment">
                        <div>
                            <label class="label">Amount (positive = credit, negative = debit)</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-2 flex items-center font-mono text-slate-400">৳</span>
                                <input
                                    v-model="amount"
                                    type="number"
                                    step="0.01"
                                    class="input pl-6"
                                    placeholder="e.g. 100 or -50"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="label">Reason <span class="text-rose-500">*</span></label>
                            <textarea
                                v-model="reason"
                                rows="3"
                                class="input text-sm"
                                placeholder="Explain why this manual adjustment is needed… (min 10 chars)"
                            ></textarea>
                        </div>
                        <button type="submit" class="btn-warning w-full" :disabled="!canSubmit">
                            Apply adjustment
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
