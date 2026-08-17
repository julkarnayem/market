<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import type { Paginated } from '@/types';

/** One listing row — whitelisted by Admin\ListingController::index(). */
interface ListingRow {
    id: number;
    title: string;
    seller: string;
    price: string;
    status: string;
    created: string;
    url: string;
}
interface Tab {
    value: string;
    label: string;
}

const props = defineProps<{
    listings: Paginated<ListingRow>;
    filters: { tab: string };
    tabs: Tab[];
}>();

// Quick-approve mirrors the server @can('listings.approve') gate on the row action.
const canApprove = computed(() => {
    const u = usePage().props.auth.user;
    return !!u && (u.roles.includes('admin') || u.permissions.includes('listings.approve'));
});

// One in-flight row at a time; disables that row's button while the POST runs.
const approvingId = ref<number | null>(null);
function approve(id: number): void {
    approvingId.value = id;
    router.post(
        route('admin.listings.approve', id),
        {},
        { preserveScroll: true, onFinish: () => (approvingId.value = null) },
    );
}
</script>

<template>
    <AdminLayout title="Listings" heading="Listings">
        <!-- Status tabs (Inertia links carrying ?tab=) -->
        <div class="mb-3 flex flex-wrap gap-1 border-b border-slate-200">
            <Link
                v-for="t in tabs"
                :key="t.value"
                :href="route('admin.listings', { tab: t.value })"
                class="tab"
                :class="filters.tab === t.value && 'tab-active'"
            >
                {{ t.label }}
            </Link>
        </div>

        <!-- Desktop table -->
        <div class="table-wrap hidden sm:block">
            <table class="table">
                <thead>
                    <tr>
                        <th>Asset</th>
                        <th>Seller</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="l in listings.data" :key="l.id">
                        <td class="max-w-xs truncate font-medium">{{ l.title }}</td>
                        <td class="text-sm">{{ l.seller }}</td>
                        <td class="money text-sm font-semibold">{{ l.price }}</td>
                        <td><StatusBadge :status="l.status" /></td>
                        <td class="text-xs text-slate-500">{{ l.created }}</td>
                        <td>
                            <div class="flex justify-end gap-1">
                                <button
                                    v-if="canApprove && filters.tab === 'pending_review'"
                                    type="button"
                                    class="btn-success btn-sm"
                                    :disabled="approvingId === l.id"
                                    @click="approve(l.id)"
                                >
                                    Approve
                                </button>
                                <Link :href="l.url" class="btn-ghost btn-sm">Review</Link>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="listings.data.length === 0">
                        <td colspan="6" class="py-4 text-center text-slate-500">No listings in this tab.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards -->
        <div class="flex flex-col gap-2 sm:hidden">
            <div v-for="l in listings.data" :key="l.id" class="card-p">
                <div class="flex justify-between gap-2">
                    <p class="font-medium text-slate-900">{{ l.title }}</p>
                    <StatusBadge :status="l.status" />
                </div>
                <div class="mt-1 flex justify-between text-xs text-slate-500">
                    <span>{{ l.seller }} · {{ l.created }}</span>
                    <span class="money font-semibold text-slate-700">{{ l.price }}</span>
                </div>
                <div class="mt-2 flex gap-1">
                    <button
                        v-if="canApprove && filters.tab === 'pending_review'"
                        type="button"
                        class="btn-success btn-sm"
                        :disabled="approvingId === l.id"
                        @click="approve(l.id)"
                    >
                        Approve
                    </button>
                    <Link :href="l.url" class="btn-ghost btn-sm">Review</Link>
                </div>
            </div>
            <p v-if="listings.data.length === 0" class="card-p text-center text-sm text-slate-500">
                No listings in this tab.
            </p>
        </div>

        <div class="mt-3">
            <Pagination :links="listings.links" :total="listings.total" />
        </div>
    </AdminLayout>
</template>
