<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AuthSteps from '@/Components/AuthSteps.vue';
import PhoneField from '@/Components/PhoneField.vue';

const form = useForm({ phone: '' });

function submit() {
    // On success sendOtp() redirects to register.verify; every failure path
    // (already registered, blocked number, unexpired OTP) comes back as a
    // `phone` error, so the field renders all of them.
    form.post(route('register.send-otp'));
}
</script>

<template>
    <Head title="Sign up" />

    <AuthLayout title="Signup" subtitle="Enter your mobile number to get started">
        <template #steps>
            <AuthSteps :current="1" />
        </template>

        <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
            <PhoneField
                v-model="form.phone"
                :error="form.errors.phone"
                label="Mobile Number"
                hint="We'll send a 6-digit OTP to verify your number."
            />

            <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                {{ form.processing ? 'Sending OTP…' : 'Send OTP →' }}
            </button>
        </form>

        <p class="mt-4 text-center text-sm text-slate-500">
            Already have an account?
            <Link :href="route('login')" class="font-semibold text-brand-600 hover:text-brand-700 hover:underline">Login</Link>
        </p>
    </AuthLayout>
</template>
