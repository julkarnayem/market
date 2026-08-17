<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';

const props = defineProps<{
    order: { id: number; order_number: string };
}>();

const form = useForm<{ reason: string }>({ reason: '' });

function submit() {
    form.post(route('dashboard.orders.dispute.submit', props.order.id));
}
</script>

<template>
    <DashboardLayout title="Open Dispute" heading="Open a Dispute">
        <Breadcrumb
            :items="[
                { label: 'Orders', url: route('dashboard.orders') },
                { label: order.order_number, url: route('dashboard.orders.show', order.id) },
                { label: 'Dispute' },
            ]"
        />

        <div class="max-w-xl">
            <div class="alert-error mb-3">
                <div>
                    <p class="font-semibold">Opening a dispute is a serious action.</p>
                    <p class="mt-1 text-sm">
                        Only open a dispute if you genuinely have an issue with the delivery. Admin will review
                        within 24–48 hours.
                    </p>
                </div>
            </div>

            <div class="card-p">
                <h2 class="section-title mb-3">Dispute — Order {{ order.order_number }}</h2>
                <form class="flex flex-col gap-3" novalidate @submit.prevent="submit">
                    <div>
                        <label class="label">Reason for dispute <span class="text-rose-500">*</span></label>
                        <textarea
                            v-model="form.reason"
                            rows="5"
                            class="textarea"
                            :class="form.errors.reason && 'input-error'"
                            required
                            minlength="20"
                            placeholder="Clearly describe the issue with the delivery. Include specific details of what was promised vs. what was delivered…"
                        ></textarea>
                        <p class="field-hint">Min 20 characters.</p>
                        <p v-if="form.errors.reason" class="field-error">{{ form.errors.reason }}</p>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" class="btn-danger btn-lg" :disabled="form.processing">
                            {{ form.processing ? 'Submitting…' : '⚑ Open dispute' }}
                        </button>
                        <Link :href="route('dashboard.orders.show', order.id)" class="btn-outline">Cancel</Link>
                    </div>
                </form>
            </div>
        </div>
    </DashboardLayout>
</template>
