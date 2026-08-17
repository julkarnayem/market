<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const appName = computed(() => usePage().props.appName);

const form = useForm({
    email: '',
    password: '',
    // The Blade form shipped this checked, so the default is preserved.
    remember: true,
});

function submit() {
    // AuthenticatedSessionController::store throws a ValidationException on the
    // `email` key for bad credentials, so both failures surface under the email field.
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Login" />

    <AuthLayout title="Login" :subtitle="`Welcome back to ${appName}`">
        <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
            <div>
                <label for="email" class="label">Enter your email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    name="email"
                    placeholder="Enter your email"
                    autocomplete="email"
                    required
                    autofocus
                    class="input"
                    :class="form.errors.email && 'input-error'"
                    :aria-invalid="Boolean(form.errors.email)"
                    :aria-describedby="form.errors.email ? 'email-error' : undefined"
                />
                <p v-if="form.errors.email" id="email-error" class="field-error">{{ form.errors.email }}</p>
            </div>

            <div>
                <label for="password" class="label">Password</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                    class="input"
                    :class="form.errors.password && 'input-error'"
                    :aria-invalid="Boolean(form.errors.password)"
                    :aria-describedby="form.errors.password ? 'password-error' : undefined"
                />
                <p v-if="form.errors.password" id="password-error" class="field-error">{{ form.errors.password }}</p>
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-slate-500">
                    <input v-model="form.remember" type="checkbox" name="remember" class="checkbox" />
                    Remember me
                </label>
                <Link :href="route('password.request')" class="text-sm font-semibold text-brand-600 hover:text-brand-700 hover:underline">
                    Forgot password?
                </Link>
            </div>

            <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                {{ form.processing ? 'Logging in…' : 'Login' }}
            </button>
        </form>

        <p class="mt-4 text-center text-sm text-slate-500">
            Don't have an account?
            <Link :href="route('register')" class="font-semibold text-brand-600 hover:text-brand-700 hover:underline">Signup</Link>
        </p>
    </AuthLayout>
</template>
