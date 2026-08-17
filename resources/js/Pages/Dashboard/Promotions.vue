<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import type { Paginated } from '@/types';

/** One promotion row — whitelisted by PromotionController::index(). */
interface PromotionRow {
    id: number;
    /** asset->title, nullable (the listing may since have been removed). */
    asset_title: string | null;
    /** Admin-featured (free) vs paid — drives the type label + badge tone. */
    is_manual: boolean;
    /** Duration in days; 0 for admin features → rendered as "—". */
    days: number;
    /** Desktop amount: "৳0 (free)" for admin features, else Money::format. */
    amount_display: string;
    /** Mobile amount: plain Money::format(price). */
    amount_formatted: string;
    /** starts_at / ends_at, "d M Y, H:i" — desktop table (nullable). */
    starts_full: string | null;
    ends_full: string | null;
    /** starts_at "d M" / ends_at "d M Y" — mobile range (nullable). */
    starts_short: string | null;
    ends_short: string | null;
    status: string;
}

defineProps<{
    promotions: Paginated<PromotionRow>;
}>();
</script>

<template>
    <DashboardLayout title="Promotions" heading="My Promotions">
        <div class="mb-3 flex items-center justify-between gap-3">
            <p class="section-sub">Purchase paid feature slots for your published listings.</p>
            <Link :href="route('dashboard.listings')" class="btn-outline btn-sm">← My listings</Link>
        </div>

        <div v-if="promotions.data.length === 0" class="card-p text-center">
            <p class="mb-2 text-4xl">⭐</p>
            <h2 class="font-display text-lg font-bold text-slate-900">No promotions yet</h2>
            <p class="mt-1 text-sm text-slate-500">
                Promote your listings to appear at the top of the marketplace.
            </p>
            <Link :href="route('dashboard.listings')" class="btn-outline mt-3">Browse my listings</Link>
        </div>

        <template v-else>
            <!-- Desktop table -->
            <div class="table-wrap hidden sm:block">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Listing</th>
                            <th>Type</th>
                            <th>Days</th>
                            <th>Amount</th>
                            <th>Start</th>
                            <th>End</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in promotions.data" :key="p.id">
                            <td class="max-w-[160px] truncate font-medium">{{ p.asset_title ?? '—' }}</td>
                            <td>
                                <span class="text-xs" :class="p.is_manual ? 'badge-brand' : 'badge-mint'">
                                    {{ p.is_manual ? 'Admin featured' : 'Paid' }}
                                </span>
                            </td>
                            <td>{{ p.days || '—' }}</td>
                            <td class="money">{{ p.amount_display }}</td>
                            <td class="text-xs text-slate-500">{{ p.starts_full }}</td>
                            <td class="text-xs text-slate-500">{{ p.ends_full }}</td>
                            <td><StatusBadge :status="p.status" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <div class="flex flex-col gap-2 sm:hidden">
                <div v-for="p in promotions.data" :key="p.id" class="card-p">
                    <div class="flex items-start justify-between gap-2">
                        <p class="truncate font-semibold text-slate-900">{{ p.asset_title ?? '—' }}</p>
                        <StatusBadge :status="p.status" />
                    </div>
                    <div class="mt-2 flex justify-between text-sm text-slate-500">
                        <span>{{ p.starts_short }} – {{ p.ends_short }}</span>
                        <span class="money">{{ p.amount_formatted }}</span>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <Pagination :links="promotions.links" :total="promotions.total" />
            </div>
        </template>
    </DashboardLayout>
</template>
