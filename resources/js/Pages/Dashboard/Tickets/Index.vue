<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import type { Paginated } from '@/types';

interface TicketRow {
    id: number;
    subject: string;
    status: string;
    priority_label: string;
    priority_color: string;
    updated_human: string;
}

defineProps<{
    tickets: Paginated<TicketRow>;
}>();
</script>

<template>
    <DashboardLayout title="Support" heading="Support Tickets">
        <template #actions>
            <Link :href="route('dashboard.tickets.create')" class="btn-primary">+ New Ticket</Link>
        </template>

        <div v-if="tickets.data.length === 0" class="card-p text-center">
            <p class="mb-2 text-4xl">🎧</p>
            <h2 class="font-display text-lg font-bold text-slate-900">No tickets yet</h2>
            <p class="mt-1 text-sm text-slate-500">Open a ticket and our support team will help you out.</p>
            <Link :href="route('dashboard.tickets.create')" class="btn-primary mt-3">Create a ticket</Link>
        </div>

        <template v-else>
            <!-- Desktop table -->
            <div class="table-wrap hidden sm:block">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Subject</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Last updated</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in tickets.data" :key="t.id">
                            <td class="text-slate-500">#{{ t.id }}</td>
                            <td class="max-w-xs truncate font-medium text-slate-900">{{ t.subject }}</td>
                            <td>
                                <span :class="`badge-${t.priority_color}`" class="text-xs">{{ t.priority_label }}</span>
                            </td>
                            <td><StatusBadge :status="t.status" /></td>
                            <td class="text-slate-500">{{ t.updated_human }}</td>
                            <td class="text-right">
                                <Link :href="route('dashboard.tickets.show', t.id)" class="btn-ghost btn-sm">View</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <div class="flex flex-col gap-2 sm:hidden">
                <Link
                    v-for="t in tickets.data"
                    :key="t.id"
                    :href="route('dashboard.tickets.show', t.id)"
                    class="card-p block"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-slate-900">{{ t.subject }}</p>
                            <p class="mt-1 text-xs text-slate-500">#{{ t.id }} · {{ t.updated_human }}</p>
                        </div>
                        <StatusBadge :status="t.status" />
                    </div>
                    <div class="mt-2">
                        <span :class="`badge-${t.priority_color}`" class="text-xs">{{ t.priority_label }}</span>
                    </div>
                </Link>
            </div>

            <div class="mt-3">
                <Pagination :links="tickets.links" :total="tickets.total" />
            </div>
        </template>
    </DashboardLayout>
</template>
