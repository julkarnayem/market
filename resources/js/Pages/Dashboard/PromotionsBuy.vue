<script setup lang="ts">
import { computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

/** One duration option — days → poisha whitelist (PromotionService::PRICES). */
interface PriceOption {
    days: number;
    price_poisha: number;
    price_formatted: string;
}

const props = defineProps<{
    asset: {
        id: number;
        title: string;
        category_name: string | null;
        cover_url: string | null;
        price_formatted: string;
    };
    /** Ascending by days. */
    prices: PriceOption[];
    /** Available balance in poisha — drives the live balance-after preview. */
    walletBalance: number;
    walletBalanceFormatted: string;
    /** Present when the listing already has a live promotion; hides the form. */
    activePromo: { ends_full: string } | null;
}>();

const form = useForm<{ asset_id: number; days: number }>({
    asset_id: props.asset.id,
    days: 1,
});

const selectedPrice = computed(
    () => props.prices.find((p) => p.days === form.days)?.price_poisha ?? 0,
);
const balanceAfter = computed(() => props.walletBalance - selectedPrice.value);

// The two live summary lines mirror the Blade's Alpine arithmetic: raw poisha
// ÷ 100 with a hardcoded ৳. This is the one place the client formats currency —
// a live preview that reacts to the selected duration.
const bdt = (poisha: number) => '৳' + (poisha / 100).toFixed(2);

function submit() {
    form.post(route('dashboard.promotions.store'));
}
</script>

<template>
    <DashboardLayout title="Promote Listing" heading="Promote a Listing">
        <div class="max-w-lg">
            <!-- Asset summary -->
            <div class="card-p mb-3 flex items-center gap-3">
                <img
                    v-if="asset.cover_url"
                    :src="asset.cover_url"
                    class="h-14 w-14 flex-shrink-0 rounded-lg object-cover"
                    alt=""
                />
                <div>
                    <p class="truncate font-semibold text-slate-900">{{ asset.title }}</p>
                    <p class="text-xs text-slate-500">{{ asset.category_name }}</p>
                    <p class="mt-1 text-xs">
                        Listed price: <span class="money font-medium">{{ asset.price_formatted }}</span>
                    </p>
                </div>
            </div>

            <div v-if="activePromo" class="alert-warning mb-3">
                This listing already has an active promotion until
                <strong>{{ activePromo.ends_full }}</strong>.
            </div>

            <div v-else class="card-p">
                <h2 class="section-title mb-1">Choose promotion duration</h2>
                <p class="section-sub mb-3">Deducted from your available wallet balance.</p>

                <form @submit.prevent="submit">
                    <div class="mb-3 grid grid-cols-5 gap-2">
                        <button
                            v-for="p in prices"
                            :key="p.days"
                            type="button"
                            class="rounded-lg p-2 text-center"
                            :class="
                                form.days === p.days
                                    ? 'bg-brand-50 ring-2 ring-brand-500'
                                    : 'ring-1 ring-slate-200 hover:ring-brand-300'
                            "
                            @click="form.days = p.days"
                        >
                            <p class="text-xl font-bold text-slate-900">{{ p.days }}</p>
                            <p class="text-xs text-slate-500">day{{ p.days > 1 ? 's' : '' }}</p>
                            <p class="money mt-1 text-sm font-semibold text-primary">{{ p.price_formatted }}</p>
                        </button>
                    </div>

                    <!-- Balance check -->
                    <div class="mb-3 flex flex-col gap-2 rounded-lg bg-slate-50 p-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Promotion price</span>
                            <span class="money font-semibold">{{ bdt(selectedPrice) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Current balance</span>
                            <span class="money">{{ walletBalanceFormatted }}</span>
                        </div>
                        <div class="flex justify-between border-t border-slate-200 pt-2 font-bold">
                            <span>Balance after</span>
                            <span class="money" :class="balanceAfter < 0 ? 'text-rose-600' : 'text-mint-700'">
                                {{ bdt(balanceAfter) }}
                            </span>
                        </div>
                        <p v-if="balanceAfter < 0" class="mt-1 text-xs text-rose-600">
                            ⚠ Insufficient wallet balance for this duration. Please choose a shorter duration or add
                            funds.
                        </p>
                    </div>

                    <label class="mb-3 flex items-start gap-2 text-sm text-slate-900">
                        <input type="checkbox" required class="checkbox mt-1" />
                        <span>
                            I understand that promotion fees are non-refundable unless platform policy explicitly
                            permits a refund.
                        </span>
                    </label>

                    <div class="flex gap-3">
                        <button type="submit" class="btn-primary btn-lg" :disabled="form.processing">
                            {{ form.processing ? 'Activating…' : 'Activate promotion →' }}
                        </button>
                        <Link :href="route('dashboard.promotions')" class="btn-outline">Cancel</Link>
                    </div>
                </form>
            </div>
        </div>
    </DashboardLayout>
</template>
