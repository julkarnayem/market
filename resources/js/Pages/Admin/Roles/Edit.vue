<script setup lang="ts">
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';

interface PermissionOption {
    id: number;
    name: string;
}
interface PermissionGroup {
    group: string;
    label: string;
    permissions: PermissionOption[];
}

const props = defineProps<{
    role: {
        id: number;
        name: string;
        display_name: string;
        description: string | null;
        users_count: number;
    };
    groups: PermissionGroup[];
    /** Currently granted permission ids. */
    assigned: number[];
}>();

// The whole page authorizes roles.manage, so there is nothing to gate
// client-side — loading it means you may save it.
const form = useForm<{ display_name: string; description: string; permissions: number[] }>({
    display_name: props.role.display_name,
    description: props.role.description ?? '',
    // Copied, not aliased: v-model mutates this array in place.
    permissions: [...props.assigned],
});

/** "3 / 8" per group — the lists are long enough that a count helps. */
function groupCount(group: PermissionGroup): number {
    return group.permissions.filter((p) => form.permissions.includes(p.id)).length;
}

const total = computed(() => form.permissions.length);

function submit(): void {
    form.patch(route('admin.roles.update', props.role.id));
}
</script>

<template>
    <AdminLayout :title="`Edit ${role.display_name}`" heading="Edit Role Permissions">
        <Breadcrumb
            :items="[{ label: 'Roles', url: route('admin.roles') }, { label: role.display_name }]"
        />

        <div class="max-w-3xl">
            <div class="card-p">
                <h2 class="section-title mb-1">{{ role.display_name }}</h2>
                <p class="section-sub mb-3">
                    Toggle permissions for this role. Changes are audited.
                    <span class="text-slate-400">
                        Slug <code class="money">{{ role.name }}</code> ·
                        {{ role.users_count }} member{{ role.users_count === 1 ? '' : 's' }} affected
                    </span>
                </p>

                <form class="flex flex-col gap-4" @submit.prevent="submit">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="label">Display Name <span class="text-rose-500">*</span></label>
                            <input
                                v-model="form.display_name"
                                type="text"
                                class="input"
                                :class="form.errors.display_name && 'input-error'"
                                maxlength="100"
                            />
                            <p v-if="form.errors.display_name" class="field-error">
                                {{ form.errors.display_name }}
                            </p>
                        </div>
                        <div>
                            <label class="label">Description</label>
                            <input
                                v-model="form.description"
                                type="text"
                                class="input"
                                :class="form.errors.description && 'input-error'"
                                maxlength="500"
                                placeholder="Optional"
                            />
                            <p v-if="form.errors.description" class="field-error">
                                {{ form.errors.description }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-slate-100 pt-3">
                        <p class="text-sm text-slate-500">
                            <span class="font-semibold text-slate-900">{{ total }}</span> permission{{
                                total === 1 ? '' : 's'
                            }}
                            selected
                        </p>
                        <p v-if="form.errors.permissions" class="field-error">{{ form.errors.permissions }}</p>

                        <div v-for="group in groups" :key="group.group">
                            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">
                                {{ group.label }}
                                <span class="font-normal text-slate-400">
                                    {{ groupCount(group) }} / {{ group.permissions.length }}
                                </span>
                            </p>
                            <div class="grid gap-1 sm:grid-cols-2 lg:grid-cols-3">
                                <label
                                    v-for="perm in group.permissions"
                                    :key="perm.id"
                                    class="flex items-center gap-2 rounded-lg p-2 text-sm hover:bg-slate-50"
                                >
                                    <input
                                        v-model="form.permissions"
                                        type="checkbox"
                                        class="checkbox"
                                        :value="perm.id"
                                    />
                                    <span class="text-slate-800">{{ perm.name }}</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-3 border-t border-slate-100 pt-3">
                        <button type="submit" class="btn-primary" :disabled="form.processing">
                            Save permissions
                        </button>
                        <Link :href="route('admin.roles')" class="btn-outline">Cancel</Link>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
