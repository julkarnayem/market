<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';

interface RoleOption {
    id: number;
    name: string;
    display_name: string;
    /** False for super_admin unless you are one — store() aborts 403. */
    assignable: boolean;
}

const props = defineProps<{ roles: RoleOption[] }>();

const form = useForm<{
    name: string;
    email: string;
    role_id: string;
    password: string;
    password_confirmation: string;
}>({
    name: '',
    email: '',
    // The select has no empty option, so preselect the first role we are
    // actually allowed to assign.
    role_id: String(props.roles.find((r) => r.assignable)?.id ?? ''),
    password: '',
    password_confirmation: '',
});

function submit(): void {
    form.post(route('admin.staff.store'), {
        onError: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <AdminLayout title="Add Staff" heading="Add Staff Account">
        <Breadcrumb :items="[{ label: 'Staff', url: route('admin.staff') }, { label: 'Add Staff' }]" />

        <div class="max-w-lg">
            <div class="card-p">
                <form class="flex flex-col gap-3" @submit.prevent="submit">
                    <div>
                        <label class="label">Full Name <span class="text-rose-500">*</span></label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="input"
                            :class="form.errors.name && 'input-error'"
                            maxlength="100"
                            autofocus
                        />
                        <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="label">Email Address <span class="text-rose-500">*</span></label>
                        <input
                            v-model="form.email"
                            type="email"
                            class="input"
                            :class="form.errors.email && 'input-error'"
                        />
                        <p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label class="label">Role <span class="text-rose-500">*</span></label>
                        <select
                            v-model="form.role_id"
                            class="select"
                            :class="form.errors.role_id && 'input-error'"
                        >
                            <option
                                v-for="role in roles"
                                :key="role.id"
                                :value="String(role.id)"
                                :disabled="!role.assignable"
                            >
                                {{ role.display_name }}{{ role.assignable ? '' : ' — Super Admin only' }}
                            </option>
                        </select>
                        <p v-if="form.errors.role_id" class="field-error">{{ form.errors.role_id }}</p>
                    </div>

                    <div>
                        <label class="label">Password <span class="text-rose-500">*</span></label>
                        <input
                            v-model="form.password"
                            type="password"
                            class="input"
                            :class="form.errors.password && 'input-error'"
                            autocomplete="new-password"
                        />
                        <p v-if="form.errors.password" class="field-error">{{ form.errors.password }}</p>
                        <p v-else class="mt-1 text-xs text-slate-400">Minimum 10 characters.</p>
                    </div>

                    <div>
                        <label class="label">Confirm Password <span class="text-rose-500">*</span></label>
                        <input
                            v-model="form.password_confirmation"
                            type="password"
                            class="input"
                            autocomplete="new-password"
                        />
                    </div>

                    <div class="flex gap-3">
                        <button
                            type="submit"
                            class="btn-primary"
                            :disabled="form.processing || !form.name.trim() || !form.email.trim() || !form.role_id"
                        >
                            Create staff account
                        </button>
                        <Link :href="route('admin.staff')" class="btn-outline">Cancel</Link>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
