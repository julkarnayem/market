<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import WithdrawalActions from '@/Components/WithdrawalActions.vue';

interface WithdrawalDetail {
    id: number;
    /** Buyer-facing handle, e.g. WD-7RASRSC42JFW. */
    reference: string;
    user_name: string;
    status: string;
    status_label: string;
    amount_formatted: string;
    fee_formatted: string;
    net_formatted: string;
    provider: string;
    requested: string;
    rejected_at: string | null;
    cancelled_at: string | null;
    completed_at: string | null;
    external_reference: string | null;
    rejection_reason: string | null;
}

/** The full, unmasked destination — staff-only, shown so the payout can be sent. */
interface Payout {
    /** 'mfs' | 'bank'. */
    method: string;
    mfs_number: string | null;
    bank_account_name: string | null;
    bank_account_number: string | null;
    bank_name: string | null;
    bank_branch: string | null;
}

const props = defineProps<{
    withdrawal: WithdrawalDetail;
    payout: Payout;
    wallet: { available_formatted: string; pending_formatted: string } | null;
}>();

// Show the action block to staff who can act on a payout: super-admin (role
// 'admin') or the pay / reject permission. The action routes re-check server-side.
const user = computed(() => usePage().props.auth.user);
const canProcess = computed(() => {
    const u = user.value;
    if (!u) return false;
    return (
        u.roles.includes('admin') ||
        u.permissions.includes('withdrawals.complete') ||
        u.permissions.includes('withdrawals.reject')
    );
});

/** Detail rows, in Blade order; nullable timestamps/reference render only when set. */
interface DetailRow {
    label: string;
    value: string;
    money?: boolean;
    tone?: string;
}
const rows = computed<DetailRow[]>(() => {
    const w = props.withdrawal;
    return [
        { label: 'User', value: w.user_name },
        { label: 'Amount requested', value: w.amount_formatted, money: true },
        { label: 'Withdrawal fee', value: w.fee_formatted, money: true, tone: 'text-rose-600' },
        { label: 'Net payout', value: w.net_formatted, money: true, tone: 'font-bold text-emerald-600' },
        { label: 'Provider', value: w.provider, tone: 'font-semibold uppercase' },
        { label: 'Requested', value: w.requested },
        ...(w.rejected_at ? [{ label: 'Rejected at', value: w.rejected_at }] : []),
        ...(w.completed_at ? [{ label: 'Paid at', value: w.completed_at }] : []),
    ];
});
</script>

<template>
    <AdminLayout :title="'Withdrawal #' + withdrawal.id" heading="Withdrawal Detail">
        <Breadcrumb
            :items="[{ label: 'Withdrawals', url: route('admin.withdrawals') }, { label: '#' + withdrawal.id }]"
        />

        <div class="flex max-w-2xl flex-col gap-3">
            <div class="card-p">
                <h2 class="section-title mb-3">Withdrawal #{{ withdrawal.id }}</h2>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-lg bg-slate-50 p-2">
                        <dt class="text-xs text-slate-500">Status</dt>
                        <dd><StatusBadge :status="withdrawal.status" :label="withdrawal.status_label" /></dd>
                    </div>
                    <div v-for="r in rows" :key="r.label" class="rounded-lg bg-slate-50 p-2">
                        <dt class="text-xs text-slate-500">{{ r.label }}</dt>
                        <dd :class="[r.money && 'money', r.tone]">{{ r.value }}</dd>
                    </div>
                    <div v-if="withdrawal.external_reference" class="col-span-2 rounded-lg bg-slate-50 p-2">
                        <dt class="text-xs text-slate-500">External reference</dt>
                        <dd class="font-mono">{{ withdrawal.external_reference }}</dd>
                    </div>
                </dl>
                <div v-if="withdrawal.rejection_reason" class="mt-3 rounded-lg bg-rose-50 p-2 text-sm">
                    <p class="font-semibold text-rose-800">Rejection reason:</p>
                    <p class="text-rose-600">{{ withdrawal.rejection_reason }}</p>
                </div>
            </div>

            <!-- Payout destination: full + unmasked, so staff can actually send it.
                 Click a value to select it whole (select-all) for quick copy. -->
            <div class="card-p">
                <h2 class="section-title mb-2">Send payout to</h2>
                <dl class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                    <div v-if="payout.method !== 'bank'" class="rounded-lg bg-slate-50 p-2 sm:col-span-2">
                        <dt class="text-xs text-slate-500">{{ withdrawal.provider }} number</dt>
                        <dd class="select-all font-mono text-base font-semibold">{{ payout.mfs_number }}</dd>
                    </div>
                    <template v-else>
                        <div class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">Account holder</dt>
                            <dd class="font-semibold">{{ payout.bank_account_name }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">Account number</dt>
                            <dd class="select-all font-mono text-base font-semibold">{{ payout.bank_account_number }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">Bank</dt>
                            <dd class="font-semibold">{{ payout.bank_name }}</dd>
                        </div>
                        <div v-if="payout.bank_branch" class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">Branch / routing</dt>
                            <dd>{{ payout.bank_branch }}</dd>
                        </div>
                    </template>
                </dl>
                <p class="mt-2 text-xs text-amber-600">Full details, shown for payout only — don't share or screenshot.</p>
            </div>

            <!-- User wallet -->
            <div v-if="wallet" class="card-p">
                <h2 class="section-title mb-2">User Wallet Balance</h2>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-lg bg-emerald-50 p-2">
                        <p class="text-xs text-emerald-600">Available</p>
                        <p class="money font-bold text-emerald-600">{{ wallet.available_formatted }}</p>
                    </div>
                    <div class="rounded-lg bg-amber-50 p-2">
                        <p class="text-xs text-amber-600">Pending</p>
                        <p class="money font-bold text-amber-600">{{ wallet.pending_formatted }}</p>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <WithdrawalActions
                v-if="canProcess"
                :id="withdrawal.id"
                :status="withdrawal.status"
                layout="card"
            />
        </div>
    </AdminLayout>
</template>
