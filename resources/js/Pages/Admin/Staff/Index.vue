<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import type { Paginated } from '@/types';

/** One staff row — whitelisted by Admin\StaffController::index(). */
interface StaffRow {
    id: number;
    name: string;
    email: string;
    initial: string;
    status: string;
    roles: string[];
    joined: string;
    url: string;
    is_self: boolean;
}

defineProps<{ staff: Paginated<StaffRow> }>();

// index/show authorize staff.view; create/store/role/suspend/restore authorize
// staff.manage. The server re-checks — this only decides what the UI offers.
const canManage = computed(() => {
    const u = usePage().props.auth.user;
    return !!u && (u.roles.includes('admin') || u.permissions.includes('staff.manage'));
});

const processing = ref(false);

function submit(action: 'suspend' | 'restore', id: number): void {
    processing.value = true;
    router.post(route(`admin.staff.${action}`, id), {}, {
        preserveScroll: true,
        onFinish: () => (processing.value = false),
    });
}
</script>

<template>
    <AdminLayout title="Staff Management" heading="Staff Management">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <p class="section-sub">Manage admin, moderator, support and finance staff accounts.</p>
            <Link v-if="canManage" :href="route('admin.staff.create')" class="btn-primary btn-sm">
                + Add Staff
            </Link>
        </div>

        <!-- Desktop table -->
        <div class="table-wrap hidden sm:block">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in staff.data" :key="s.id">
                        <td class="font-medium text-slate-900">{{ s.name }}</td>
                        <td class="text-sm text-slate-500">{{ s.email }}</td>
                        <td>
                            <span v-for="role in s.roles" :key="role" class="badge-brand mr-1 text-xs">
                                {{ role }}
                            </span>
                        </td>
                        <td><StatusBadge :status="s.status" /></td>
                        <td class="text-xs text-slate-500">{{ s.joined }}</td>
                        <td>
                            <div class="flex justify-end gap-1">
                                <Link :href="s.url" class="btn-ghost btn-sm">View</Link>
                                <!-- suspend() aborts 403 on your own account. -->
                                <template v-if="canManage && !s.is_self">
                                    <button
                                        v-if="s.status === 'active'"
                                        class="btn-danger btn-sm"
                                        :disabled="processing"
                                        @click="submit('suspend', s.id)"
                                    >
                                        Suspend
                                    </button>
                                    <button
                                        v-else
                                        class="btn-success btn-sm"
                                        :disabled="processing"
                                        @click="submit('restore', s.id)"
                                    >
                                        Restore
                                    </button>
                                </template>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="staff.data.length === 0">
                        <td colspan="6" class="py-4 text-center text-slate-500">No staff accounts found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards -->
        <div class="flex flex-col gap-2 sm:hidden">
            <Link v-for="s in staff.data" :key="s.id" :href="s.url" class="card-p block">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-brand-100 font-semibold text-brand-600"
                        >
                            {{ s.initial }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-slate-900">{{ s.name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ s.email }}</p>
                        </div>
                    </div>
                    <StatusBadge :status="s.status" />
                </div>
                <div class="mt-2 flex flex-wrap gap-1">
                    <span v-for="role in s.roles" :key="role" class="badge-brand text-xs">{{ role }}</span>
                </div>
            </Link>
            <p v-if="staff.data.length === 0" class="card-p text-center text-sm text-slate-500">
                No staff accounts found.
            </p>
        </div>

        <div class="mt-3">
            <Pagination :links="staff.links" :total="staff.total" />
        </div>
    </AdminLayout>
</template>
