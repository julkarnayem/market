<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import type { Paginated } from '@/types';

/** One dispute row — whitelisted by Dashboard\DisputeController::index(). */
interface DisputeRow {
    id: number;
    reference: string;
    order_number: string;
    asset_title: string;
    reason: string;
    status: string;
    status_label: string;
    is_active: boolean;
    /** buyer | seller — this user's side of it. */
    role: string | null;
    counterparty: string;
    total: string;
    activity: string;
    url: string;
}

defineProps<{ disputes: Paginated<DisputeRow> }>();
</script>

<template>
    <DashboardLayout title="Disputes" heading="Disputes">
        <div v-if="disputes.data.length === 0" class="card-p text-center">
            <p class="mb-2 text-4xl">🕊️</p>
            <h2 class="font-display text-lg font-bold text-slate-900">No disputes</h2>
            <p class="mt-1 text-sm text-slate-500">
                Nothing to settle — none of your orders is in dispute.
            </p>
            <Link :href="route('dashboard.orders')" class="btn-outline mt-3">Back to orders</Link>
        </div>

        <template v-else>
            <!-- Desktop table. Reference/order and role/counterparty are stacked
                 into one column each: ten columns overflowed .table-wrap, which is
                 overflow-hidden, so the action column was clipped off the end with
                 no scrollbar to reach it.
                 Eight columns still want ~860px and the sidebar leaves less than
                 that until ~1150px, so rather than pick a breakpoint that is wrong
                 on some screen, the wrap scrolls and the action column is pinned to
                 its right edge. View is then reachable at every width, and a long
                 asset title can never push it out of sight again. -->
            <div class="table-wrap hidden overflow-x-auto lg:block">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Asset</th>
                            <th>Your role</th>
                            <th>Reason</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Activity</th>
                            <th class="sticky right-0 border-l border-slate-200 bg-slate-50 text-right">
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="d in disputes.data" :key="d.id" class="group">
                            <!-- nowrap: at the narrow end of lg the reference broke
                                 mid-handle ("D-" / "4HICKZ6OHAV"). The wrap scrolls
                                 now, so the extra width costs nothing. -->
                            <td class="whitespace-nowrap font-mono text-xs">
                                <span class="font-semibold text-slate-900">{{ d.reference }}</span>
                                <span class="mt-0.5 block text-slate-500">{{ d.order_number }}</span>
                            </td>
                            <td class="max-w-[160px] truncate font-medium">{{ d.asset_title }}</td>
                            <td class="text-sm">
                                <span class="capitalize">{{ d.role ?? '—' }}</span>
                                <span class="mt-0.5 block text-xs text-slate-500">vs {{ d.counterparty }}</span>
                            </td>
                            <td class="text-sm text-slate-500">{{ d.reason }}</td>
                            <td class="money font-semibold">{{ d.total }}</td>
                            <td><StatusBadge :status="d.status" /></td>
                            <td class="text-xs text-slate-500">{{ d.activity }}</td>
                            <td
                                class="sticky right-0 whitespace-nowrap border-l border-slate-100 bg-white
                                       text-right transition-colors group-hover:bg-slate-50"
                            >
                                <Link
                                    :href="d.url"
                                    class="btn-outline btn-sm"
                                    :aria-label="`View dispute ${d.reference}`"
                                >
                                    View
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Cards — everything below lg, where eight columns will not fit. The
                 whole card is the link, so the action is the card itself. -->
            <div class="flex flex-col gap-2 lg:hidden">
                <Link v-for="d in disputes.data" :key="d.id" :href="d.url" class="card-p block">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-slate-900">{{ d.asset_title }}</p>
                            <p class="mt-1 truncate font-mono text-xs text-slate-500">
                                {{ d.reference }} · {{ d.order_number }}
                            </p>
                        </div>
                        <StatusBadge :status="d.status" />
                    </div>
                    <p class="mt-2 text-sm text-slate-500">
                        <span class="capitalize">{{ d.role ?? '—' }}</span> · vs {{ d.counterparty }}
                    </p>
                    <div class="mt-1 flex justify-between text-xs text-slate-500">
                        <span>{{ d.activity }}</span>
                        <span class="money font-bold text-slate-900">{{ d.total }}</span>
                    </div>
                </Link>
            </div>

            <div class="mt-3">
                <Pagination :links="disputes.links" :total="disputes.total" />
            </div>
        </template>
    </DashboardLayout>
</template>
