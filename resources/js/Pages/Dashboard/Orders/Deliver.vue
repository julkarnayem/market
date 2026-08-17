<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';

const props = defineProps<{
    order: { id: number; order_number: string; asset_title: string };
}>();

const form = useForm<{ delivery_note: string; attachment: File | null }>({
    delivery_note: '',
    attachment: null,
});

function onFile(e: Event) {
    const target = e.target as HTMLInputElement;
    form.attachment = target.files?.[0] ?? null;
}

function submit() {
    form.post(route('dashboard.orders.deliver.submit', props.order.id));
}
</script>

<template>
    <DashboardLayout title="Deliver Asset" heading="Deliver Asset">
        <Breadcrumb
            :items="[
                { label: 'Orders', url: route('dashboard.orders', { role: 'seller' }) },
                { label: order.order_number, url: route('dashboard.orders.show', order.id) },
                { label: 'Deliver' },
            ]"
        />

        <div class="max-w-xl">
            <div class="alert-warning mb-3">
                <div>
                    <p class="font-semibold">Delivery is final and visible to the buyer.</p>
                    <p class="mt-1 text-sm">
                        Do not include passwords or sensitive data in the note unless the buyer specifically
                        needs it. Use secure channels where possible.
                    </p>
                </div>
            </div>

            <div class="card-p">
                <h2 class="section-title mb-3">Submit Delivery for Order {{ order.order_number }}</h2>
                <div class="mb-3 rounded-lg bg-slate-50 p-2 text-sm">
                    <span class="font-medium">Asset:</span> {{ order.asset_title }}
                </div>

                <form class="flex flex-col gap-3" novalidate @submit.prevent="submit">
                    <div>
                        <label class="label">
                            Delivery message / credentials <span class="text-rose-500">*</span>
                        </label>
                        <textarea
                            v-model="form.delivery_note"
                            rows="6"
                            class="textarea"
                            :class="form.errors.delivery_note && 'input-error'"
                            required
                            minlength="10"
                            placeholder="Provide clear delivery instructions, transfer details, or credentials needed for the buyer to access the asset…"
                        ></textarea>
                        <p class="field-hint">
                            Min 10 characters. This will only be visible to the buyer and authorized support
                            staff.
                        </p>
                        <p v-if="form.errors.delivery_note" class="field-error">
                            {{ form.errors.delivery_note }}
                        </p>
                    </div>

                    <div>
                        <label class="label">Attachment (optional)</label>
                        <input
                            type="file"
                            class="input"
                            accept=".pdf,.zip,.txt,.jpg,.jpeg,.png"
                            @change="onFile"
                        />
                        <p class="field-hint">Max 20MB. Stored securely — never publicly accessible.</p>
                        <p v-if="form.errors.attachment" class="field-error">{{ form.errors.attachment }}</p>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" class="btn-success btn-lg" :disabled="form.processing">
                            {{ form.processing ? 'Submitting…' : 'Confirm delivery →' }}
                        </button>
                        <Link :href="route('dashboard.orders.show', order.id)" class="btn-outline">Cancel</Link>
                    </div>
                </form>
            </div>
        </div>
    </DashboardLayout>
</template>
