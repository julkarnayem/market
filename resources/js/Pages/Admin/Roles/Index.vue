<script setup lang="ts">
import { computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

/** One role card — whitelisted by Admin\RoleController::index(). */
interface RoleRow {
    id: number;
    name: string;
    display_name: string;
    description: string | null;
    is_protected: boolean;
    users_count: number;
    permissions: string[];
    edit_url: string | null;
}

defineProps<{ roles: RoleRow[] }>();

// index authorizes roles.view; store/edit/update authorize roles.manage.
// Server authorize() re-checks — this only decides what the UI offers.
const canManage = computed(() => {
    const u = usePage().props.auth.user;
    return !!u && (u.roles.includes('admin') || u.permissions.includes('roles.manage'));
});

const form = useForm({ name: '', display_name: '', description: '' });

function submit(): void {
    // store() redirects to the new role's edit page to assign permissions.
    form.post(route('admin.roles.store'), { onSuccess: () => form.reset() });
}
</script>

<template>
    <AdminLayout title="Roles" heading="Role Management">
        <p class="section-sub mb-3">Manage staff roles and their permissions.</p>

        <div v-if="canManage" class="card-p mb-3">
            <h2 class="section-title mb-2">Create New Role</h2>
            <form class="flex flex-wrap items-start gap-3" @submit.prevent="submit">
                <div class="min-w-[10rem] flex-1">
                    <label class="label text-xs">Name (slug)</label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="input text-sm"
                        :class="form.errors.name && 'input-error'"
                        placeholder="e.g. content_manager"
                        maxlength="50"
                    />
                    <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
                    <p v-else class="mt-1 text-xs text-slate-400">Lowercase letters and underscores only.</p>
                </div>
                <div class="min-w-[10rem] flex-1">
                    <label class="label text-xs">Display Name</label>
                    <input
                        v-model="form.display_name"
                        type="text"
                        class="input text-sm"
                        :class="form.errors.display_name && 'input-error'"
                        placeholder="Content Manager"
                        maxlength="100"
                    />
                    <p v-if="form.errors.display_name" class="field-error">{{ form.errors.display_name }}</p>
                </div>
                <div class="min-w-[10rem] flex-1">
                    <label class="label text-xs">Description</label>
                    <input
                        v-model="form.description"
                        type="text"
                        class="input text-sm"
                        :class="form.errors.description && 'input-error'"
                        placeholder="Optional"
                        maxlength="500"
                    />
                    <p v-if="form.errors.description" class="field-error">{{ form.errors.description }}</p>
                </div>
                <button
                    type="submit"
                    class="btn-primary mt-[1.55rem]"
                    :disabled="form.processing || !form.name.trim() || !form.display_name.trim()"
                >
                    Create role
                </button>
            </form>
        </div>

        <div class="flex flex-col gap-2">
            <div v-for="role in roles" :key="role.id" class="card-p">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold text-slate-900">{{ role.display_name }}</h3>
                            <span v-if="role.is_protected" class="badge-slate text-xs">Protected</span>
                            <span class="text-xs text-slate-500">
                                {{ role.users_count }} member{{ role.users_count === 1 ? '' : 's' }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            Slug: <code class="money">{{ role.name }}</code>
                            <template v-if="role.description"> · {{ role.description }}</template>
                        </p>
                        <div class="mt-2 flex flex-wrap gap-1">
                            <span v-for="perm in role.permissions" :key="perm" class="badge-slate">{{ perm }}</span>
                            <span v-if="role.permissions.length === 0" class="text-xs text-slate-400">
                                No permissions assigned.
                            </span>
                        </div>
                    </div>
                    <Link
                        v-if="role.edit_url && canManage"
                        :href="role.edit_url"
                        class="btn-outline btn-sm shrink-0"
                    >
                        Edit permissions
                    </Link>
                    <!-- Protected roles have no edit page: edit()/update() abort 403. -->
                    <span v-else-if="role.is_protected" class="shrink-0 text-xs text-slate-400">
                        Not editable
                    </span>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
