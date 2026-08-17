<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import type { Paginated } from '@/types';

/** One promotion row — whitelisted by Admin\PromotionController::index(). */
interface PromotionRow {
    id: number;
    listing: string;
    seller: string;
    type: string;
    is_manual: boolean;
    days: number | null;
    amount: string;
    starts: string | null;
    ends: string | null;
    status: string;
}
interface Tab {
    value: string;
    label: string;
}

defineProps<{
    promotions: Paginated<PromotionRow>;
    filters: { status: string };
    tabs: Tab[];
}>();

// Mirror the server @can('promotions.feature') gate on the unfeature action.
const canManage = computed(() => {
    const u = usePage().props.auth.user;
    return !!u && (u.roles.includes('admin') || u.permissions.includes('promotions.feature'));
});

// One in-flight row at a time; disables that row's button while the POST runs.
const endingId = ref<number | null>(null);
function unfeature(id: number): void {
    endingId.value = id;
    router.post(
        route('admin.promotions.unfeature', id),
        {},
        { preserveScroll: true, onFinish: () => (endingId.value = null) },
    );
}
</script>

<template>
    <AdminLayout title="Promotions" heading="Promotion Management">
        <!-- Status tabs (Inertia links carrying ?status=) -->
        <div class="mb-3 flex flex-wrap gap-1 border-b border-slate-200">
            <Link
                v-for="t in tabs"
                :key="t.value"
                :href="route('admin.promotions', { status: t.value })"
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
                        <th>Listing</th>
                        <th>Seller</th>
                        <th>Type</th>
                        <th>Days</th>
                        <th>Amount</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in promotions.data" :key="p.id">
                        <td class="max-w-[140px] truncate font-medium">{{ p.listing }}</td>
                        <td class="text-sm">{{ p.seller }}</td>
                        <td>
                            <span class="text-xs" :class="p.is_manual ? 'badge-brand' : 'badge-mint'">
                                {{ p.type }}
                            </span>
                        </td>
                        <td class="text-sm">{{ p.days ?? '—' }}</td>
                        <td class="money text-sm">{{ p.amount }}</td>
                        <td class="text-xs text-slate-500">{{ p.starts ?? '—' }}</td>
                        <td class="text-xs text-slate-500">{{ p.ends ?? '—' }}</td>
                        <td><StatusBadge :status="p.status" /></td>
                        <td>
                            <div class="flex justify-end">
                                <button
                                    v-if="canManage && p.status === 'active'"
                                    type="button"
                                    class="btn-danger btn-sm"
                                    :disabled="endingId === p.id"
                                    @click="unfeature(p.id)"
                                >
                                    End
                                </button>
                                <span v-else class="text-xs text-slate-300">—</span>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="promotions.data.length === 0">
                        <td colspan="9" class="py-4 text-center text-slate-500">No promotions.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards -->
        <div class="flex flex-col gap-2 sm:hidden">
            <div v-for="p in promotions.data" :key="p.id" class="card-p">
                <div class="flex justify-between gap-2">
                    <p class="truncate font-semibold text-slate-900">{{ p.listing }}</p>
                    <StatusBadge :status="p.status" />
                </div>
                <div class="mt-1 flex justify-between text-xs text-slate-500">
                    <span>{{ p.seller }}</span>
                    <span class="money font-semibold text-slate-700">{{ p.amount }}</span>
                </div>
                <div class="mt-1 flex items-center justify-between text-xs text-slate-500">
                    <span :class="p.is_manual ? 'badge-brand' : 'badge-mint'">{{ p.type }}</span>
                    <span v-if="p.ends">ends {{ p.ends }}</span>
                </div>
                <div v-if="canManage && p.status === 'active'" class="mt-2">
                    <button
                        type="button"
                        class="btn-danger btn-sm"
                        :disabled="endingId === p.id"
                        @click="unfeature(p.id)"
                    >
                        End feature
                    </button>
                </div>
            </div>
            <p v-if="promotions.data.length === 0" class="card-p text-center text-sm text-slate-500">
                No promotions.
            </p>
        </div>

        <div class="mt-3">
            <Pagination :links="promotions.links" :total="promotions.total" />
        </div>
    </AdminLayout>
</template>
