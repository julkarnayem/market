<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

const props = defineProps<{
    asset: {
        id: number;
        slug: string;
        title: string;
        category_name: string | null;
        category_icon: string | null;
        cover_url: string | null;
        price_formatted: string;
        quantity: number;
        available_quantity: number;
    };
    /** Present when the buyer already has a live pending offer; hides the form. */
    userActiveOffer: { amount_formatted: string; expires_human: string } | null;
}>();

const form = useForm<{
    asset_id: number;
    amount_bdt: string;
    quantity: number;
    buyer_message: string;
}>({
    asset_id: props.asset.id,
    amount_bdt: '',
    quantity: 1,
    buyer_message: '',
});

function submit() {
    form.post(route('offers.store'));
}
</script>

<template>
    <DashboardLayout title="Make an Offer" heading="Make an Offer">
        <div class="max-w-lg">
            <!-- Asset summary -->
            <div class="card-p mb-3 flex items-center gap-3">
                <img
                    v-if="asset.cover_url"
                    :src="asset.cover_url"
                    class="h-16 w-16 flex-shrink-0 rounded-lg object-cover"
                    alt=""
                />
                <div
                    v-else
                    class="grid h-16 w-16 flex-shrink-0 place-items-center rounded-lg bg-brand-50 text-3xl"
                >
                    {{ asset.category_icon ?? '🧩' }}
                </div>
                <div>
                    <p class="truncate font-semibold text-slate-900">{{ asset.title }}</p>
                    <p class="text-xs text-slate-500">{{ asset.category_name }}</p>
                    <p class="mt-1 text-sm">
                        Listing price: <span class="money font-bold text-slate-900">{{ asset.price_formatted }}</span>
                    </p>
                </div>
            </div>

            <div v-if="userActiveOffer" class="alert-warning mb-3">
                <div>
                    <p class="font-semibold">You already have an active offer on this listing.</p>
                    <p class="mt-1 text-sm">
                        Amount: <span class="money">{{ userActiveOffer.amount_formatted }}</span> — Expires
                        {{ userActiveOffer.expires_human }}
                    </p>
                </div>
            </div>

            <div v-else class="card-p">
                <h2 class="section-title mb-3">Your Offer</h2>
                <form class="flex flex-col gap-3" novalidate @submit.prevent="submit">
                    <div>
                        <label class="label">Offer Amount (৳) <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 font-mono font-bold text-slate-500">৳</span>
                            <input
                                v-model="form.amount_bdt"
                                type="number"
                                class="input pl-7"
                                :class="form.errors.amount_bdt && 'input-error'"
                                min="1"
                                step="1"
                                placeholder="0"
                                required
                                autofocus
                            />
                        </div>
                        <p v-if="form.errors.amount_bdt" class="field-error">{{ form.errors.amount_bdt }}</p>
                        <p class="field-hint">
                            Listing price is
                            <span class="money font-medium">{{ asset.price_formatted }}</span>. Offer any amount.
                        </p>
                    </div>

                    <div v-if="asset.quantity > 1">
                        <label class="label">Quantity</label>
                        <input
                            v-model.number="form.quantity"
                            type="number"
                            class="input"
                            min="1"
                            :max="asset.available_quantity"
                        />
                    </div>

                    <div>
                        <label class="label">Message to seller (optional)</label>
                        <textarea
                            v-model="form.buyer_message"
                            rows="3"
                            class="textarea"
                            placeholder="Explain your offer or ask a question…"
                        ></textarea>
                    </div>

                    <div class="rounded-lg bg-brand-50 p-3 text-sm">
                        <p class="mb-1 font-semibold text-brand-900">📋 Offer terms</p>
                        <ul class="flex flex-col gap-1 text-primary">
                            <li>• This offer is valid for <strong>8 hours</strong> from submission.</li>
                            <li>• The seller can accept or reject. No counter-offers.</li>
                            <li>• If accepted, you must complete payment immediately.</li>
                            <li>• Seller cannot change the listing price while your offer is active.</li>
                        </ul>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" class="btn-primary btn-lg" :disabled="form.processing">
                            {{ form.processing ? 'Submitting…' : 'Submit offer' }}
                        </button>
                        <Link :href="route('marketplace.show', asset.slug)" class="btn-outline">Cancel</Link>
                    </div>
                </form>
            </div>
        </div>
    </DashboardLayout>
</template>
