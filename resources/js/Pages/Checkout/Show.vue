<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const props = defineProps<{
    asset: {
        title: string;
        url: string;
        cover: string | null;
        icon: string;
        seller_name: string;
    };
    fees: {
        unit_price: string;
        quantity: number;
        subtotal: string;
        has_buyer_fee: boolean;
        buyer_fee_amount: string;
        /** Only set when the buyer fee is a percentage; a fixed fee has no rate. */
        buyer_fee_percent: string | null;
        buyer_total: string;
        seller_fee_percent: string;
        seller_earning: string;
    };
    has_offer: boolean;
    has_bid: boolean;
    order: { asset_id: number; quantity: number; offer_id: number | null; bid_id: number | null };
    gateway_configured: boolean;
    buyer_protection_hours: number;
}>();

// initiate() re-validates every one of these server-side; they are posted only
// so the gateway invoice is built from the same order the buyer just reviewed.
const form = useForm<{ asset_id: number; quantity: number; offer_id: number | null; bid_id: number | null }>({
    ...props.order,
});

function pay(): void {
    // The button is disabled without a gateway, but initiate() would happily
    // build an order and then fail at the gateway — so guard here too.
    if (!props.gateway_configured || form.processing) return;
    // initiate() answers with Inertia::location(), so a success leaves the SPA
    // entirely and navigates to UddoktaPay. form.processing therefore stays true
    // until the browser goes — which is what keeps a double-click from creating
    // a second pending order, as the Blade form did.
    form.post(route('checkout.initiate'));
}
</script>

<template>
    <PublicLayout>
        <Head title="Checkout" />

        <div class="mx-auto max-w-5xl px-3 py-4 sm:px-4">
            <h1 class="font-display mb-4 text-2xl font-bold text-slate-900">Checkout</h1>

            <div
                v-if="!gateway_configured"
                class="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
            >
                <p class="font-semibold">Payment gateway not configured.</p>
                <p class="mt-1">
                    The site administrator needs to configure UddoktaPay credentials
                    (<code>UDDOKTAPAY_API_KEY</code> + <code>UDDOKTAPAY_BASE_URL</code>) in the
                    <code>.env</code> file.
                </p>
            </div>

            <!-- The Blade had grid-cols-[1fr_20rem] with no `grid` display, so the
                 two columns never applied and both cards ran full width. -->
            <div class="grid items-start gap-4 lg:grid-cols-[1fr_20rem]">
                <!-- Order summary -->
                <div class="card-p">
                    <h2 class="section-title mb-3">Order Summary</h2>

                    <div class="mb-3 flex items-center gap-3 border-b border-slate-100 pb-3">
                        <img
                            v-if="asset.cover"
                            :src="asset.cover"
                            :alt="asset.title"
                            class="h-16 w-16 flex-shrink-0 rounded-xl object-cover"
                        />
                        <div
                            v-else
                            class="grid h-16 w-16 flex-shrink-0 place-items-center rounded-xl bg-brand-100 text-2xl"
                        >
                            {{ asset.icon }}
                        </div>
                        <div class="min-w-0">
                            <Link :href="asset.url" class="block truncate font-semibold text-slate-900 hover:underline">
                                {{ asset.title }}
                            </Link>
                            <p class="text-xs text-slate-500">Sold by: {{ asset.seller_name }}</p>
                            <p v-if="has_offer" class="badge-mint mt-1 inline-flex">Offer accepted</p>
                            <p v-else-if="has_bid" class="badge-mint mt-1 inline-flex">Winning bid</p>
                        </div>
                    </div>

                    <!-- Every figure here is calculated server-side. -->
                    <dl class="flex flex-col gap-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Unit price</dt>
                            <dd class="money font-medium">{{ fees.unit_price }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Quantity</dt>
                            <dd>× {{ fees.quantity }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Subtotal</dt>
                            <dd class="money font-medium">{{ fees.subtotal }}</dd>
                        </div>
                        <div v-if="fees.has_buyer_fee" class="flex justify-between">
                            <dt class="text-slate-500">
                                Buyer fee<span v-if="fees.buyer_fee_percent"> ({{ fees.buyer_fee_percent }}%)</span>
                            </dt>
                            <dd class="money text-rose-600">+ {{ fees.buyer_fee_amount }}</dd>
                        </div>
                        <div class="flex justify-between border-t border-slate-100 pt-2 font-bold">
                            <dt>Total payable</dt>
                            <dd class="money text-slate-900">{{ fees.buyer_total }}</dd>
                        </div>
                    </dl>

                    <!-- Seller earning info (for transparency) -->
                    <div class="mt-2 rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-500">
                        Platform fee: {{ fees.seller_fee_percent }}% · Seller receives:
                        <span class="money">{{ fees.seller_earning }}</span>
                    </div>

                    <div class="mt-3 flex flex-col gap-1 rounded-xl bg-mint-50 px-3 py-2 text-sm text-mint-700">
                        <p>🛡 {{ buyer_protection_hours }}-hour buyer protection applies after payment.</p>
                        <p>⚠ After payment you cannot cancel — use the dispute system if needed.</p>
                    </div>
                </div>

                <!-- Payment action -->
                <div class="flex flex-col gap-3">
                    <div class="card-p">
                        <h2 class="section-title mb-1">Payment</h2>
                        <p class="section-sub mb-3">Secure payment via UddoktaPay (BDT)</p>

                        <div class="mb-3 rounded-xl bg-brand-50 p-3 text-center">
                            <p class="text-xs text-slate-500">You will pay</p>
                            <p class="money mt-1 text-2xl font-bold text-slate-900">{{ fees.buyer_total }}</p>
                        </div>

                        <button
                            type="button"
                            class="btn-primary btn-lg w-full"
                            :disabled="!gateway_configured || form.processing"
                            @click="pay"
                        >
                            {{ form.processing ? 'Redirecting to payment…' : `Pay ${fees.buyer_total} via UddoktaPay →` }}
                        </button>

                        <p class="mt-2 text-center text-xs text-slate-500">
                            You will be redirected to UddoktaPay to complete payment securely in BDT.
                        </p>
                    </div>

                    <div class="card">
                        <div class="flex flex-col gap-1.5 p-3 text-xs text-slate-500">
                            <p>✓ Payment is processed by UddoktaPay</p>
                            <p>✓ Funds held until you confirm delivery</p>
                            <p>✓ Seller is notified once payment is verified</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
