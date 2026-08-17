<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

/** Whitelisted by Admin\NotificationController::index(). */
defineProps<{
    stats: { total: number; sent: number; failed: number };
    provider: { name: string; enabled: boolean };
}>();

const num = (n: number): string => n.toLocaleString();
</script>

<template>
    <AdminLayout title="Notifications" heading="Notification Management">
        <!-- SMS counts -->
        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <Link :href="route('admin.sms-logs')" class="card-p transition-shadow hover:shadow-md">
                <p class="text-xs font-medium text-slate-500">📨 SMS Logs</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ num(stats.total) }}</p>
                <p class="mt-1 text-xs text-brand-600">View all →</p>
            </Link>
            <div class="card-p bg-emerald-50">
                <p class="text-xs font-medium text-emerald-700">✓ SMS sent</p>
                <p class="mt-1 text-2xl font-bold text-emerald-700">{{ num(stats.sent) }}</p>
            </div>
            <div class="card-p bg-rose-50">
                <p class="text-xs font-medium text-rose-700">✕ SMS failed</p>
                <p class="mt-1 text-2xl font-bold text-rose-700">{{ num(stats.failed) }}</p>
            </div>
        </div>

        <!-- Provider status -->
        <div class="card-p">
            <h2 class="section-title mb-2">SMS Provider Status</h2>
            <div class="flex items-center gap-3">
                <span
                    class="h-3 w-3 rounded-full ring-2 ring-offset-1"
                    :class="provider.enabled ? 'bg-emerald-500 ring-emerald-300' : 'bg-rose-500 ring-rose-300'"
                ></span>
                <span class="text-sm font-medium text-slate-700">
                    {{ provider.name }} {{ provider.enabled ? 'configured' : 'not configured' }}
                </span>
            </div>
            <p v-if="!provider.enabled" class="mt-2 text-xs text-slate-500">
                Set <code>BULKSMSBD_API_KEY</code>, <code>BULKSMSBD_SENDER_ID</code> and
                <code>BULKSMSBD_ENABLED=true</code> in <code>.env</code>.
            </p>
        </div>
    </AdminLayout>
</template>
