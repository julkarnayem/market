<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import type { Paginated } from '@/types';

/** One withdrawal history row — whitelisted by WithdrawalController::index(). */
interface WithdrawalRow {
    id: number;
    amount_formatted: string;
    fee_formatted: string;
    net_formatted: string;
    /** Raw provider slug, e.g. "bkash" — uppercased for display. */
    provider: string;
    masked_number: string;
    /** WithdrawalStatus value, for the badge. */
    status: string;
    date: string;
    rejection_reason: string | null;
}

const props = defineProps<{
    availableFormatted: string;
    /** Minimum withdrawal in BDT — input min attribute. */
    minBdt: number;
    /** number_format(minBdt, 2) — display copy. */
    minBdtFormatted: string;
    /** Money::format(fee) — snapshot line. */
    feeFormatted: string;
    /** Fee in BDT (float) — drives the live "you receive" preview. */
    feeBdt: number;
    /** number_format(feeBdt, 2) — static fee-preview line. */
    feeBdtFormatted: string;
    hasPending: boolean;
    pendingFormatted: string;
    /** available_balance >= minWithdrawal — show form vs below-minimum notice. */
    canWithdraw: boolean;
    /** Available balance in BDT — input max attribute. */
    maxBdt: number;
    withdrawals: Paginated<WithdrawalRow>;
}>();

/** Static UI labels (server validates the slug in :store). */
const providers = [
    { value: 'bkash', label: 'bKash' },
    { value: 'nagad', label: 'Nagad' },
    { value: 'rocket', label: 'Rocket' },
    { value: 'upay', label: 'Upay' },
] as const;

const form = useForm<{
    amount_bdt: number | string;
    mfs_provider: string;
    mfs_number: string;
}>({
    amount_bdt: 0,
    mfs_provider: 'bkash',
    mfs_number: '',
});

// Live fee preview — mirrors the Blade's Alpine net(): coerce the (possibly
// empty) input to a number so toFixed never throws, then clamp at zero.
const amount = computed(() => Number(form.amount_bdt) || 0);
const net = computed(() => Math.max(0, amount.value - props.feeBdt));

function submit() {
    form.post(route('dashboard.withdrawals.store'), {
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <DashboardLayout title="Withdrawals" heading="Withdrawals">
        <div class="flex max-w-2xl flex-col gap-3">
            <!-- Balance snapshot -->
            <div class="card-p">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase text-slate-500">Available to withdraw</p>
                        <span class="money mt-1 block text-2xl font-bold !text-mint-700">{{ availableFormatted }}</span>
                    </div>
                    <div class="space-y-0.5 text-right text-xs text-slate-500">
                        <p>Minimum: <span class="money font-medium text-slate-900">৳{{ minBdtFormatted }}</span></p>
                        <p>Fee: <span class="money font-medium text-slate-900">{{ feeFormatted }}</span> per withdrawal</p>
                        <p>Method: Mobile Financial Service (MFS)</p>
                    </div>
                </div>
            </div>

            <!-- Pending warning -->
            <div v-if="hasPending" class="alert-info flex-col !items-start">
                <p class="font-semibold">Pending balance: <span class="money">{{ pendingFormatted }}</span></p>
                <p class="mt-1 text-sm">
                    Pending amounts are locked until 8 hours after order completion. They will move to available
                    automatically.
                </p>
            </div>

            <!-- Request form -->
            <div v-if="canWithdraw" class="card-p">
                <h2 class="section-title mb-3">Request Withdrawal</h2>
                <form class="flex flex-col gap-3" novalidate @submit.prevent="submit">
                    <div>
                        <label class="label">Amount (৳) <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 font-mono font-bold text-slate-500">৳</span>
                            <input
                                v-model.number="form.amount_bdt"
                                type="number"
                                class="input pl-7"
                                :class="form.errors.amount_bdt && 'input-error'"
                                :min="minBdt"
                                step="1"
                                :max="maxBdt"
                                required
                                autofocus
                            />
                        </div>
                        <p v-if="form.errors.amount_bdt" class="field-error">{{ form.errors.amount_bdt }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label">MFS Provider <span class="text-rose-500">*</span></label>
                            <select
                                v-model="form.mfs_provider"
                                class="select"
                                :class="form.errors.mfs_provider && 'input-error'"
                                required
                            >
                                <option v-for="p in providers" :key="p.value" :value="p.value">{{ p.label }}</option>
                            </select>
                            <p v-if="form.errors.mfs_provider" class="field-error">{{ form.errors.mfs_provider }}</p>
                        </div>
                        <div>
                            <label class="label">Mobile Number <span class="text-rose-500">*</span></label>
                            <input
                                v-model="form.mfs_number"
                                type="tel"
                                class="input"
                                :class="form.errors.mfs_number && 'input-error'"
                                placeholder="01XXXXXXXXX"
                                maxlength="15"
                                required
                            />
                            <p v-if="form.errors.mfs_number" class="field-error">{{ form.errors.mfs_number }}</p>
                        </div>
                    </div>

                    <!-- Fee preview -->
                    <div class="flex flex-col gap-2 rounded-lg bg-slate-50 p-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Requested</span>
                            <span class="money">৳{{ amount.toFixed(2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Withdrawal fee</span>
                            <span class="money text-rose-600">— ৳{{ feeBdtFormatted }}</span>
                        </div>
                        <div class="flex justify-between border-t border-slate-200 pt-2 font-bold">
                            <span>You receive</span>
                            <span class="money text-mint-700">৳{{ net.toFixed(2) }}</span>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary btn-lg" :disabled="form.processing">
                        {{ form.processing ? 'Submitting…' : 'Submit withdrawal request' }}
                    </button>
                </form>
            </div>
            <div v-else class="alert-warning">
                Your available balance ({{ availableFormatted }}) is below the minimum withdrawal amount (৳{{
                    minBdtFormatted
                }}).
            </div>

            <!-- History -->
            <h2 class="section-title">Withdrawal History</h2>
            <div v-if="withdrawals.data.length === 0" class="card-p text-center">
                <p class="mb-2 text-4xl">🏦</p>
                <h2 class="font-display text-lg font-bold text-slate-900">No withdrawals yet</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Submit a request once your balance is ৳{{ minBdtFormatted }} or more.
                </p>
            </div>

            <template v-else>
                <!-- Desktop table -->
                <div class="table-wrap hidden sm:block">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Amount</th>
                                <th>Fee</th>
                                <th>Net</th>
                                <th>Provider</th>
                                <th>Account</th>
                                <th>Status</th>
                                <th>Requested</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="w in withdrawals.data" :key="w.id">
                                <td class="font-mono text-xs text-slate-500">#{{ w.id }}</td>
                                <td class="money">{{ w.amount_formatted }}</td>
                                <td class="money text-rose-600">{{ w.fee_formatted }}</td>
                                <td class="money font-semibold text-mint-700">{{ w.net_formatted }}</td>
                                <td class="text-xs uppercase">{{ w.provider }}</td>
                                <td class="font-mono text-xs">{{ w.masked_number }}</td>
                                <td><StatusBadge :status="w.status" /></td>
                                <td class="text-xs text-slate-500">{{ w.date }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile cards -->
                <div class="flex flex-col gap-2 sm:hidden">
                    <div v-for="w in withdrawals.data" :key="w.id" class="card-p">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <span class="money font-bold text-mint-700">{{ w.net_formatted }}</span>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ w.provider.toUpperCase() }} · {{ w.masked_number }}
                                </p>
                            </div>
                            <StatusBadge :status="w.status" />
                        </div>
                        <p v-if="w.status === 'rejected' && w.rejection_reason" class="mt-2 text-xs text-rose-600">
                            Reason: {{ w.rejection_reason }}
                        </p>
                        <p class="mt-1 text-xs text-slate-400">{{ w.date }}</p>
                    </div>
                </div>

                <div class="mt-3">
                    <Pagination :links="withdrawals.links" :total="withdrawals.total" />
                </div>
            </template>
        </div>
    </DashboardLayout>
</template>
