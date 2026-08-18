<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

/** Keys of the five promotion-price fields — one per purchasable duration. */
type PriceField =
    | 'promotion_price_1'
    | 'promotion_price_2'
    | 'promotion_price_3'
    | 'promotion_price_4'
    | 'promotion_price_5';

interface PromotionPrice {
    days: number;
    label: string;
    field: PriceField;
    /** BDT, not poisha — the input edits BDT and the server converts. */
    bdt: number;
}

const props = defineProps<{
    settings: {
        seller_fee_bp: number;
        buyer_fee_enabled: boolean;
        minimum_withdrawal: number;
        withdrawal_fee: number;
        offer_validity_hours: number;
        earning_lock_hours: number;
        buyer_protection_hours: number;
    };
    promotion_prices: PromotionPrice[];
    can_manage: boolean;
}>();

// index() authorizes settings.view, update() authorizes settings.manage, so a
// view-only admin gets this page read-only. can_manage is the server's own
// Gate answer (super-admin bypass included); update() re-checks regardless.
const canManage = computed(() => props.can_manage);

const form = useForm<{
    seller_fee_bp: number;
    buyer_fee_enabled: boolean;
    minimum_withdrawal: number;
    withdrawal_fee: number;
    offer_validity_hours: number;
    earning_lock_hours: number;
    buyer_protection_hours: number;
    promotion_price_1: number;
    promotion_price_2: number;
    promotion_price_3: number;
    promotion_price_4: number;
    promotion_price_5: number;
}>({
    ...props.settings,
    promotion_price_1: price(1),
    promotion_price_2: price(2),
    promotion_price_3: price(3),
    promotion_price_4: price(4),
    promotion_price_5: price(5),
});

function price(days: number): number {
    return props.promotion_prices.find((p) => p.days === days)?.bdt ?? 0;
}

function submit(): void {
    if (!canManage.value) return;
    // `buyer_fee_enabled` goes out as a real JSON boolean. The Blade posted a
    // hidden '0' plus a checkbox '1', which validate()'s bare `boolean` rule
    // passed through as a string — so the row was persisted with type 'int'.
    form.patch(route('admin.settings.update'), { preserveScroll: true });
}

/** Money::format() in the browser, for the live poisha → BDT hints. */
const bdt = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function money(poisha: number): string {
    return Number.isFinite(poisha) ? `৳${bdt.format(poisha / 100)}` : '—';
}

const sellerFeePercent = computed(() =>
    Number.isFinite(form.seller_fee_bp) ? (form.seller_fee_bp / 100).toFixed(2) : '—',
);
</script>

<template>
    <AdminLayout title="Settings" heading="Platform Settings">
        <div
            v-if="!canManage"
            class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
        >
            You do not have permission to manage settings — these values are read-only.
        </div>

        <form class="flex max-w-3xl flex-col gap-4" @submit.prevent="submit">
            <!-- Fees -->
            <div class="card-p">
                <h2 class="section-title mb-3">Fees</h2>
                <div class="flex flex-col gap-3">
                    <div>
                        <label class="label">Seller platform fee (basis points)</label>
                        <div class="flex flex-wrap items-center gap-3">
                            <input
                                v-model.number="form.seller_fee_bp"
                                type="number"
                                class="input max-w-[10rem]"
                                :class="form.errors.seller_fee_bp && 'input-error'"
                                min="0"
                                max="10000"
                                step="1"
                                :disabled="!canManage"
                            />
                            <span class="money text-sm text-slate-500">= {{ sellerFeePercent }}%</span>
                        </div>
                        <p class="label-hint">
                            Default: 1000 bp = 10%. Enter 0–10000 (basis points). Applies to ALL prices.
                        </p>
                        <p v-if="form.errors.seller_fee_bp" class="field-error">{{ form.errors.seller_fee_bp }}</p>
                    </div>

                    <label class="flex items-center gap-2 text-sm font-medium text-slate-900">
                        <input
                            v-model="form.buyer_fee_enabled"
                            type="checkbox"
                            class="checkbox"
                            :disabled="!canManage"
                        />
                        Enable buyer fee
                    </label>
                    <p v-if="form.errors.buyer_fee_enabled" class="field-error">
                        {{ form.errors.buyer_fee_enabled }}
                    </p>
                </div>
            </div>

            <!-- Withdrawals -->
            <div class="card-p">
                <h2 class="section-title mb-3">Withdrawals</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label">Minimum withdrawal (poisha)</label>
                        <input
                            v-model.number="form.minimum_withdrawal"
                            type="number"
                            class="input"
                            :class="form.errors.minimum_withdrawal && 'input-error'"
                            min="100"
                            step="1"
                            :disabled="!canManage"
                        />
                        <p class="label-hint money">{{ money(form.minimum_withdrawal) }}</p>
                        <p v-if="form.errors.minimum_withdrawal" class="field-error">
                            {{ form.errors.minimum_withdrawal }}
                        </p>
                    </div>
                    <div>
                        <label class="label">Withdrawal fee (poisha)</label>
                        <input
                            v-model.number="form.withdrawal_fee"
                            type="number"
                            class="input"
                            :class="form.errors.withdrawal_fee && 'input-error'"
                            min="0"
                            step="1"
                            :disabled="!canManage"
                        />
                        <p class="label-hint money">{{ money(form.withdrawal_fee) }}</p>
                        <p v-if="form.errors.withdrawal_fee" class="field-error">
                            {{ form.errors.withdrawal_fee }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Timing -->
            <div class="card-p">
                <h2 class="section-title mb-3">Timing</h2>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="label">Offer validity (hours)</label>
                        <input
                            v-model.number="form.offer_validity_hours"
                            type="number"
                            class="input"
                            :class="form.errors.offer_validity_hours && 'input-error'"
                            min="1"
                            max="168"
                            step="1"
                            :disabled="!canManage"
                        />
                        <p class="label-hint">Max 168 (7 days).</p>
                        <p v-if="form.errors.offer_validity_hours" class="field-error">
                            {{ form.errors.offer_validity_hours }}
                        </p>
                    </div>
                    <div>
                        <label class="label">Earning lock (hours)</label>
                        <input
                            v-model.number="form.earning_lock_hours"
                            type="number"
                            class="input"
                            :class="form.errors.earning_lock_hours && 'input-error'"
                            min="1"
                            step="1"
                            :disabled="!canManage"
                        />
                        <p class="label-hint">Applied after Order Complete.</p>
                        <p v-if="form.errors.earning_lock_hours" class="field-error">
                            {{ form.errors.earning_lock_hours }}
                        </p>
                    </div>
                    <div>
                        <label class="label">Buyer protection (hours)</label>
                        <input
                            v-model.number="form.buyer_protection_hours"
                            type="number"
                            class="input"
                            :class="form.errors.buyer_protection_hours && 'input-error'"
                            min="1"
                            step="1"
                            :disabled="!canManage"
                        />
                        <p class="label-hint">Auto-complete window.</p>
                        <p v-if="form.errors.buyer_protection_hours" class="field-error">
                            {{ form.errors.buyer_protection_hours }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Promotion pricing -->
            <div class="card-p">
                <h2 class="section-title mb-3">Promotion Pricing</h2>
                <div class="flex flex-col gap-2">
                    <div v-for="p in promotion_prices" :key="p.days" class="flex flex-wrap items-center gap-3">
                        <label class="label mb-0 w-16 shrink-0">{{ p.label }}</label>
                        <span class="font-mono text-slate-500">৳</span>
                        <input
                            v-model.number="form[p.field]"
                            type="number"
                            class="input max-w-[8rem]"
                            :class="form.errors[p.field] && 'input-error'"
                            min="0"
                            step="1"
                            :disabled="!canManage"
                        />
                        <span class="text-xs text-slate-500">BDT (stored as poisha)</span>
                        <p v-if="form.errors[p.field]" class="field-error w-full">{{ form.errors[p.field] }}</p>
                    </div>
                </div>
            </div>

            <div v-if="canManage">
                <button type="submit" class="btn-primary btn-lg" :disabled="form.processing">
                    {{ form.processing ? 'Saving…' : 'Save all settings' }}
                </button>
            </div>
        </form>
    </AdminLayout>
</template>
