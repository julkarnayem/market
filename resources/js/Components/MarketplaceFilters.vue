<script setup lang="ts">
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import type { DynamicAttribute, MarketplaceFilters } from '@/types';

interface RootCategory {
    slug: string;
    name: string;
    icon: string | null;
    children: { slug: string; name: string }[];
}

const props = defineProps<{
    rootCategories: RootCategory[];
    dynamicAttributes: DynamicAttribute[];
    filters: MarketplaceFilters;
}>();

const emit = defineEmits<{ applied: [] }>();

/** Local, editable copy of the server's filter state. */
const form = reactive({
    q: props.filters.q ?? '',
    min_price: props.filters.min_price ?? '',
    max_price: props.filters.max_price ?? '',
    verified_only: props.filters.verified_only,
    featured_only: props.filters.featured_only,
    in_stock: props.filters.in_stock,
    attributes: { ...props.filters.attributes } as Record<string, string>,
});

/** Drop empty values so the URL stays clean and shareable. */
function apply() {
    const query: Record<string, string | number> = {};

    if (form.q) query.q = form.q;
    if (props.filters.category) query.category = props.filters.category;
    if (props.filters.subcategory) query.subcategory = props.filters.subcategory;
    if (form.min_price !== '') query.min_price = form.min_price;
    if (form.max_price !== '') query.max_price = form.max_price;
    if (form.verified_only) query.verified_only = 1;
    if (form.featured_only) query.featured_only = 1;
    if (form.in_stock) query.in_stock = 1;
    if (props.filters.sort && props.filters.sort !== 'newest') query.sort = props.filters.sort;

    for (const [key, value] of Object.entries(form.attributes)) {
        if (value !== '' && value != null) query[key] = value;
    }

    router.get(route('marketplace.index'), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
    emit('applied');
}

/** Category links keep the other active filters, but always reset paging. */
function categoryQuery(category?: string, subcategory?: string) {
    const query: Record<string, string | number> = {};
    if (form.q) query.q = form.q;
    if (category) query.category = category;
    if (subcategory) query.subcategory = subcategory;
    if (form.min_price !== '') query.min_price = form.min_price;
    if (form.max_price !== '') query.max_price = form.max_price;
    if (form.verified_only) query.verified_only = 1;
    if (form.featured_only) query.featured_only = 1;
    if (form.in_stock) query.in_stock = 1;
    if (props.filters.sort && props.filters.sort !== 'newest') query.sort = props.filters.sort;
    return route('marketplace.index', query);
}

const isNumeric = (type: string) => type === 'number' || type === 'decimal';
</script>

<template>
    <form @submit.prevent="apply">
        <!-- Search (desktop only — the mobile bar has its own) -->
        <div class="mb-4 hidden lg:block">
            <label for="filter-q" class="label">Search</label>
            <input id="filter-q" v-model="form.q" type="search" placeholder="Keyword…" class="input" />
        </div>

        <!-- Categories -->
        <div class="mb-4">
            <p class="label">Category</p>
            <div class="flex flex-col gap-0.5">
                <Link
                    :href="categoryQuery()"
                    class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm"
                    :class="!filters.category
                        ? 'bg-brand-50 font-medium text-brand-700'
                        : 'text-slate-600 hover:bg-slate-100'"
                >
                    All categories
                </Link>

                <div v-for="cat in rootCategories" :key="cat.slug">
                    <Link
                        :href="categoryQuery(cat.slug)"
                        class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium"
                        :class="filters.category === cat.slug
                            ? 'bg-brand-50 text-brand-700'
                            : 'text-slate-700 hover:bg-slate-100'"
                    >
                        <span>{{ cat.icon ?? '🗂️' }}</span>{{ cat.name }}
                    </Link>

                    <div v-if="filters.category === cat.slug && cat.children.length" class="ms-4 mt-1 flex flex-col gap-0.5">
                        <Link
                            v-for="sub in cat.children"
                            :key="sub.slug"
                            :href="categoryQuery(cat.slug, sub.slug)"
                            class="block rounded-lg px-2 py-1 text-xs"
                            :class="filters.subcategory === sub.slug
                                ? 'bg-brand-50 font-medium text-brand-700'
                                : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900'"
                        >
                            {{ sub.name }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Price -->
        <div class="mb-4">
            <p class="label">Price (৳)</p>
            <div class="grid grid-cols-2 gap-2">
                <input v-model="form.min_price" type="number" min="0" step="1" placeholder="Min" aria-label="Minimum price" class="input text-sm" />
                <input v-model="form.max_price" type="number" min="0" step="1" placeholder="Max" aria-label="Maximum price" class="input text-sm" />
            </div>
        </div>

        <!-- Toggles -->
        <div class="mb-4 flex flex-col gap-2">
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.verified_only" type="checkbox" class="checkbox" />
                <span class="text-slate-900">Verified sellers only</span>
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.featured_only" type="checkbox" class="checkbox" />
                <span class="text-slate-900">Featured only</span>
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.in_stock" type="checkbox" class="checkbox" />
                <span class="text-slate-900">In stock only</span>
            </label>
        </div>

        <!-- Dynamic attribute filters (only shown once a subcategory is chosen) -->
        <div v-if="dynamicAttributes.length" class="mb-4 border-t border-slate-200 pt-4">
            <p class="label mb-2">{{ filters.subcategory ? '' : '' }}Filters</p>
            <div class="flex flex-col gap-3">
                <div v-for="attr in dynamicAttributes" :key="attr.key">
                    <label class="mb-1 block text-xs font-medium text-slate-500">
                        {{ attr.label }}
                        <span v-if="attr.unit" class="text-slate-400">({{ attr.unit }})</span>
                    </label>

                    <div v-if="isNumeric(attr.type)" class="grid grid-cols-2 gap-1">
                        <input
                            v-model="form.attributes[`${attr.key}_min`]"
                            type="number" step="any" placeholder="Min"
                            :aria-label="`${attr.label} minimum`"
                            class="input py-1 text-xs"
                        />
                        <input
                            v-model="form.attributes[`${attr.key}_max`]"
                            type="number" step="any" placeholder="Max"
                            :aria-label="`${attr.label} maximum`"
                            class="input py-1 text-xs"
                        />
                    </div>

                    <select
                        v-else-if="attr.type === 'select' && attr.options.length"
                        v-model="form.attributes[attr.key]"
                        :aria-label="attr.label"
                        class="select text-sm"
                    >
                        <option value="">Any</option>
                        <option v-for="opt in attr.options" :key="opt" :value="opt">{{ opt }}</option>
                    </select>

                    <select
                        v-else-if="attr.type === 'boolean'"
                        v-model="form.attributes[attr.key]"
                        :aria-label="attr.label"
                        class="select text-sm"
                    >
                        <option value="">Any</option>
                        <option value="yes">Yes</option>
                        <option value="no">No</option>
                    </select>

                    <input
                        v-else
                        v-model="form.attributes[attr.key]"
                        type="text" placeholder="Filter…"
                        :aria-label="attr.label"
                        class="input text-sm"
                    />
                </div>
            </div>
        </div>

        <div class="mt-4 flex gap-2">
            <button type="submit" class="btn-primary flex-grow py-2 text-sm">Apply filters</button>
            <Link :href="route('marketplace.index')" class="btn-ghost py-2 text-sm">Clear</Link>
        </div>
    </form>
</template>
