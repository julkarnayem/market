<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AuthSteps from '@/Components/AuthSteps.vue';

defineProps<{
    /** The verified number this account will be created with. */
    phone: string;
}>();

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(route('register.store'), {
        onError: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Complete Signup" />

    <AuthLayout title="Complete Signup" subtitle="Almost done! Fill in your details.">
        <template #steps>
            <AuthSteps :current="3" />
        </template>

        <p class="-mt-3 mb-5 text-center text-xs text-slate-500">
            Verified number: <span class="font-semibold text-slate-900">{{ phone }}</span>
        </p>

        <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="first_name" class="label">First Name</label>
                    <input
                        id="first_name"
                        v-model="form.first_name"
                        type="text"
                        name="first_name"
                        placeholder="First name"
                        autocomplete="given-name"
                        maxlength="100"
                        required
                        autofocus
                        class="input"
                        :class="form.errors.first_name && 'input-error'"
                        :aria-invalid="Boolean(form.errors.first_name)"
                        :aria-describedby="form.errors.first_name ? 'first-name-error' : undefined"
                    />
                    <p v-if="form.errors.first_name" id="first-name-error" class="field-error">{{ form.errors.first_name }}</p>
                </div>

                <div>
                    <label for="last_name" class="label">Last Name</label>
                    <input
                        id="last_name"
                        v-model="form.last_name"
                        type="text"
                        name="last_name"
                        placeholder="Last name"
                        autocomplete="family-name"
                        maxlength="100"
                        required
                        class="input"
                        :class="form.errors.last_name && 'input-error'"
                        :aria-invalid="Boolean(form.errors.last_name)"
                        :aria-describedby="form.errors.last_name ? 'last-name-error' : undefined"
                    />
                    <p v-if="form.errors.last_name" id="last-name-error" class="field-error">{{ form.errors.last_name }}</p>
                </div>
            </div>

            <div>
                <label for="email" class="label">Email Address</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    name="email"
                    placeholder="Enter your email"
                    autocomplete="email"
                    maxlength="255"
                    required
                    class="input"
                    :class="form.errors.email && 'input-error'"
                    :aria-invalid="Boolean(form.errors.email)"
                    :aria-describedby="form.errors.email ? 'email-error' : undefined"
                />
                <p v-if="form.errors.email" id="email-error" class="field-error">{{ form.errors.email }}</p>
            </div>

            <div>
                <label for="password" class="label">Password</label>
                <!-- "min 6", not the Blade copy's "min 8": Password::min(6) is the actual rule. -->
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    name="password"
                    placeholder="Create a password (min 6 chars)"
                    autocomplete="new-password"
                    required
                    class="input"
                    :class="form.errors.password && 'input-error'"
                    :aria-invalid="Boolean(form.errors.password)"
                    :aria-describedby="form.errors.password ? 'password-error' : undefined"
                />
                <p v-if="form.errors.password" id="password-error" class="field-error">{{ form.errors.password }}</p>
            </div>

            <div>
                <label for="password_confirmation" class="label">Confirm Password</label>
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
                {{ form.processing ? 'Creating account…' : 'Create Account ✓' }}
            </button>
        </form>

        <p class="mt-4 text-center text-xs text-slate-500">
            By signing up you agree to our
            <Link :href="route('legal', 'terms')" class="font-semibold text-brand-600 hover:underline">Terms</Link>
            and
            <Link :href="route('legal', 'privacy')" class="font-semibold text-brand-600 hover:underline">Privacy Policy</Link>.
        </p>
    </AuthLayout>
</template>
