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
            <!-- Desktop table -->
            <div class="table-wrap hidden sm:block">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Ref</th>
                            <th>Order</th>
                            <th>Asset</th>
                            <th>Your role</th>
                            <th>Counterparty</th>
                            <th>Reason</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Activity</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="d in disputes.data" :key="d.id">
                            <td class="font-mono text-xs font-semibold">{{ d.reference }}</td>
                            <td class="font-mono text-xs text-slate-500">{{ d.order_number }}</td>
                            <td class="max-w-[160px] truncate font-medium">{{ d.asset_title }}</td>
                            <td class="text-sm capitalize">{{ d.role ?? '—' }}</td>
                            <td class="text-sm">{{ d.counterparty }}</td>
                            <td class="text-sm text-slate-500">{{ d.reason }}</td>
                            <td class="money font-semibold">{{ d.total }}</td>
                            <td><StatusBadge :status="d.status" /></td>
                            <td class="text-xs text-slate-500">{{ d.activity }}</td>
                            <td><Link :href="d.url" class="btn-ghost btn-sm">Open</Link></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <div class="flex flex-col gap-2 sm:hidden">
                <Link v-for="d in disputes.data" :key="d.id" :href="d.url" class="card-p block">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="truncate font-semibold text-slate-900">{{ d.asset_title }}</p>
                            <p class="mt-1 font-mono text-xs text-slate-500">
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
