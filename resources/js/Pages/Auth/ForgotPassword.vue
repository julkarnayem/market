<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AuthSteps from '@/Components/AuthSteps.vue';
import PhoneField from '@/Components/PhoneField.vue';

const form = useForm({ phone: '' });

function submit() {
    // Failure paths (unknown number, blocked number, unexpired OTP) all come
    // back as a `phone` error from PasswordResetLinkController::store.
    form.post(route('password.email'));
}
</script>

<template>
    <Head title="Forgot Password" />

    <AuthLayout title="Forgot Password" subtitle="Enter your registered mobile number to receive an OTP">
        <template #steps>
            <AuthSteps :current="1" />
        </template>

        <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
            <PhoneField
                v-model="form.phone"
                :error="form.errors.phone"
                label="Mobile Number"
                hint="We'll send a 6-digit OTP to reset your password."
            />

            <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                {{ form.processing ? 'Sending OTP…' : 'Send OTP →' }}
            </button>
        </form>

        <p class="mt-4 text-center text-sm text-slate-500">
            Remember your password?
            <Link :href="route('login')" class="font-semibold text-brand-600 hover:text-brand-700 hover:underline">Login</Link>
        </p>
    </AuthLayout>
</template>
