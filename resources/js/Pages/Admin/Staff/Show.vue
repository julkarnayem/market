<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

interface RoleOption {
    id: number;
    name: string;
    display_name: string;
    /** False for super_admin unless you are one — assignRole() aborts 403. */
    assignable: boolean;
}

const props = defineProps<{
    user: {
        id: number;
        name: string;
        email: string;
        status: string;
        joined: string;
        suspended_at: string | null;
        is_self: boolean;
    };
    role_permissions: { id: number; display_name: string; permissions: string[] }[];
    roles: RoleOption[];
    current_role_id: number | null;
    logs: { id: number; action: string; at: string | null }[];
}>();

// The page authorizes staff.view; role/suspend/restore authorize staff.manage.
// Server authorize() re-checks — this only decides which controls to show.
const canManage = computed(() => {
    const u = usePage().props.auth.user;
    return !!u && (u.roles.includes('admin') || u.permissions.includes('staff.manage'));
});

const profileRows = computed(() => {
    const rows = [
        { label: 'Name', value: props.user.name },
        { label: 'Email', value: props.user.email },
        { label: 'Joined', value: props.user.joined },
    ];
    if (props.user.suspended_at) {
        rows.push({ label: 'Suspended', value: props.user.suspended_at });
    }
    return rows;
});

const roleId = ref(String(props.current_role_id ?? ''));
const processing = ref(false);

function post(action: 'role' | 'suspend' | 'restore', data: Record<string, string> = {}): void {
    processing.value = true;
    router.post(route(`admin.staff.${action}`, props.user.id), data, {
        preserveScroll: true,
        onFinish: () => (processing.value = false),
    });
}
</script>

<template>
    <AdminLayout :title="user.name" heading="Staff Detail">
        <Breadcrumb :items="[{ label: 'Staff', url: route('admin.staff') }, { label: user.name }]" />

        <div class="grid gap-4 lg:grid-cols-[1fr_20rem]">
            <!-- Main column -->
            <div class="flex flex-col gap-3">
                <div class="card-p">
                    <h2 class="section-title mb-3">Profile</h2>
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div v-for="r in profileRows" :key="r.label" class="rounded-lg bg-slate-50 p-2">
                            <dt class="mb-1 text-xs text-slate-500">{{ r.label }}</dt>
                            <dd class="font-medium text-slate-900">{{ r.value }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-2">
                            <dt class="mb-1 text-xs text-slate-500">Status</dt>
                            <dd><StatusBadge :status="user.status" /></dd>
                        </div>
                    </dl>
                </div>

                <div class="card-p">
                    <h2 class="section-title mb-2">Permissions (via role)</h2>
                    <div v-for="role in role_permissions" :key="role.id" class="mb-3 last:mb-0">
                        <p class="mb-2 text-sm font-semibold text-slate-900">{{ role.display_name }}</p>
                        <div class="flex flex-wrap gap-1">
                            <span v-for="perm in role.permissions" :key="perm" class="badge-slate">{{ perm }}</span>
                            <span v-if="role.permissions.length === 0" class="text-xs text-slate-400">
                                No permissions assigned.
                            </span>
                        </div>
                    </div>
                    <p v-if="role_permissions.length === 0" class="text-sm text-slate-500">No roles attached.</p>
                </div>

                <div class="card-p">
                    <h2 class="section-title mb-2">Recent Activity</h2>
                    <p v-if="logs.length === 0" class="text-sm text-slate-500">No activity logged.</p>
                    <div v-else class="flex flex-col gap-2">
                        <div v-for="log in logs" :key="log.id" class="flex justify-between gap-2 text-sm">
                            <code class="rounded bg-slate-50 px-2 py-1 text-xs text-slate-700">{{ log.action }}</code>
                            <span class="text-xs text-slate-500">{{ log.at }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="flex flex-col gap-3">
                <div class="card-p">
                    <h2 class="section-title mb-2">Change Role</h2>
                    <template v-if="canManage && !user.is_self">
                        <div class="flex flex-col gap-2">
                            <select v-model="roleId" class="select text-sm">
                                <option
                                    v-for="role in roles"
                                    :key="role.id"
                                    :value="String(role.id)"
                                    :disabled="!role.assignable"
                                >
                                    {{ role.display_name }}{{ role.assignable ? '' : ' — Super Admin only' }}
                                </option>
                            </select>
                            <button
                                class="btn-outline btn-sm w-full"
                                :disabled="processing || !roleId || roleId === String(current_role_id)"
                                @click="post('role', { role_id: roleId })"
                            >
                                Update role
                            </button>
                        </div>

                        <div class="mt-3 border-t border-slate-100 pt-3">
                            <button
                                v-if="user.status === 'active'"
                                class="btn-danger btn-sm w-full"
                                :disabled="processing"
                                @click="post('suspend')"
                            >
                                Suspend access
                            </button>
                            <button
                                v-else
                                class="btn-success btn-sm w-full"
                                :disabled="processing"
                                @click="post('restore')"
                            >
                                Restore access
                            </button>
                        </div>
                    </template>
                    <!-- suspend() aborts 403 on your own account, and demoting
                         yourself out of staff is not offered here either. -->
                    <p v-else-if="user.is_self" class="text-xs text-slate-500">
                        You cannot modify your own account here.
                    </p>
                    <p v-else class="text-xs text-slate-500">
                        You do not have permission to change staff accounts.
                    </p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
