<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

// Everything here is already-settled state loaded by a GET. The page has no
// form and no action: the payment was verified and confirmed by the gateway
// callback before this ever rendered, so reloading it does nothing.
defineProps<{
    order: {
        order_number: string;
        status_label: string;
        is_paid: boolean;
        paid_at: string | null;
        quantity: number;
        unit_price_formatted: string;
        buyer_total_formatted: string;
        asset_title: string;
        asset_cover: string | null;
        asset_icon: string;
        seller_name: string;
        url: string;
        conversation_url: string | null;
    };
    buyer_protection_hours: number;
}>();
</script>

<template>
    <PublicLayout>
        <Head title="Payment successful" />

        <div class="mx-auto max-w-2xl px-3 py-8 sm:px-4">
            <div class="card-p text-center">
                <div
                    class="mx-auto grid h-14 w-14 place-items-center rounded-full"
                    :class="order.is_paid ? 'bg-mint-50 text-mint-700' : 'bg-amber-50 text-amber-700'"
                >
                    <span class="text-2xl">{{ order.is_paid ? '✓' : '⏳' }}</span>
                </div>

                <h1 class="font-display mt-3 text-2xl font-bold text-slate-900">
                    {{ order.is_paid ? 'Payment successful' : 'Payment is being confirmed' }}
                </h1>
                <p class="section-sub">
                    Order <span class="font-semibold text-slate-700">#{{ order.order_number }}</span>
                    · {{ order.status_label }}
                </p>

                <!-- What they bought -->
                <div class="mt-5 flex items-center gap-3 rounded-2xl bg-slate-50 p-3 text-left">
                    <img
                        v-if="order.asset_cover"
                        :src="order.asset_cover"
                        :alt="order.asset_title"
                        class="h-14 w-14 flex-shrink-0 rounded-xl object-cover"
                    />
                    <div v-else class="grid h-14 w-14 flex-shrink-0 place-items-center rounded-xl bg-brand-100 text-2xl">
                        {{ order.asset_icon }}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-slate-900">{{ order.asset_title }}</p>
                        <p class="text-xs text-slate-500">
                            Sold by {{ order.seller_name }} ·
                            <span class="money">{{ order.unit_price_formatted }}</span> × {{ order.quantity }}
                        </p>
                    </div>
                </div>

                <dl class="mt-3 flex flex-col gap-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Amount paid</dt>
                        <dd class="money font-bold text-slate-900">{{ order.buyer_total_formatted }}</dd>
                    </div>
                    <div v-if="order.paid_at" class="flex justify-between">
                        <dt class="text-slate-500">Paid at</dt>
                        <dd class="text-slate-700">{{ order.paid_at }}</dd>
                    </div>
                </dl>

                <div class="mt-4 rounded-xl bg-mint-50 px-3 py-2 text-sm text-mint-700">
                    🛡 {{ buyer_protection_hours }}-hour buyer protection has started. Funds are held until you
                    confirm delivery.
                </div>

                <div class="mt-5 flex flex-col gap-2 sm:flex-row">
                    <Link :href="order.url" class="btn-primary btn-lg flex-1">View order</Link>
                    <Link v-if="order.conversation_url" :href="order.conversation_url" class="btn-ghost btn-lg flex-1">
                        Message seller
                    </Link>
                </div>

                <Link :href="route('marketplace.index')" class="mt-3 inline-block text-sm text-slate-500 hover:underline">
                    Continue browsing
                </Link>
            </div>
        </div>
    </PublicLayout>
</template>
