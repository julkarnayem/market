<script setup lang="ts">
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
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

/** One admin-managed payout method. `type` fixes which account fields it needs. */
interface WithdrawalMethod {
    id: number;
    key: string;
    label: string;
    type: 'mfs' | 'bank';
    is_active: boolean;
    sort_order: number;
    /** Withdrawals that have used this method — blocks deletion when > 0. */
    usage_count: number;
}

/** The 4 admin-editable brand-semantic colors, each an #RRGGBB hex. */
type ThemeRole = 'brand' | 'money' | 'featured' | 'danger';
type ThemeColorMap = Record<ThemeRole, string>;

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
    withdrawal_methods: WithdrawalMethod[];
    /** Current color per role (stored override, else the shipped default). */
    theme_colors: ThemeColorMap;
    /** Shipped default per role — drives the per-row "Reset to default". */
    theme_defaults: ThemeColorMap;
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

// ── Withdrawal methods (admin-managed set) ──────────────────────────────────
// These are separate from the settings form above: each is its own request
// against the dedicated admin.settings.methods.* routes, so a toggle or delete
// is not entangled with saving fees/timing. Every response redirects back and
// Inertia reloads the props, so the rows below always reflect server truth —
// a rejected toggle (e.g. the last active method) snaps back on its own.
const addForm = useForm<{ label: string; key: string; type: 'mfs' | 'bank' }>({
    label: '',
    key: '',
    type: 'mfs',
});

function addMethod(): void {
    if (!canManage.value) return;
    addForm.post(route('admin.settings.methods.store'), {
        preserveScroll: true,
        onSuccess: () => addForm.reset(),
    });
}

function toggleMethod(method: WithdrawalMethod): void {
    if (!canManage.value) return;
    router.patch(
        route('admin.settings.methods.update', method.id),
        { is_active: !method.is_active },
        { preserveScroll: true },
    );
}

function deleteMethod(method: WithdrawalMethod): void {
    if (!canManage.value || method.usage_count > 0) return;
    if (!window.confirm(`Delete “${method.label}”? Users will no longer be able to pick it.`)) return;
    router.delete(route('admin.settings.methods.destroy', method.id), { preserveScroll: true });
}

// ── Theme colors (admin-editable palette) ───────────────────────────────────
// Its own request against admin.settings.theme.update. Pick one hex per role;
// the server generates the full 50–900 shade scale from it and re-emits the CSS
// variables the whole site reads — so a save recolors everything on next load
// with no rebuild. Saving the default hex (or Reset) drops the override.
const THEME_ROLES: { role: ThemeRole; label: string; hint: string }[] = [
    { role: 'brand', label: 'Brand', hint: 'Buttons, links, active nav, focus rings' },
    { role: 'money', label: 'Money', hint: 'Wallet, earnings, “Paid”, verified' },
    { role: 'featured', label: 'Featured', hint: 'Promotion, urgency, warnings' },
    { role: 'danger', label: 'Danger', hint: 'Disputes, delete, errors' },
];

const themeForm = useForm<ThemeColorMap>({ ...props.theme_colors });

/** True when a role's chosen hex differs from its shipped default. */
function isCustomColor(role: ThemeRole): boolean {
    return themeForm[role].toLowerCase() !== props.theme_defaults[role].toLowerCase();
}

function resetColor(role: ThemeRole): void {
    if (!canManage.value) return;
    themeForm[role] = props.theme_defaults[role];
}

/** A soft tint of the chosen color, for the badge sample in the live preview. */
function softTint(hex: string): string {
    return `color-mix(in srgb, ${hex} 14%, white)`;
}

function submitTheme(): void {
    if (!canManage.value) return;
    themeForm.patch(route('admin.settings.theme.update'), { preserveScroll: true });
}
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

        <!-- Theme colors — admin-editable palette, its own request (not the form above) -->
        <section class="card-p mt-4 max-w-3xl">
            <div class="mb-1 flex items-center justify-between gap-3">
                <h2 class="section-title">Theme Colors</h2>
                <span class="text-xs text-slate-500">Recolor the whole site.</span>
            </div>
            <p class="label-hint mb-3">
                Pick one base color per role — the full range of shades is generated from it and
                applied everywhere on the next page load, no rebuild needed. Reset restores the default.
            </p>

            <!-- Live preview (approximate — the saved scale is generated server-side) -->
            <div class="mb-4 flex flex-wrap items-center gap-2 rounded-xl bg-slate-50 p-3">
                <span class="text-xs font-medium uppercase tracking-wider text-slate-400">Preview</span>
                <span
                    class="rounded-xl px-3 py-1.5 text-sm font-semibold text-white shadow-sm"
                    :style="{ backgroundColor: themeForm.brand }"
                >
                    Primary
                </span>
                <span
                    class="money rounded-full px-2.5 py-0.5 text-xs font-medium"
                    :style="{ backgroundColor: softTint(themeForm.money), color: themeForm.money }"
                >
                    ৳ Paid
                </span>
                <span
                    class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                    :style="{ backgroundColor: softTint(themeForm.featured), color: themeForm.featured }"
                >
                    Featured
                </span>
                <span
                    class="rounded-xl px-3 py-1.5 text-sm font-semibold text-white shadow-sm"
                    :style="{ backgroundColor: themeForm.danger }"
                >
                    Delete
                </span>
            </div>

            <form @submit.prevent="submitTheme">
                <ul class="divide-y divide-slate-100">
                    <li
                        v-for="r in THEME_ROLES"
                        :key="r.role"
                        class="flex flex-wrap items-center gap-3 py-2.5"
                    >
                        <input
                            v-model="themeForm[r.role]"
                            type="color"
                            class="h-9 w-12 shrink-0 cursor-pointer rounded-lg border border-slate-300 bg-white p-1 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!canManage"
                            :aria-label="`${r.label} color`"
                        />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-slate-900">{{ r.label }}</span>
                                <span
                                    v-if="isCustomColor(r.role)"
                                    class="rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-medium text-brand-700"
                                >
                                    Customized
                                </span>
                            </div>
                            <p class="label-hint mb-0">{{ r.hint }}</p>
                        </div>
                        <input
                            v-model="themeForm[r.role]"
                            type="text"
                            class="input max-w-[7.5rem] font-mono uppercase"
                            :class="themeForm.errors[r.role] && 'input-error'"
                            placeholder="#RRGGBB"
                            :disabled="!canManage"
                        />
                        <button
                            type="button"
                            class="text-sm font-medium text-slate-500 hover:text-slate-800 disabled:cursor-not-allowed disabled:text-slate-300"
                            :disabled="!canManage || !isCustomColor(r.role)"
                            @click="resetColor(r.role)"
                        >
                            Reset
                        </button>
                        <p v-if="themeForm.errors[r.role]" class="field-error w-full">
                            {{ themeForm.errors[r.role] }}
                        </p>
                    </li>
                </ul>

                <div v-if="canManage" class="mt-4 border-t border-slate-100 pt-4">
                    <button type="submit" class="btn-primary" :disabled="themeForm.processing">
                        {{ themeForm.processing ? 'Saving…' : 'Save theme colors' }}
                    </button>
                </div>
            </form>
        </section>

        <!-- Withdrawal methods — admin-managed set, its own requests (not the form above) -->
        <section class="card-p mt-4 max-w-3xl">
            <div class="mb-1 flex items-center justify-between gap-3">
                <h2 class="section-title">Withdrawal Methods</h2>
                <span class="text-xs text-slate-500">What users can choose when withdrawing.</span>
            </div>
            <p class="label-hint mb-3">
                Switch a method off to hide it from the withdrawal form immediately. A method that
                withdrawals have used can't be deleted — switch it off instead.
            </p>

            <ul class="divide-y divide-slate-100">
                <li
                    v-for="m in withdrawal_methods"
                    :key="m.id"
                    class="flex flex-wrap items-center gap-3 py-2.5"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-slate-900">{{ m.label }}</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                                :class="
                                    m.type === 'bank'
                                        ? 'bg-indigo-50 text-indigo-700'
                                        : 'bg-mint-50 text-mint-700'
                                "
                            >
                                {{ m.type === 'bank' ? 'Bank' : 'Mobile' }}
                            </span>
                            <span
                                v-if="!m.is_active"
                                class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500"
                            >
                                Off
                            </span>
                        </div>
                        <p class="label-hint mb-0">
                            <span class="font-mono">{{ m.key }}</span>
                            <span v-if="m.usage_count > 0">
                                · used by {{ m.usage_count }} withdrawal{{ m.usage_count > 1 ? 's' : '' }}
                            </span>
                        </p>
                    </div>

                    <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                        <input
                            type="checkbox"
                            class="checkbox"
                            :checked="m.is_active"
                            :disabled="!canManage"
                            @change="toggleMethod(m)"
                        />
                        On
                    </label>

                    <button
                        type="button"
                        class="text-sm font-medium text-rose-600 hover:text-rose-700 disabled:cursor-not-allowed disabled:text-slate-300"
                        :disabled="!canManage || m.usage_count > 0"
                        :title="m.usage_count > 0 ? 'In use — switch it off instead' : 'Delete method'"
                        @click="deleteMethod(m)"
                    >
                        Delete
                    </button>
                </li>
            </ul>

            <!-- Add a method -->
            <form
                v-if="canManage"
                class="mt-4 flex flex-wrap items-end gap-2 border-t border-slate-100 pt-4"
                @submit.prevent="addMethod"
            >
                <div>
                    <label class="label">Label</label>
                    <input
                        v-model="addForm.label"
                        type="text"
                        class="input max-w-[10rem]"
                        :class="addForm.errors.label && 'input-error'"
                        placeholder="e.g. MyCash"
                    />
                </div>
                <div>
                    <label class="label">Key</label>
                    <input
                        v-model="addForm.key"
                        type="text"
                        class="input max-w-[9rem] font-mono"
                        :class="addForm.errors.key && 'input-error'"
                        placeholder="mycash"
                    />
                </div>
                <div>
                    <label class="label">Type</label>
                    <select v-model="addForm.type" class="input max-w-[10rem]">
                        <option value="mfs">Mobile money</option>
                        <option value="bank">Bank transfer</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary" :disabled="addForm.processing">
                    {{ addForm.processing ? 'Adding…' : 'Add method' }}
                </button>
                <p v-if="addForm.errors.label" class="field-error w-full">{{ addForm.errors.label }}</p>
                <p v-if="addForm.errors.key" class="field-error w-full">{{ addForm.errors.key }}</p>
                <p v-if="addForm.errors.type" class="field-error w-full">{{ addForm.errors.type }}</p>
            </form>
        </section>
    </AdminLayout>
</template>
