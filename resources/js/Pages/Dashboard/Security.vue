<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

defineProps<{
    /** Pre-formatted by ProfileController (diffForHumans, or a first-session hint). */
    lastLogin: string;
}>();

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.patch(route('dashboard.security.password'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <DashboardLayout title="Security" heading="Security">
        <div class="flex max-w-2xl flex-col gap-3">
            <div class="card-p">
                <h2 class="section-title mb-3">Change Password</h2>
                <form class="flex flex-col gap-3" novalidate @submit.prevent="submit">
                    <div>
                        <label for="current_password" class="label">Current password</label>
                        <input
                            id="current_password"
                            v-model="form.current_password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="input"
                            :class="form.errors.current_password && 'input-error'"
                        />
                        <p v-if="form.errors.current_password" class="field-error">{{ form.errors.current_password }}</p>
                    </div>

                    <div>
                        <label for="password" class="label">New password</label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            autocomplete="new-password"
                            required
                            class="input"
                            :class="form.errors.password && 'input-error'"
                        />
                        <p v-if="form.errors.password" class="field-error">{{ form.errors.password }}</p>
                    </div>

                    <div>
                        <label for="password_confirmation" class="label">Confirm new password</label>
                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            required
                            class="input"
                        />
                    </div>

                    <button type="submit" class="btn-primary self-start" :disabled="form.processing">
                        {{ form.processing ? 'Updating…' : 'Update password' }}
                    </button>
                </form>
            </div>

            <div class="card-p">
                <h2 class="section-title mb-1">Two-Factor Authentication</h2>
                <p class="section-sub mb-2">Adds an extra layer of security to your account.</p>
                <div class="alert-warning">2FA setup is available in a future release.</div>
            </div>

            <div class="card-p">
                <h2 class="section-title mb-1">Login History</h2>
                <p class="section-sub mb-2">Recent sign-ins to your account.</p>
                <p class="text-sm text-slate-500">Last login: {{ lastLogin }}</p>
            </div>
        </div>
    </DashboardLayout>
</template>
