<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AuthSteps from '@/Components/AuthSteps.vue';
import OtpField from '@/Components/OtpField.vue';

defineProps<{
    /** The number the reset OTP was sent to, echoed back from the session. */
    phone: string;
}>();

const form = useForm({ otp: '' });

function submit() {
    form.post(route('password.verify-otp'), {
        onError: () => form.reset('otp'),
    });
}
</script>

<template>
    <Head title="Verify OTP" />

    <AuthLayout title="Verify OTP">
        <template #steps>
            <AuthSteps :current="2" />
        </template>

        <template #subtitle>
            Enter the 6-digit code sent to<br />
            <span class="font-semibold text-slate-900">{{ phone }}</span>
        </template>

        <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
            <OtpField v-model="form.otp" :error="form.errors.otp" />

            <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                {{ form.processing ? 'Verifying…' : 'Verify OTP →' }}
            </button>
        </form>

        <div class="mt-4 text-center">
            <Link :href="route('password.request')" class="text-sm text-slate-500 hover:text-slate-700">← Change number</Link>
        </div>
        <p class="mt-2 text-center text-xs text-slate-500">OTP is valid for 10 minutes. Cannot be resent before expiry.</p>
        <p class="mt-1 text-center text-xs font-medium text-rose-600">⚠️ 2 wrong attempts = 24 hour block</p>
    </AuthLayout>
</template>
