<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import type { Paginated } from '@/types';

/** One withdrawal history row — whitelisted by WithdrawalController::index(). */
interface WithdrawalRow {
    id: number;
    /** Buyer-facing handle, e.g. WD-10007. */
    reference: string;
    amount_formatted: string;
    fee_formatted: string;
    net_formatted: string;
    /** Human label for the method, e.g. "bKash" or "Bank transfer". */
    method_label: string;
    /** Masked destination — never the raw account number. */
    masked_number: string;
    /** WithdrawalStatus value, for the badge. */
    status: string;
    status_label: string;
    date: string;
    /** When it reached a terminal state, or null while pending. */
    processed_at: string | null;
    rejection_reason: string | null;
    can_cancel: boolean;
}

interface MethodOption {
    value: string;
    label: string;
    /** Bank transfers collect account details instead of a mobile number. */
    is_bank: boolean;
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
    /** Gross of every completed payout. */
    totalWithdrawnFormatted: string;
    /** From WithdrawalMethod::options(), so the form cannot offer an invalid one. */
    methods: MethodOption[];
    withdrawals: Paginated<WithdrawalRow>;
}>();

/**
 * The id is minted on first submit and only cleared once the request lands, so a
 * double-clicked button reuses it and the server dedupes on
 * (user_id, client_request_id) instead of reserving the balance twice.
 */
const newId = (): string =>
    globalThis.crypto?.randomUUID?.() ??
    `w-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;

const form = useForm<{
    amount_bdt: number | string;
    method: string;
    mfs_number: string;
    bank_account_name: string;
    bank_account_number: string;
    bank_name: string;
    bank_branch: string;
    client_request_id: string;
}>({
    amount_bdt: 0,
    method: props.methods[0]?.value ?? 'bkash',
    mfs_number: '',
    bank_account_name: '',
    bank_account_number: '',
    bank_name: '',
    bank_branch: '',
    client_request_id: '',
});

const selectedMethod = computed(() => props.methods.find((m) => m.value === form.method));
const isBank = computed(() => selectedMethod.value?.is_bank === true);

// Live fee preview: coerce the (possibly empty) input to a number so toFixed
// never throws, then clamp at zero.
const amount = computed(() => Number(form.amount_bdt) || 0);
const net = computed(() => Math.max(0, amount.value - props.feeBdt));

// Client-side mirror of the server's rules — the server still decides.
const overBalance = computed(() => amount.value > props.maxBdt);
const belowMinimum = computed(() => amount.value > 0 && amount.value < props.minBdt);

function submit() {
    // Confirm before money moves, naming the amount and destination.
    const label = selectedMethod.value?.label ?? form.method;
    if (!window.confirm(`You are requesting to withdraw ৳${amount.value.toFixed(2)} via ${label}. Continue?`)) {
        return;
    }

    if (!form.client_request_id) form.client_request_id = newId();

    form.post(route('dashboard.withdrawals.store'), {
        onSuccess: () => form.reset(),
    });
}

const cancelling = ref<number | null>(null);

function cancel(w: WithdrawalRow) {
    if (!window.confirm(`Cancel withdrawal ${w.reference}? The funds go back to your available balance.`)) {
        return;
    }
    cancelling.value = w.id;
    router.post(route('dashboard.withdrawals.cancel', w.id), {}, {
        preserveScroll: true,
        onFinish: () => (cancelling.value = null),
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
                        <p>Pending: <span class="money font-medium text-slate-900">{{ pendingFormatted }}</span></p>
                        <p>Total withdrawn: <span class="money font-medium text-slate-900">{{ totalWithdrawnFormatted }}</span></p>
                        <p>Minimum: <span class="money font-medium text-slate-900">৳{{ minBdtFormatted }}</span></p>
                        <p>Fee: <span class="money font-medium text-slate-900">{{ feeFormatted }}</span> per withdrawal</p>
                    </div>
                </div>
            </div>

            <!-- Pending warning -->
            <div v-if="hasPending" class="alert-info flex-col !items-start">
                <p class="font-semibold">Pending balance: <span class="money">{{ pendingFormatted }}</span></p>
                <p class="mt-1 text-sm">
                    Pending amounts are locked until 8 hours after order completion. They will move to available
                    automatically, and cannot be withdrawn before then.
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
                                :class="(form.errors.amount_bdt || overBalance || belowMinimum) && 'input-error'"
                                :min="minBdt"
                                step="1"
                                :max="maxBdt"
                                required
                                autofocus
                            />
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            Maximum withdrawable: <span class="money font-medium">{{ availableFormatted }}</span>
                        </p>
                        <p v-if="overBalance" class="field-error">Insufficient available balance.</p>
                        <p v-else-if="belowMinimum" class="field-error">
                            Minimum withdrawal amount is ৳{{ minBdtFormatted }}.
                        </p>
                        <p v-if="form.errors.amount_bdt" class="field-error">{{ form.errors.amount_bdt }}</p>
                    </div>

                    <div>
                        <label class="label">Withdrawal method <span class="text-rose-500">*</span></label>
                        <select
                            v-model="form.method"
                            class="select"
                            :class="form.errors.method && 'input-error'"
                            required
                        >
                            <option v-for="m in methods" :key="m.value" :value="m.value">{{ m.label }}</option>
                        </select>
                        <p v-if="form.errors.method" class="field-error">{{ form.errors.method }}</p>
                    </div>

                    <!-- Mobile money: just the wallet number. -->
                    <div v-if="!isBank">
                        <label class="label">Mobile number <span class="text-rose-500">*</span></label>
                        <input
                            v-model="form.mfs_number"
                            type="tel"
                            class="input"
                            :class="form.errors.mfs_number && 'input-error'"
                            placeholder="01XXXXXXXXX"
                            maxlength="15"
                        />
                        <p v-if="form.errors.mfs_number" class="field-error">{{ form.errors.mfs_number }}</p>
                    </div>

                    <!-- Bank transfer: where to send it. -->
                    <template v-else>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="label">Account holder <span class="text-rose-500">*</span></label>
                                <input
                                    v-model="form.bank_account_name"
                                    type="text"
                                    class="input"
                                    :class="form.errors.bank_account_name && 'input-error'"
                                    maxlength="120"
                                />
                                <p v-if="form.errors.bank_account_name" class="field-error">
                                    {{ form.errors.bank_account_name }}
                                </p>
                            </div>
                            <div>
                                <label class="label">Account number <span class="text-rose-500">*</span></label>
                                <input
                                    v-model="form.bank_account_number"
                                    type="text"
                                    class="input"
                                    :class="form.errors.bank_account_number && 'input-error'"
                                    maxlength="64"
                                />
                                <p v-if="form.errors.bank_account_number" class="field-error">
                                    {{ form.errors.bank_account_number }}
                                </p>
                            </div>
                            <div>
                                <label class="label">Bank name <span class="text-rose-500">*</span></label>
                                <input
                                    v-model="form.bank_name"
                                    type="text"
                                    class="input"
                                    :class="form.errors.bank_name && 'input-error'"
                                    maxlength="120"
                                />
                                <p v-if="form.errors.bank_name" class="field-error">{{ form.errors.bank_name }}</p>
                            </div>
                            <div>
                                <label class="label">Branch / routing <span class="font-normal text-slate-500">(optional)</span></label>
                                <input
                                    v-model="form.bank_branch"
                                    type="text"
                                    class="input"
                                    :class="form.errors.bank_branch && 'input-error'"
                                    maxlength="120"
                                />
                                <p v-if="form.errors.bank_branch" class="field-error">{{ form.errors.bank_branch }}</p>
                            </div>
                        </div>
                    </template>

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
                        <p class="text-xs text-slate-500">
                            ৳{{ amount.toFixed(2) }} leaves your wallet; the fee is taken from it, not added on top.
                        </p>
                    </div>

                    <button
                        type="submit"
                        class="btn-primary btn-lg"
                        :disabled="form.processing || overBalance || belowMinimum || amount <= 0"
                    >
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
                                <th>Reference</th>
                                <th>Amount</th>
                                <th>Fee</th>
                                <th>Net</th>
                                <th>Method</th>
                                <th>Account</th>
                                <th>Status</th>
                                <th>Requested</th>
                                <th>Processed</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="w in withdrawals.data" :key="w.id">
                                <tr>
                                    <td class="font-mono text-xs text-slate-500">{{ w.reference }}</td>
                                    <td class="money">{{ w.amount_formatted }}</td>
                                    <td class="money text-rose-600">{{ w.fee_formatted }}</td>
                                    <td class="money font-semibold text-mint-700">{{ w.net_formatted }}</td>
                                    <td class="text-xs">{{ w.method_label }}</td>
                                    <td class="font-mono text-xs">{{ w.masked_number }}</td>
                                    <td><StatusBadge :status="w.status" :label="w.status_label" /></td>
                                    <td class="text-xs text-slate-500">{{ w.date }}</td>
                                    <td class="text-xs text-slate-500">{{ w.processed_at ?? '—' }}</td>
                                    <td>
                                        <!-- Only a pending request is the user's to take back. -->
                                        <button
                                            v-if="w.can_cancel"
                                            type="button"
                                            class="btn-ghost btn-sm text-rose-600"
                                            :disabled="cancelling === w.id"
                                            @click="cancel(w)"
                                        >
                                            {{ cancelling === w.id ? '…' : 'Cancel' }}
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="w.status === 'rejected' && w.rejection_reason">
                                    <td colspan="10" class="bg-rose-50 text-xs text-rose-700">
                                        Rejected: {{ w.rejection_reason }}
                                    </td>
                                </tr>
                            </template>
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
                                    {{ w.method_label }} · {{ w.masked_number }}
                                </p>
                                <p class="mt-0.5 font-mono text-xs text-slate-400">{{ w.reference }}</p>
                            </div>
                            <StatusBadge :status="w.status" :label="w.status_label" />
                        </div>
                        <p v-if="w.status === 'rejected' && w.rejection_reason" class="mt-2 text-xs text-rose-600">
                            Reason: {{ w.rejection_reason }}
                        </p>
                        <div class="mt-1 flex items-center justify-between gap-2">
                            <p class="text-xs text-slate-400">
                                {{ w.date }}<span v-if="w.processed_at"> · processed {{ w.processed_at }}</span>
                            </p>
                            <button
                                v-if="w.can_cancel"
                                type="button"
                                class="btn-ghost btn-sm text-rose-600"
                                :disabled="cancelling === w.id"
                                @click="cancel(w)"
                            >
                                {{ cancelling === w.id ? '…' : 'Cancel' }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <Pagination :links="withdrawals.links" :total="withdrawals.total" />
                </div>
            </template>
        </div>
    </DashboardLayout>
</template>
