<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import VerificationActions from '@/Components/VerificationActions.vue';
import type { Paginated } from '@/types';

/** One verification row — whitelisted by Admin\VerificationController::index(). */
interface VerificationRow {
    id: number;
    user_name: string;
    type_label: string;
    submitted: string;
    status: string;
    reviewer_name: string;
    is_pending: boolean;
    url: string;
}
interface Tab {
    value: string;
    label: string;
}

defineProps<{
    verifications: Paginated<VerificationRow>;
    tab: string;
    tabs: Tab[];
}>();
</script>

<template>
    <AdminLayout title="Verification" heading="Seller Verification">
        <!-- Status tabs -->
        <div class="tabs mb-3">
            <Link
                v-for="t in tabs"
                :key="t.value"
                :href="route('admin.verification', { tab: t.value })"
                class="tab"
                :class="tab === t.value && 'tab-active'"
            >
                {{ t.label }}
            </Link>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Type</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Reviewer</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="v in verifications.data" :key="v.id">
                        <td class="font-medium text-slate-900">{{ v.user_name }}</td>
                        <td class="text-sm">{{ v.type_label }}</td>
                        <td class="text-xs text-slate-500">{{ v.submitted }}</td>
                        <td><StatusBadge :status="v.status" /></td>
                        <td class="text-xs text-slate-500">{{ v.reviewer_name }}</td>
                        <td>
                            <div class="flex flex-wrap items-center gap-1">
                                <Link :href="v.url" class="btn-ghost btn-sm">View</Link>
                                <VerificationActions :id="v.id" :status="v.status" />
                            </div>
                        </td>
                    </tr>
                    <tr v-if="verifications.data.length === 0">
                        <td colspan="6" class="py-4 text-center text-slate-500">No verification submissions.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            <Pagination :links="verifications.links" :total="verifications.total" />
        </div>
    </AdminLayout>
</template>
