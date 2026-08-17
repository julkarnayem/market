<script setup lang="ts">
import { computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

defineProps<{
    /** hasVerifiedEmail() is not part of the shared auth payload. */
    emailVerified: boolean;
}>();

const user = computed(() => usePage().props.auth.user!);

const resend = useForm({});
function resendVerification() {
    resend.post(route('verification.send'), { preserveScroll: true });
}
</script>

<template>
    <DashboardLayout title="Account Settings">
        <div class="flex max-w-xl flex-col gap-3">
            <!-- Email address -->
            <div class="card-p">
                <h2 class="section-title mb-3">Email Address</h2>
                <dl class="text-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 py-2">
                        <dt class="text-slate-500">Current email</dt>
                        <dd class="font-medium text-slate-900">{{ user.email }}</dd>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <dt class="text-slate-500">Verified</dt>
                        <dd class="flex items-center gap-2">
                            <span v-if="emailVerified" class="badge-mint text-xs">✓ Verified</span>
                            <template v-else>
                                <span class="badge-amber text-xs">Unverified</span>
                                <button
                                    type="button"
                                    class="text-xs font-medium text-brand-600 hover:underline disabled:opacity-50"
                                    :disabled="resend.processing"
                                    @click="resendVerification"
                                >
                                    {{ resend.processing ? 'Sending…' : 'Resend verification' }}
                                </button>
                            </template>
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Notification preferences -->
            <div class="card-p">
                <h2 class="section-title mb-3">Notification Preferences</h2>
                <div class="flex flex-col gap-2 text-sm text-slate-500">
                    <div class="flex items-center justify-between py-1">
                        <span>Order updates</span>
                        <span class="badge-mint text-xs">Always on</span>
                    </div>
                    <div class="flex items-center justify-between py-1">
                        <span>Offer notifications</span>
                        <span class="badge-mint text-xs">Always on</span>
                    </div>
                    <div class="flex items-center justify-between py-1">
                        <span>Promotion expiry warnings</span>
                        <span class="badge-mint text-xs">Always on</span>
                    </div>
                    <p class="border-t border-slate-100 pt-2 text-xs text-slate-400">
                        Granular notification preferences will be available in a future update.
                    </p>
                </div>
            </div>

            <!-- Danger zone -->
            <div class="card-p">
                <h2 class="section-title mb-3 text-rose-600">Danger Zone</h2>
                <p class="mb-3 text-sm text-slate-500">
                    Need to close your account? Contact support — we'll process your request within 5 business days.
                </p>
                <Link :href="route('dashboard.tickets.create')" class="btn-outline !text-rose-600">
                    Request account closure
                </Link>
            </div>
        </div>
    </DashboardLayout>
</template>
