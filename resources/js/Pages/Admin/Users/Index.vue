<script setup lang="ts">
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import type { Paginated } from '@/types';

/** One user row — whitelisted by Admin\UserController::index(). */
interface UserRow {
    id: number;
    name: string;
    email: string;
    initial: string;
    status: string;
    verification: string;
    joined: string;
    last_login: string;
    url: string;
}
interface Option {
    value: string;
    label: string;
}

const props = defineProps<{
    users: Paginated<UserRow>;
    filters: { q: string; status: string; verification: string };
    statuses: Option[];
    verifications: Option[];
}>();

// Local mirror of the server-echoed filters so the controls follow the URL.
const filterState = reactive({
    q: props.filters.q,
    status: props.filters.status,
    verification: props.filters.verification,
});

function applyFilters() {
    router.get(
        route('admin.users'),
        {
            q: filterState.q || undefined,
            status: filterState.status === 'all' ? undefined : filterState.status,
            verification: filterState.verification === 'all' ? undefined : filterState.verification,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}
</script>

<template>
    <AdminLayout title="Users" heading="Users">
        <!-- Filters -->
        <form class="mb-3 flex flex-wrap items-center gap-2" @submit.prevent="applyFilters">
            <input v-model="filterState.q" type="search" placeholder="Search users…" class="input max-w-xs" />
            <select v-model="filterState.status" class="select w-auto" @change="applyFilters">
                <option value="all">All status</option>
                <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
            <select v-model="filterState.verification" class="select w-auto" @change="applyFilters">
                <option value="all">Verification</option>
                <option v-for="v in verifications" :key="v.value" :value="v.value">{{ v.label }}</option>
            </select>
            <button type="submit" class="btn-outline">Filter</button>
        </form>

        <!-- Desktop table -->
        <div class="table-wrap hidden sm:block">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Status</th>
                        <th>Verification</th>
                        <th>Joined</th>
                        <th>Last login</th>
                        <th class="text-right"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="u in users.data" :key="u.id">
                        <td>
                            <div class="flex items-center gap-3">
                                <span
                                    class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-600"
                                >
                                    {{ u.initial }}
                                </span>
                                <div>
                                    <p class="font-medium text-slate-900">{{ u.name }}</p>
                                    <p class="text-xs text-slate-500">{{ u.email }}</p>
                                </div>
                            </div>
                        </td>
                        <td><StatusBadge :status="u.status" /></td>
                        <td><StatusBadge :status="u.verification" /></td>
                        <td class="text-xs text-slate-500">{{ u.joined }}</td>
                        <td class="text-xs text-slate-500">{{ u.last_login }}</td>
                        <td class="text-right">
                            <Link :href="u.url" class="btn-ghost btn-sm">View</Link>
                        </td>
                    </tr>
                    <tr v-if="users.data.length === 0">
                        <td colspan="6" class="py-4 text-center text-slate-500">No users found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards -->
        <div class="flex flex-col gap-2 sm:hidden">
            <Link v-for="u in users.data" :key="u.id" :href="u.url" class="card-p flex items-center gap-3">
                <span
                    class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-brand-100 font-semibold text-brand-600"
                >
                    {{ u.initial }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold text-slate-900">{{ u.name }}</p>
                    <p class="truncate text-xs text-slate-500">{{ u.email }}</p>
                </div>
                <StatusBadge :status="u.status" />
            </Link>
            <p v-if="users.data.length === 0" class="card-p text-center text-sm text-slate-500">No users found.</p>
        </div>

        <div class="mt-3">
            <Pagination :links="users.links" :total="users.total" />
        </div>
    </AdminLayout>
</template>
