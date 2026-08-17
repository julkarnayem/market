<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AuthSteps from '@/Components/AuthSteps.vue';

const form = useForm({
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(route('password.update'), {
        onError: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Reset Password" />

    <AuthLayout title="New Password" subtitle="Create a strong new password for your account">
        <template #steps>
            <AuthSteps :current="3" />
        </template>

        <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
            <div>
                <label for="password" class="label">New Password</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    name="password"
                    placeholder="Min 6 characters"
                    autocomplete="new-password"
                    required
                    autofocus
                    class="input"
                    :class="form.errors.password && 'input-error'"
                    :aria-invalid="Boolean(form.errors.password)"
                    :aria-describedby="form.errors.password ? 'password-error' : undefined"
                />
                <p v-if="form.errors.password" id="password-error" class="field-error">{{ form.errors.password }}</p>
            </div>

            <div>
                <label for="password_confirmation" class="label">Confirm New Password</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    name="password_confirmation"
                    placeholder="Confirm your password"
                    autocomplete="new-password"
                    required
                    class="input"
                />
            </div>

            <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                {{ form.processing ? 'Resetting…' : 'Reset Password ✓' }}
            </button>
        </form>
    </AuthLayout>
</template>
