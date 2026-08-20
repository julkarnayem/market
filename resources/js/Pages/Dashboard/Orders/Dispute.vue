<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';

interface ReasonOption {
    value: string;
    label: string;
}

const props = defineProps<{
    order: { id: number; order_number: string; asset_title: string; total: string };
    /** From App\Enums\DisputeReason::options() — the same list the request validates against. */
    reasons: ReasonOption[];
}>();

const form = useForm<{ reason_code: string; description: string }>({
    reason_code: '',
    description: '',
});

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
                        The seller gets 48 hours to respond, and you can settle it between yourselves before
                        anyone from our team has to decide. The seller's payment stays held until it is closed.
                    </p>
                </div>
            </div>

            <div class="card-p">
                <h2 class="section-title mb-1">Dispute — Order {{ order.order_number }}</h2>
                <p class="section-sub mb-3">
                    {{ order.asset_title }} · <span class="money font-semibold">{{ order.total }}</span>
                </p>

                <form class="flex flex-col gap-3" novalidate @submit.prevent="submit">
                    <div>
                        <label class="label" for="reason_code">
                            What went wrong? <span class="text-rose-500">*</span>
                        </label>
                        <select
                            id="reason_code"
                            v-model="form.reason_code"
                            class="select"
                            :class="form.errors.reason_code && 'input-error'"
                            required
                        >
                            <option value="" disabled>Choose a reason…</option>
                            <option v-for="r in reasons" :key="r.value" :value="r.value">{{ r.label }}</option>
                        </select>
                        <p v-if="form.errors.reason_code" class="field-error">{{ form.errors.reason_code }}</p>
                    </div>

                    <div>
                        <label class="label" for="description">
                            Describe the problem <span class="text-rose-500">*</span>
                        </label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="5"
                            class="textarea"
                            :class="form.errors.description && 'input-error'"
                            required
                            minlength="20"
                            maxlength="2000"
                            placeholder="Clearly describe the issue with the delivery. Include specific details of what was promised vs. what was delivered…"
                        ></textarea>
                        <p class="field-hint">
                            Min 20 characters. You can attach screenshots as evidence once the dispute is open.
                        </p>
                        <p v-if="form.errors.description" class="field-error">{{ form.errors.description }}</p>
                    </div>

                    <div class="flex gap-3">
                        <button
                            type="submit"
                            class="btn-danger btn-lg"
                            :disabled="form.processing || !form.reason_code"
                        >
                            {{ form.processing ? 'Submitting…' : '⚑ Open dispute' }}
                        </button>
                        <Link :href="route('dashboard.orders.show', order.id)" class="btn-outline">Cancel</Link>
                    </div>
                </form>
            </div>
        </div>
    </DashboardLayout>
</template>
