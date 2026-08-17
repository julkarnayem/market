<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

/** A subcategory row — whitelisted by Admin\CategoryController::index(). */
interface Sub {
    id: number;
    name: string;
    is_active: boolean;
    is_prohibited: boolean;
    is_restricted: boolean;
    attr_count: number;
    edit_url: string;
}
interface Root {
    id: number;
    name: string;
    icon: string | null;
    position: number;
    is_active: boolean;
    is_prohibited: boolean;
    is_restricted: boolean;
    edit_url: string;
    children: Sub[];
}

defineProps<{ categories: Root[] }>();
</script>

<template>
    <AdminLayout title="Categories" heading="Category Management">
        <template #actions>
            <Link :href="route('admin.categories.create')" class="btn-primary btn-sm">+ New Category</Link>
        </template>

        <div class="flex flex-col gap-3">
            <div v-for="cat in categories" :key="cat.id" class="card overflow-hidden">
                <!-- Root header -->
                <div class="flex items-center justify-between gap-2 border-b border-slate-100 bg-slate-50 px-3 py-2">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl">{{ cat.icon ?? '🗂️' }}</span>
                        <div>
                            <p class="font-semibold text-slate-900">{{ cat.name }}</p>
                            <p class="text-xs text-slate-500">
                                {{ cat.children.length }} subcategories · pos {{ cat.position }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span v-if="cat.is_prohibited" class="badge-rose">Prohibited</span>
                        <span v-else-if="cat.is_restricted" class="badge-amber">Restricted</span>
                        <span v-if="!cat.is_active" class="badge-slate">Inactive</span>
                        <Link :href="cat.edit_url" class="btn-ghost btn-sm">Edit</Link>
                    </div>
                </div>

                <!-- Subcategories -->
                <div v-if="cat.children.length" class="divide-y divide-slate-100">
                    <div
                        v-for="sub in cat.children"
                        :key="sub.id"
                        class="flex items-center justify-between gap-2 px-3 py-2"
                    >
                        <div class="ml-3 flex items-center gap-3">
                            <span class="text-sm text-slate-400">↳</span>
                            <span class="text-sm font-medium text-slate-900">{{ sub.name }}</span>
                            <span class="badge-slate text-xs">{{ sub.attr_count }} attrs</span>
                            <span v-if="sub.is_prohibited" class="badge-rose text-xs">Prohibited</span>
                            <span v-if="!sub.is_active" class="badge-slate text-xs">Inactive</span>
                        </div>
                        <Link :href="sub.edit_url" class="btn-ghost btn-sm">Edit</Link>
                    </div>
                </div>
            </div>

            <p v-if="categories.length === 0" class="card-p text-center text-sm text-slate-500">
                No categories yet. Create your first category to get started.
            </p>
        </div>
    </AdminLayout>
</template>
