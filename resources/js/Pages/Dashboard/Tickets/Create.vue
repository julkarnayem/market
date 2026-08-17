<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';

// Values must match TicketController@store validation:
//   category in:account,verification,listing,order,payment,withdrawal,dispute,promotion,technical,other
//   priority in:low,normal,high   (the old Blade offered "medium", which the server rejected)
const CATEGORIES = [
    { value: 'account', label: 'Account' },
    { value: 'order', label: 'Order' },
    { value: 'payment', label: 'Payment' },
    { value: 'listing', label: 'Listing' },
    { value: 'other', label: 'Other' },
] as const;

const PRIORITIES = [
    { value: 'low', label: 'Low' },
    { value: 'normal', label: 'Normal' },
    { value: 'high', label: 'High' },
] as const;

const form = useForm<{
    subject: string;
    category: string;
    priority: string;
    message: string;
}>({
    subject: '',
    category: 'account',
    priority: 'normal',
    message: '',
});

function submit() {
    form.post(route('dashboard.tickets.store'));
}
</script>

<template>
    <DashboardLayout title="New Ticket" heading="Open a Support Ticket">
        <Breadcrumb
            :items="[
                { label: 'Support', url: route('dashboard.tickets') },
                { label: 'New Ticket' },
            ]"
        />

        <form class="card-p flex max-w-2xl flex-col gap-3" @submit.prevent="submit">
            <div>
                <label class="label">Subject <span class="text-rose-500">*</span></label>
                <input
                    v-model="form.subject"
                    type="text"
                    class="input"
                    :class="form.errors.subject && 'input-error'"
                    maxlength="255"
                    required
                />
                <p v-if="form.errors.subject" class="field-error">{{ form.errors.subject }}</p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label">Category <span class="text-rose-500">*</span></label>
                    <select
                        v-model="form.category"
                        class="select"
                        :class="form.errors.category && 'input-error'"
                    >
                        <option v-for="c in CATEGORIES" :key="c.value" :value="c.value">{{ c.label }}</option>
                    </select>
                    <p v-if="form.errors.category" class="field-error">{{ form.errors.category }}</p>
                </div>
                <div>
                    <label class="label">Priority <span class="text-rose-500">*</span></label>
                    <select
                        v-model="form.priority"
                        class="select"
                        :class="form.errors.priority && 'input-error'"
                    >
                        <option v-for="p in PRIORITIES" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                    <p v-if="form.errors.priority" class="field-error">{{ form.errors.priority }}</p>
                </div>
            </div>

            <div>
                <label class="label">Message <span class="text-rose-500">*</span></label>
                <textarea
                    v-model="form.message"
                    rows="6"
                    class="textarea"
                    :class="form.errors.message && 'input-error'"
                    maxlength="5000"
                    required
                    placeholder="Describe your issue in detail…"
                ></textarea>
                <p v-if="form.errors.message" class="field-error">{{ form.errors.message }}</p>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="btn-primary" :disabled="form.processing">Submit ticket</button>
                <Link :href="route('dashboard.tickets')" class="btn-outline">Cancel</Link>
            </div>
        </form>
    </DashboardLayout>
</template>
