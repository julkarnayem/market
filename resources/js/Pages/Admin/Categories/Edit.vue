<script setup lang="ts">
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';

interface CategoryData {
    id: number;
    name: string;
    parent_id: number | null;
    icon: string | null;
    description: string | null;
    position: number;
    is_active: boolean;
    is_prohibited: boolean;
    is_restricted: boolean;
}
interface Parent {
    id: number;
    name: string;
}
interface AttributeRow {
    id: number;
    label: string;
    key: string;
    type: string;
    is_required: boolean;
    is_active: boolean;
}

const props = defineProps<{
    category: CategoryData;
    parents: Parent[];
    attributes: AttributeRow[];
    attributeTypes: string[];
}>();

// ── Category details (PATCH) ──────────────────────────────────────
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
    name: props.category.name,
    parent_id: props.category.parent_id ? String(props.category.parent_id) : '',
    icon: props.category.icon ?? '',
    description: props.category.description ?? '',
    position: props.category.position,
    is_active: props.category.is_active,
    is_prohibited: props.category.is_prohibited,
    is_restricted: props.category.is_restricted,
});

function save(): void {
    form.patch(route('admin.categories.update', props.category.id));
}

// ── Add attribute (POST) ──────────────────────────────────────────
const attrForm = useForm<{
    label: string;
    key: string;
    type: string;
    unit: string;
    position: number;
    options: string;
    is_required: boolean;
    is_filterable: boolean;
}>({
    label: '',
    key: '',
    type: props.attributeTypes[0] ?? 'text',
    unit: '',
    position: 0,
    options: '',
    is_required: false,
    is_filterable: false,
});

function addAttribute(): void {
    attrForm.post(route('admin.categories.attributes.store', props.category.id), {
        preserveScroll: true,
        onSuccess: () => attrForm.reset(),
    });
}

// ── Per-attribute enable/disable toggle (PATCH) ───────────────────
// updateAttribute requires `label`; send the current one and flip is_active.
// is_required/is_filterable are omitted, so the server leaves them untouched.
const togglingId = ref<number | null>(null);
function toggleAttribute(attr: AttributeRow): void {
    togglingId.value = attr.id;
    router.patch(
        route('admin.categories.attributes.update', [props.category.id, attr.id]),
        { label: attr.label, is_active: !attr.is_active },
        { preserveScroll: true, onFinish: () => (togglingId.value = null) },
    );
}

// ── Deactivate (PATCH) ────────────────────────────────────────────
const deactivating = ref(false);
function deactivate(): void {
    deactivating.value = true;
    router.patch(
        route('admin.categories.deactivate', props.category.id),
        {},
        { preserveScroll: true, onFinish: () => (deactivating.value = false) },
    );
}
</script>

<template>
    <AdminLayout :title="`Edit: ${category.name}`" heading="Edit Category">
        <Breadcrumb
            :items="[{ label: 'Categories', url: route('admin.categories') }, { label: category.name }]"
        />

        <div class="grid gap-4 lg:grid-cols-[1fr_22rem]">
            <!-- Main column -->
            <div class="flex flex-col gap-3">
                <!-- Category details -->
                <div class="card-p">
                    <h2 class="section-title mb-3">Category Details</h2>
                    <form class="flex flex-col gap-3" @submit.prevent="save">
                        <div>
                            <label class="label">Name <span class="text-rose-500">*</span></label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="input"
                                :class="form.errors.name && 'input-error'"
                                maxlength="100"
                            />
                            <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="label">Parent</label>
                            <select
                                v-model="form.parent_id"
                                class="select"
                                :class="form.errors.parent_id && 'input-error'"
                            >
                                <option value="">— Top level —</option>
                                <option v-for="p in parents" :key="p.id" :value="String(p.id)">{{ p.name }}</option>
                            </select>
                            <p v-if="form.errors.parent_id" class="field-error">{{ form.errors.parent_id }}</p>
                        </div>

                        <div>
                            <label class="label">Icon</label>
                            <input
                                v-model="form.icon"
                                type="text"
                                class="input"
                                :class="form.errors.icon && 'input-error'"
                                maxlength="50"
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

                        <div>
                            <button type="submit" class="btn-primary" :disabled="form.processing">Save changes</button>
                        </div>
                    </form>
                </div>

                <!-- Dynamic attributes -->
                <div class="card-p">
                    <h2 class="section-title mb-3">Dynamic Attributes</h2>

                    <div v-if="attributes.length" class="table-wrap mb-3">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Label</th>
                                    <th>Key</th>
                                    <th>Type</th>
                                    <th>Required</th>
                                    <th>Active</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="attr in attributes" :key="attr.id">
                                    <td class="font-medium">{{ attr.label }}</td>
                                    <td class="font-mono text-xs">{{ attr.key }}</td>
                                    <td><span class="badge-slate">{{ attr.type }}</span></td>
                                    <td>{{ attr.is_required ? '✓' : '—' }}</td>
                                    <td>{{ attr.is_active ? '✓' : '—' }}</td>
                                    <td>
                                        <button
                                            type="button"
                                            class="btn-ghost btn-sm"
                                            :disabled="togglingId === attr.id"
                                            @click="toggleAttribute(attr)"
                                        >
                                            {{ attr.is_active ? 'Disable' : 'Enable' }}
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h3 class="mb-2 mt-2 font-semibold text-slate-900">Add Attribute</h3>
                    <form class="flex flex-col gap-2" @submit.prevent="addAttribute">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="label">Label <span class="text-rose-500">*</span></label>
                                <input
                                    v-model="attrForm.label"
                                    type="text"
                                    class="input"
                                    :class="attrForm.errors.label && 'input-error'"
                                    placeholder="e.g. Subscribers"
                                    maxlength="100"
                                />
                                <p v-if="attrForm.errors.label" class="field-error">{{ attrForm.errors.label }}</p>
                            </div>
                            <div>
                                <label class="label">Key (slug) <span class="text-rose-500">*</span></label>
                                <input
                                    v-model="attrForm.key"
                                    type="text"
                                    class="input"
                                    :class="attrForm.errors.key && 'input-error'"
                                    placeholder="e.g. subscribers"
                                    maxlength="60"
                                />
                                <p v-if="attrForm.errors.key" class="field-error">{{ attrForm.errors.key }}</p>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <label class="label">Type</label>
                                <select
                                    v-model="attrForm.type"
                                    class="select"
                                    :class="attrForm.errors.type && 'input-error'"
                                >
                                    <option v-for="t in attributeTypes" :key="t" :value="t">
                                        {{ t.charAt(0).toUpperCase() + t.slice(1) }}
                                    </option>
                                </select>
                                <p v-if="attrForm.errors.type" class="field-error">{{ attrForm.errors.type }}</p>
                            </div>
                            <div>
                                <label class="label">Unit (optional)</label>
                                <input
                                    v-model="attrForm.unit"
                                    type="text"
                                    class="input"
                                    maxlength="30"
                                    placeholder="e.g. /month"
                                />
                            </div>
                            <div>
                                <label class="label">Sort order</label>
                                <input v-model.number="attrForm.position" type="number" class="input" min="0" />
                            </div>
                        </div>
                        <div>
                            <label class="label">Options (one per line, for select type)</label>
                            <textarea
                                v-model="attrForm.options"
                                rows="3"
                                class="textarea text-sm"
                                placeholder="Option A&#10;Option B&#10;Option C"
                            ></textarea>
                        </div>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 text-sm">
                                <input v-model="attrForm.is_required" type="checkbox" class="checkbox" /> Required
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input v-model="attrForm.is_filterable" type="checkbox" class="checkbox" /> Filterable
                            </label>
                        </div>
                        <div>
                            <button type="submit" class="btn-outline" :disabled="attrForm.processing">
                                + Add attribute
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sidebar: danger zone -->
            <div>
                <div class="card-p">
                    <h2 class="section-title mb-2">Danger Zone</h2>
                    <button
                        type="button"
                        class="btn-danger w-full"
                        :disabled="deactivating || !category.is_active"
                        @click="deactivate"
                    >
                        {{ category.is_active ? 'Deactivate category' : 'Already inactive' }}
                    </button>
                    <p class="mt-2 text-xs text-slate-500">
                        Deactivating hides this category from new listings. Historical data is preserved.
                    </p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
