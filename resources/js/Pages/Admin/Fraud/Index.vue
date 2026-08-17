<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import type { Paginated } from '@/types';

/** One queue row — whitelisted by Admin\FraudController::index(). */
interface ReviewRow {
    id: number;
    user_name: string;
    user_email: string;
    user_url: string | null;
    risk_score: number;
    flags: string[];
    status: string;
    reviewer: string | null;
    updated: string;
}
interface Tab {
    value: string;
    label: string;
}

defineProps<{
    reviews: Paginated<ReviewRow>;
    filters: { status: string };
    tabs: Tab[];
}>();

// FraudService::THRESHOLD_HIGH = 70, THRESHOLD_REVIEW = 30.
function scoreTone(score: number): string {
    if (score >= 70) return 'text-rose-600';
    if (score >= 30) return 'text-amber-600';
    return 'text-slate-500';
}
</script>

<template>
    <AdminLayout title="Fraud Review" heading="Fraud Risk Queue">
        <p class="section-sub mb-3">
            Users flagged by the anti-fraud system. Review before taking action — scores are advisory.
        </p>

        <!-- Status tabs (Inertia links carrying ?status=) -->
        <div class="mb-3 flex flex-wrap gap-1 border-b border-slate-200">
            <Link
                v-for="t in tabs"
                :key="t.value"
                :href="route('admin.fraud', { status: t.value })"
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
                        <th>User</th>
                        <th>Risk Score</th>
                        <th>Flags</th>
                        <th>Status</th>
                        <th>Reviewed by</th>
                        <th>Last updated</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in reviews.data" :key="r.id">
                        <td class="font-medium">
                            {{ r.user_name }}
                            <p class="text-xs text-slate-500">{{ r.user_email }}</p>
                        </td>
                        <td>
                            <span class="money font-bold" :class="scoreTone(r.risk_score)">{{ r.risk_score }}</span>
                        </td>
                        <td class="max-w-[200px]">
                            <span v-for="f in r.flags" :key="f" class="badge-rose mr-1 text-xs">{{ f }}</span>
                            <span v-if="r.flags.length === 0" class="text-xs text-slate-300">—</span>
                        </td>
                        <td><StatusBadge :status="r.status" /></td>
                        <td class="text-sm text-slate-500">{{ r.reviewer ?? '—' }}</td>
                        <td class="text-xs text-slate-500">{{ r.updated }}</td>
                        <td class="text-right">
                            <Link v-if="r.user_url" :href="r.user_url" class="btn-ghost btn-sm">Review</Link>
                        </td>
                    </tr>
                    <tr v-if="reviews.data.length === 0">
                        <td colspan="7" class="py-4 text-center text-slate-500">No fraud cases in this queue.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards -->
        <div class="flex flex-col gap-2 sm:hidden">
            <div v-for="r in reviews.data" :key="r.id" class="card-p">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-medium">{{ r.user_name }}</p>
                        <p class="text-xs text-slate-500">{{ r.user_email }}</p>
                    </div>
                    <span class="money text-lg font-bold" :class="scoreTone(r.risk_score)">{{ r.risk_score }}</span>
                </div>
                <div class="mt-2 flex flex-wrap gap-1">
                    <span v-for="f in r.flags" :key="f" class="badge-rose text-xs">{{ f }}</span>
                </div>
                <div class="mt-2 flex items-center justify-between">
                    <StatusBadge :status="r.status" />
                    <span class="text-xs text-slate-400">{{ r.updated }}</span>
                </div>
                <Link v-if="r.user_url" :href="r.user_url" class="btn-outline btn-sm mt-2 block text-center">
                    Review
                </Link>
            </div>
            <p v-if="reviews.data.length === 0" class="card-p text-center text-sm text-slate-500">
                No fraud cases in this queue.
            </p>
        </div>

        <div class="mt-3">
            <Pagination :links="reviews.links" :total="reviews.total" />
        </div>
    </AdminLayout>
</template>
