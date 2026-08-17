<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const email = computed(() => usePage().props.auth.user?.email);

const resend = useForm({});

function submit() {
    // EmailVerificationController::send flashes a readable `status`, which
    // PublicLayout renders from the shared flash prop.
    resend.post(route('verification.send'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Verify email" />

    <AuthLayout title="Verify your email">
        <template #subtitle>
            We sent a verification link to
            <span v-if="email" class="font-semibold text-slate-900">{{ email }}</span>
            <span v-else>your inbox</span>. Click it to activate your account.
        </template>

        <form @submit.prevent="submit">
            <button type="submit" class="btn-primary w-full" :disabled="resend.processing">
                {{ resend.processing ? 'Sending…' : 'Resend verification email' }}
            </button>
        </form>

        <Link
            :href="route('logout')"
            method="post"
            as="button"
            type="button"
            class="btn-ghost mt-2 w-full"
        >
            Log out
        </Link>
    </AuthLayout>
</template>
