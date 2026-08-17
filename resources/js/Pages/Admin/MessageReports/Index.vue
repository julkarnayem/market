<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import type { Paginated } from '@/types';

/** One report row — whitelisted by Admin\MessageReportController::index(). */
interface ReportRow {
    id: number;
    reporter: string;
    message: string;
    sender: string;
    reason: string;
    order_number: string | null;
    order_url: string | null;
    status: string;
    date: string;
}
interface Tab {
    value: string;
    label: string;
}

defineProps<{
    reports: Paginated<ReportRow>;
    filters: { status: string };
    tabs: Tab[];
}>();

// Mirror the server @can('disputes.manage') gate on the review action.
const canManage = computed(() => {
    const u = usePage().props.auth.user;
    return !!u && (u.roles.includes('admin') || u.permissions.includes('disputes.manage'));
});

// Per-row chosen action (defaults to dismiss) + one in-flight row at a time.
const actions = reactive<Record<number, string>>({});
const reviewingId = ref<number | null>(null);
function review(id: number): void {
    reviewingId.value = id;
    router.post(
        route('admin.message-reports.review', id),
        { action: actions[id] ?? 'dismiss' },
        { preserveScroll: true, onFinish: () => (reviewingId.value = null) },
    );
}
</script>

<template>
    <AdminLayout title="Message Reports" heading="Message Reports">
        <!-- Status tabs (Inertia links carrying ?status=) -->
        <div class="mb-3 flex flex-wrap gap-1 border-b border-slate-200">
            <Link
                v-for="t in tabs"
                :key="t.value"
                :href="route('admin.message-reports', { status: t.value })"
                class="tab"
                :class="filters.status === t.value && 'tab-active'"
            >
                {{ t.label }}
            </Link>
        </div>

        <!-- Desktop table -->
        <div class="table-wrap hidden sm:block">
            <table class="table">
                <thead>
                    <tr>
                        <th>Reporter</th>
                        <th>Message</th>
                        <th>Sender</th>
                        <th>Reason</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in reports.data" :key="r.id">
                        <td class="text-sm">{{ r.reporter }}</td>
                        <td class="max-w-[180px] truncate text-xs text-slate-500">{{ r.message }}</td>
                        <td class="text-sm">{{ r.sender }}</td>
                        <td><span class="badge-rose text-xs">{{ r.reason }}</span></td>
                        <td class="font-mono text-xs">
                            <Link v-if="r.order_url" :href="r.order_url" class="text-brand-600 hover:underline">
                                {{ r.order_number }}
                            </Link>
                            <span v-else class="text-slate-300">—</span>
                        </td>
                        <td><StatusBadge :status="r.status" /></td>
                        <td class="text-xs text-slate-500">{{ r.date }}</td>
                        <td>
                            <div v-if="canManage && r.status === 'pending'" class="flex justify-end gap-1">
                                <select v-model="actions[r.id]" class="select w-auto text-xs">
                                    <option value="dismiss">Dismiss</option>
                                    <option value="delete_message">Delete message</option>
                                </select>
                                <button
                                    type="button"
                                    class="btn-outline btn-sm"
                                    :disabled="reviewingId === r.id"
                                    @click="review(r.id)"
                                >
                                    Review
                                </button>
                            </div>
                            <span v-else class="text-xs text-slate-400">Reviewed</span>
                        </td>
                    </tr>
                    <tr v-if="reports.data.length === 0">
                        <td colspan="8" class="py-4 text-center text-slate-500">No reports.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards -->
        <div class="flex flex-col gap-2 sm:hidden">
            <div v-for="r in reports.data" :key="r.id" class="card-p">
                <div class="flex justify-between gap-2">
                    <span class="badge-rose text-xs">{{ r.reason }}</span>
                    <StatusBadge :status="r.status" />
                </div>
                <p class="mt-2 text-xs text-slate-500">{{ r.message }}</p>
                <div class="mt-1 flex justify-between text-xs text-slate-400">
                    <span>{{ r.reporter }} → {{ r.sender }}</span>
                    <span>{{ r.date }}</span>
                </div>
                <div v-if="canManage && r.status === 'pending'" class="mt-2 flex gap-1">
                    <select v-model="actions[r.id]" class="select w-auto text-xs">
                        <option value="dismiss">Dismiss</option>
                        <option value="delete_message">Delete message</option>
                    </select>
                    <button
                        type="button"
                        class="btn-outline btn-sm"
                        :disabled="reviewingId === r.id"
                        @click="review(r.id)"
                    >
                        Review
                    </button>
                </div>
            </div>
            <p v-if="reports.data.length === 0" class="card-p text-center text-sm text-slate-500">
                No reports.
            </p>
        </div>

        <div class="mt-3">
            <Pagination :links="reports.links" :total="reports.total" />
        </div>
    </AdminLayout>
</template>
