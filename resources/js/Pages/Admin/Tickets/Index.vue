<script setup lang="ts">
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import type { Paginated } from '@/types';

/** One ticket row — whitelisted by Admin\TicketController::index(). */
interface TicketRow {
    id: number;
    reference: string;
    user_name: string;
    subject: string;
    priority_label: string;
    priority_color: string;
    status: string;
    assignee: string | null;
    last_reply: string;
    url: string;
}
interface Option {
    value: string;
    label: string;
}

const props = defineProps<{
    tickets: Paginated<TicketRow>;
    filters: { status: string; q: string | null; priority: string | null };
    tabs: Option[];
    priorities: Option[];
}>();

// This page carries BOTH idioms: status is a tab (an Inertia <Link>), while the
// free-text search and the priority select are a reactive mirror of the echoed
// filters. Each has to preserve the other's value, so the tab links re-send q
// and priority, and applyFilters() re-sends the active tab.
const filterState = reactive({
    q: props.filters.q ?? '',
    priority: props.filters.priority ?? '',
});

function tabHref(status: string): string {
    return route('admin.tickets', {
        status,
        q: filterState.q || undefined,
        priority: filterState.priority || undefined,
    });
}

function applyFilters(): void {
    router.get(
        route('admin.tickets'),
        {
            status: props.filters.status,
            q: filterState.q || undefined,
            priority: filterState.priority || undefined,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}
</script>

<template>
    <AdminLayout title="Support Tickets" heading="Support Tickets">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
            <!-- Status tabs (Inertia links carrying ?status=) -->
            <div class="flex flex-wrap gap-1 border-b border-slate-200">
                <Link
                    v-for="t in tabs"
                    :key="t.value"
                    :href="tabHref(t.value)"
                    class="tab"
                    :class="filters.status === t.value && 'tab-active'"
                >
                    {{ t.label }}
                </Link>
            </div>

            <form class="flex flex-wrap gap-2" @submit.prevent="applyFilters">
                <input
                    v-model="filterState.q"
                    type="search"
                    class="input max-w-xs"
                    placeholder="Search subject or reference…"
                />
                <select v-model="filterState.priority" class="select w-auto" @change="applyFilters">
                    <option value="">All priorities</option>
                    <option v-for="p in priorities" :key="p.value" :value="p.value">{{ p.label }}</option>
                </select>
                <button type="submit" class="btn-outline">Filter</button>
            </form>
        </div>

        <!-- Desktop table -->
        <div class="table-wrap hidden sm:block">
            <table class="table">
                <thead>
                    <tr>
                        <th>Ref</th>
                        <th>User</th>
                        <th>Subject</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Assigned</th>
                        <th>Last reply</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="t in tickets.data" :key="t.id">
                        <td class="money text-xs text-slate-500">{{ t.reference }}</td>
                        <td class="text-sm">{{ t.user_name }}</td>
                        <td class="max-w-[200px] truncate font-medium">{{ t.subject }}</td>
                        <td>
                            <span class="text-xs" :class="`badge-${t.priority_color}`">{{ t.priority_label }}</span>
                        </td>
                        <td><StatusBadge :status="t.status" /></td>
                        <td class="text-sm text-slate-500">{{ t.assignee ?? '—' }}</td>
                        <td class="text-xs text-slate-500">{{ t.last_reply }}</td>
                        <td class="text-right">
                            <Link :href="t.url" class="btn-ghost btn-sm">View</Link>
                        </td>
                    </tr>
                    <tr v-if="tickets.data.length === 0">
                        <td colspan="8" class="py-4 text-center text-slate-500">
                            No support tickets match your filters.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards -->
        <div class="flex flex-col gap-2 sm:hidden">
            <Link v-for="t in tickets.data" :key="t.id" :href="t.url" class="card-p block">
                <div class="flex justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-slate-900">{{ t.subject }}</p>
                        <p class="text-xs text-slate-500">{{ t.user_name }} · {{ t.reference }}</p>
                    </div>
                    <StatusBadge :status="t.status" />
                </div>
                <div class="mt-1 flex justify-between text-xs text-slate-500">
                    <span :class="`badge-${t.priority_color}`">{{ t.priority_label }}</span>
                    <span>{{ t.last_reply }}</span>
                </div>
            </Link>
            <p v-if="tickets.data.length === 0" class="card-p text-center text-sm text-slate-500">
                No support tickets match your filters.
            </p>
        </div>

        <div class="mt-3">
            <Pagination :links="tickets.links" :total="tickets.total" />
        </div>
    </AdminLayout>
</template>
