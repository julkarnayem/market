<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';

interface Parent {
    id: number;
    name: string;
}

defineProps<{ parents: Parent[] }>();

const form = useForm<{
    name: string;
    parent_id: string;
    icon: string;
    description: string;
    position: number;
    is_active: boolean;
    is_prohibited: boolean;
    is_restricted: boolean;
}>({
    name: '',
    parent_id: '',
    icon: '',
    description: '',
    position: 0,
    is_active: true,
    is_prohibited: false,
    is_restricted: false,
});

function submit(): void {
    form.post(route('admin.categories.store'));
}
</script>

<template>
    <AdminLayout title="New Category" heading="Create Category">
        <Breadcrumb
            :items="[{ label: 'Categories', url: route('admin.categories') }, { label: 'New' }]"
        />

        <div class="max-w-xl">
            <div class="card-p">
                <form class="flex flex-col gap-3" @submit.prevent="submit">
                    <div>
                        <label class="label">Name <span class="text-rose-500">*</span></label>
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
                        <label class="label">Parent Category (leave empty for top-level)</label>
                        <select
                            v-model="form.parent_id"
                            class="select"
                            :class="form.errors.parent_id && 'input-error'"
                        >
                            <option value="">— Top level category —</option>
                            <option v-for="p in parents" :key="p.id" :value="String(p.id)">{{ p.name }}</option>
                        </select>
                        <p v-if="form.errors.parent_id" class="field-error">{{ form.errors.parent_id }}</p>
                    </div>

                    <div>
                        <label class="label">Icon (emoji or icon code)</label>
                        <input
                            v-model="form.icon"
                            type="text"
                            class="input"
                            :class="form.errors.icon && 'input-error'"
                            maxlength="50"
                            placeholder="📱"
                        />
                        <p v-if="form.errors.icon" class="field-error">{{ form.errors.icon }}</p>
                    </div>

                    <div>
                        <label class="label">Description</label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            class="textarea"
                            :class="form.errors.description && 'input-error'"
                            maxlength="500"
                            placeholder="Optional description…"
                        ></textarea>
                        <p v-if="form.errors.description" class="field-error">{{ form.errors.description }}</p>
                    </div>

                    <div>
                        <label class="label">Sort order</label>
                        <input
                            v-model.number="form.position"
                            type="number"
                            class="input"
                            :class="form.errors.position && 'input-error'"
                            min="0"
                        />
                        <p v-if="form.errors.position" class="field-error">{{ form.errors.position }}</p>
                    </div>

                    <div class="flex flex-wrap gap-4">
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="form.is_active" type="checkbox" class="checkbox" /> Active
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="form.is_prohibited" type="checkbox" class="checkbox" /> Prohibited
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="form.is_restricted" type="checkbox" class="checkbox" /> Restricted
                        </label>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" class="btn-primary" :disabled="form.processing">Create category</button>
                        <Link :href="route('admin.categories')" class="btn-outline">Cancel</Link>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
