<script setup lang="ts">
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface TemplateRow {
    id: number;
    title: string;
    category: string | null;
    body: string;
    is_active: boolean;
    creator: string | null;
    created: string;
}

defineProps<{
    groups: { label: string; templates: TemplateRow[] }[];
    /** Placeholders SupportResponseTemplate::render() substitutes. */
    variables: string[];
}>();

// The page authorizes tickets.manage, and so does every write below — anyone
// who can see this page can edit, so no client-side permission split.

// ── Create (POST) ─────────────────────────────────────────────────
const createForm = useForm<{ title: string; category: string; body: string }>({
    title: '',
    category: '',
    body: '',
});

function create(): void {
    createForm.post(route('admin.support-templates.store'), {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
}

// ── Edit (PATCH) ──────────────────────────────────────────────────
// One form shared by every card: only one template is open at a time. The
// Blade wrapped this panel in Alpine (`x-show`), but Alpine is never loaded on
// the admin layout while custom.css hides `[x-cloak]` outright, so the panel
// was permanently display:none and update() had no reachable caller.
const editingId = ref<number | null>(null);
const editForm = useForm<{ title: string; category: string; body: string; is_active: boolean }>({
    title: '',
    category: '',
    body: '',
    is_active: true,
});

function openEditor(t: TemplateRow): void {
    editingId.value = t.id;
    editForm.clearErrors();
    editForm.title = t.title;
    editForm.category = t.category ?? '';
    editForm.body = t.body;
    editForm.is_active = t.is_active;
}

function saveEditor(): void {
    if (editingId.value === null) return;
    editForm.patch(route('admin.support-templates.update', editingId.value), {
        preserveScroll: true,
        onSuccess: () => (editingId.value = null),
    });
}

// ── Active toggle (PATCH) ─────────────────────────────────────────
// update() validates title and body as required, so a quick toggle re-sends
// them unchanged (same idiom as the attribute toggle on Categories/Edit).
// `is_active` goes out as a real JSON boolean: the Blade's checkbox simply
// omitted the key when unchecked, so validate() dropped it and no template
// could ever be deactivated.
const togglingId = ref<number | null>(null);

function toggleActive(t: TemplateRow): void {
    togglingId.value = t.id;
    const payload: Record<string, string | boolean> = {
        title: t.title,
        body: t.body,
        is_active: !t.is_active,
    };
    if (t.category) payload.category = t.category;

    router.patch(route('admin.support-templates.update', t.id), payload, {
        preserveScroll: true,
        onFinish: () => (togglingId.value = null),
    });
}
</script>

<template>
    <AdminLayout title="Support Templates" heading="Support Response Templates">
        <div class="grid gap-4 lg:grid-cols-[1fr_22rem]">
            <!-- Templates, grouped by category -->
            <div class="flex flex-col gap-3">
                <div v-for="group in groups" :key="group.label" class="card-p">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h2 class="section-title">{{ group.label }}</h2>
                        <span class="badge-slate">{{ group.templates.length }}</span>
                    </div>

                    <div class="flex flex-col gap-2">
                        <div
                            v-for="t in group.templates"
                            :key="t.id"
                            class="rounded-xl border border-slate-200 p-3"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ t.title }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        Added {{ t.created }}<span v-if="t.creator"> by {{ t.creator }}</span>
                                    </p>
                                </div>
                                <span :class="t.is_active ? 'badge-mint' : 'badge-slate'">
                                    {{ t.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>

                            <p class="mt-2 whitespace-pre-line text-xs text-slate-600">{{ t.body }}</p>

                            <div class="mt-2 flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="btn-ghost btn-sm"
                                    @click="editingId === t.id ? (editingId = null) : openEditor(t)"
                                >
                                    {{ editingId === t.id ? 'Cancel' : 'Edit' }}
                                </button>
                                <button
                                    type="button"
                                    class="btn-outline btn-sm"
                                    :disabled="togglingId === t.id"
                                    @click="toggleActive(t)"
                                >
                                    {{ t.is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </div>

                            <!-- Inline editor -->
                            <form
                                v-if="editingId === t.id"
                                class="mt-3 flex flex-col gap-2 border-t border-slate-100 pt-3"
                                @submit.prevent="saveEditor"
                            >
                                <div>
                                    <label class="label">Title <span class="text-rose-500">*</span></label>
                                    <input
                                        v-model="editForm.title"
                                        type="text"
                                        class="input"
                                        :class="editForm.errors.title && 'input-error'"
                                        maxlength="200"
                                    />
                                    <p v-if="editForm.errors.title" class="field-error">
                                        {{ editForm.errors.title }}
                                    </p>
                                </div>

                                <div>
                                    <label class="label">Category</label>
                                    <input
                                        v-model="editForm.category"
                                        type="text"
                                        class="input"
                                        :class="editForm.errors.category && 'input-error'"
                                        maxlength="50"
                                        placeholder="General"
                                    />
                                    <p v-if="editForm.errors.category" class="field-error">
                                        {{ editForm.errors.category }}
                                    </p>
                                </div>

                                <div>
                                    <label class="label">Body <span class="text-rose-500">*</span></label>
                                    <textarea
                                        v-model="editForm.body"
                                        rows="5"
                                        class="textarea"
                                        :class="editForm.errors.body && 'input-error'"
                                        maxlength="5000"
                                    ></textarea>
                                    <p v-if="editForm.errors.body" class="field-error">
                                        {{ editForm.errors.body }}
                                    </p>
                                </div>

                                <label class="flex items-center gap-2 text-sm">
                                    <input v-model="editForm.is_active" type="checkbox" class="checkbox" /> Active
                                </label>

                                <div class="flex gap-2">
                                    <button type="submit" class="btn-primary btn-sm" :disabled="editForm.processing">
                                        Save changes
                                    </button>
                                    <button type="button" class="btn-ghost btn-sm" @click="editingId = null">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div v-if="groups.length === 0" class="card-p text-center">
                    <p class="mb-1 text-3xl">📝</p>
                    <p class="text-sm font-semibold text-slate-900">No templates yet</p>
                    <p class="mt-1 text-sm text-slate-500">
                        Create canned replies so the support team answers common questions consistently.
                    </p>
                </div>
            </div>

            <!-- New template -->
            <div class="flex flex-col gap-3">
                <div class="card-p">
                    <h2 class="section-title mb-3">New Template</h2>
                    <form class="flex flex-col gap-3" @submit.prevent="create">
                        <div>
                            <label class="label">Title <span class="text-rose-500">*</span></label>
                            <input
                                v-model="createForm.title"
                                type="text"
                                class="input"
                                :class="createForm.errors.title && 'input-error'"
                                maxlength="200"
                            />
                            <p v-if="createForm.errors.title" class="field-error">{{ createForm.errors.title }}</p>
                        </div>

                        <div>
                            <label class="label">Category</label>
                            <input
                                v-model="createForm.category"
                                type="text"
                                class="input"
                                :class="createForm.errors.category && 'input-error'"
                                maxlength="50"
                                placeholder="General"
                            />
                            <p v-if="createForm.errors.category" class="field-error">
                                {{ createForm.errors.category }}
                            </p>
                        </div>

                        <div>
                            <label class="label">Body <span class="text-rose-500">*</span></label>
                            <textarea
                                v-model="createForm.body"
                                rows="6"
                                class="textarea"
                                :class="createForm.errors.body && 'input-error'"
                                maxlength="5000"
                            ></textarea>
                            <p v-if="createForm.errors.body" class="field-error">{{ createForm.errors.body }}</p>
                        </div>

                        <div>
                            <button type="submit" class="btn-primary w-full" :disabled="createForm.processing">
                                Create template
                            </button>
                        </div>
                    </form>
                </div>

                <div class="card-p">
                    <h2 class="section-title mb-2">Placeholders</h2>
                    <p class="mb-2 text-xs text-slate-500">
                        These are replaced when a template is used. Anything else stays as typed.
                    </p>
                    <div class="flex flex-wrap gap-1">
                        <code v-for="v in variables" :key="v" class="badge-slate font-mono">{{ '{' + v + '}' }}</code>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
