<script setup lang="ts">
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import type { Paginated } from '@/types';

/** One SMS log row — whitelisted by Admin\NotificationController::smsLogs(). */
interface SmsLogRow {
    id: number;
    user: string;
    phone: string;
    template: string;
    status: string;
    attempts: number;
    sent: string | null;
    error: string | null;
}
interface StatusOption {
    value: string;
    label: string;
}

const props = defineProps<{
    logs: Paginated<SmsLogRow>;
    filters: { status: string; template: string };
    statuses: StatusOption[];
    templates: string[];
}>();

// Local mirror of the server-echoed filters so the controls follow the URL.
const filterState = reactive({
    status: props.filters.status,
    template: props.filters.template,
});

function applyFilters() {
    router.get(
        route('admin.sms-logs'),
        {
            status: filterState.status === 'all' ? undefined : filterState.status,
            template: filterState.template || undefined,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}
</script>

<template>
    <AdminLayout title="SMS Logs" heading="SMS Logs">
        <Breadcrumb
            :items="[{ label: 'Notifications', url: route('admin.notifications') }, { label: 'SMS Logs' }]"
        />

        <!-- Filters -->
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <select v-model="filterState.status" class="select w-auto" @change="applyFilters">
                <option value="all">All status</option>
                <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
            <select v-model="filterState.template" class="select w-auto" @change="applyFilters">
                <option value="">All templates</option>
                <option v-for="t in templates" :key="t" :value="t">{{ t }}</option>
            </select>
        </div>

        <!-- Desktop table -->
        <div class="table-wrap hidden sm:block">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Phone</th>
                        <th>Template</th>
                        <th>Status</th>
                        <th>Attempts</th>
                        <th>Sent</th>
                        <th>Error</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="log in logs.data" :key="log.id">
                        <td class="text-sm">{{ log.user }}</td>
                        <td class="font-mono text-xs">{{ log.phone }}</td>
                        <td class="text-xs">{{ log.template }}</td>
                        <td><StatusBadge :status="log.status" /></td>
                        <td class="text-center text-sm">{{ log.attempts }}</td>
                        <td class="text-xs text-slate-500">{{ log.sent ?? '—' }}</td>
                        <td class="max-w-[200px] truncate text-xs text-rose-600">{{ log.error ?? '—' }}</td>
                    </tr>
                    <tr v-if="logs.data.length === 0">
                        <td colspan="7" class="py-4 text-center text-slate-500">No SMS logs.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards -->
        <div class="flex flex-col gap-2 sm:hidden">
            <div v-for="log in logs.data" :key="log.id" class="card-p">
                <div class="flex justify-between gap-2">
                    <span class="text-sm font-medium text-slate-900">{{ log.user }}</span>
                    <StatusBadge :status="log.status" />
                </div>
                <div class="mt-1 flex justify-between text-xs text-slate-500">
                    <span class="font-mono">{{ log.phone }}</span>
                    <span>{{ log.sent ?? '—' }}</span>
                </div>
                <p class="mt-1 text-xs text-slate-500">{{ log.template }}</p>
                <p v-if="log.error" class="mt-1 text-xs text-rose-600">{{ log.error }}</p>
            </div>
            <p v-if="logs.data.length === 0" class="card-p text-center text-sm text-slate-500">
                No SMS logs.
            </p>
        </div>

        <div class="mt-3">
            <Pagination :links="logs.links" :total="logs.total" />
        </div>
    </AdminLayout>
</template>
