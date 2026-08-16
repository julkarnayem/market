<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import AssetCard from '@/Components/AssetCard.vue';
import Pagination from '@/Components/Pagination.vue';
import MarketplaceFilters from '@/Components/MarketplaceFilters.vue';
import type { AssetCardData, DynamicAttribute, MarketplaceFilters as Filters, Paginated } from '@/types';

const props = defineProps<{
    assets: Paginated<AssetCardData>;
    rootCategories: { slug: string; name: string; icon: string | null; children: { slug: string; name: string }[] }[];
    currentCategory: { slug: string; name: string } | null;
    currentSubcategory: { slug: string; name: string } | null;
    dynamicAttributes: DynamicAttribute[];
    filters: Filters;
    sortOptions: Record<string, string>;
}>();

const heading = computed(() => {
    if (props.filters.q) return `"${props.filters.q}"`;
    if (props.currentSubcategory) return props.currentSubcategory.name;
    if (props.currentCategory) return props.currentCategory.name;
    return 'Marketplace';
});

const drawerOpen = ref(false);
const mobileQuery = ref(props.filters.q ?? '');
const sort = ref(props.filters.sort);

/** Keep the query string as the single source of truth for all filter state. */
function visit(overrides: Record<string, string | number | undefined>) {
    const query: Record<string, string | number> = {};
    const base = {
        q: props.filters.q ?? undefined,
        category: props.filters.category ?? undefined,
        subcategory: props.filters.subcategory ?? undefined,
        min_price: props.filters.min_price ?? undefined,
        max_price: props.filters.max_price ?? undefined,
        verified_only: props.filters.verified_only ? 1 : undefined,
        featured_only: props.filters.featured_only ? 1 : undefined,
        in_stock: props.filters.in_stock ? 1 : undefined,
        sort: props.filters.sort !== 'newest' ? props.filters.sort : undefined,
        ...props.filters.attributes,
        ...overrides,
    };
    for (const [k, v] of Object.entries(base)) {
        if (v !== undefined && v !== '' && v !== null) query[k] = v;
    }
    router.get(route('marketplace.index'), query, { preserveState: true, preserveScroll: true, replace: true });
}

// Changing sort re-queries immediately, matching the old onchange="this.form.submit()".
watch(sort, (value) => {
    if (value !== props.filters.sort) visit({ sort: value, page: undefined });
});

const searchMobile = () => visit({ q: mobileQuery.value || undefined, page: undefined });
</script>

<template>
    <Head title="Marketplace — Buy &amp; Sell Digital Assets">
        <meta
            name="description"
            content="Browse verified digital assets — social accounts, websites, domains and software. Secure BDT payouts, buyer protection."
        />
    </Head>

    <PublicLayout>
        <div class="mx-auto max-w-7xl px-3 py-6 sm:px-4">
            <!-- Mobile: search + filter trigger -->
            <div class="mb-4 flex items-center gap-2 lg:hidden">
                <form class="flex flex-grow gap-2" @submit.prevent="searchMobile">
                    <input
                        v-model="mobileQuery"
                        type="search"
                        placeholder="Search listings…"
                        aria-label="Search listings"
                        class="input h-10"
                    />
                    <button type="submit" class="btn-primary h-10 px-3 text-sm">Go</button>
                </form>
                <button
                    type="button"
                    class="btn-outline h-10 flex-shrink-0 gap-1 text-sm"
                    :aria-expanded="drawerOpen"
                    @click="drawerOpen = true"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M7 8h10M11 12h2" />
                    </svg>
                    Filters
                </button>
            </div>

            <!-- Mobile filter drawer -->
            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="drawerOpen" class="fixed inset-0 z-50 lg:hidden">
                    <div class="absolute inset-0 bg-slate-900/50" @click="drawerOpen = false"></div>
                    <aside class="absolute inset-y-0 left-0 w-80 max-w-[90vw] overflow-y-auto bg-white shadow-pop">
                        <div class="flex items-center justify-between border-b border-slate-200 p-4">
                            <h2 class="font-semibold text-slate-900">Filters</h2>
                            <button type="button" class="btn-ghost btn-icon" aria-label="Close filters" @click="drawerOpen = false">
                                ✕
                            </button>
                        </div>
                        <div class="p-4">
                            <MarketplaceFilters
                                :root-categories="rootCategories"
                                :dynamic-attributes="dynamicAttributes"
                                :filters="filters"
                                @applied="drawerOpen = false"
                            />
                        </div>
                    </aside>
                </div>
            </Transition>

            <div class="lg:grid lg:grid-cols-[17rem_1fr] lg:gap-8">
                <!-- Desktop sidebar -->
                <aside class="hidden lg:block">
                    <div class="sticky top-20">
                        <MarketplaceFilters
                            :root-categories="rootCategories"
                            :dynamic-attributes="dynamicAttributes"
                            :filters="filters"
                        />
                    </div>
                </aside>

                <!-- Results -->
                <div>
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h1 class="font-display text-xl font-bold text-slate-900">{{ heading }}</h1>
                            <p class="text-sm text-slate-500">
                                {{ assets.total.toLocaleString() }} asset{{ assets.total === 1 ? '' : 's' }} found
                            </p>
                        </div>
                        <label class="flex items-center gap-2">
                            <span class="sr-only">Sort by</span>
                            <select v-model="sort" class="select w-auto text-sm">
                                <option v-for="(label, value) in sortOptions" :key="value" :value="value">
                                    {{ label }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <div v-if="assets.data.length === 0" class="card-p text-center">
                        <p class="mb-2 text-4xl">🔍</p>
                        <h2 class="font-display text-lg font-bold text-slate-900">No listings found</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Try clearing some filters or searching a different keyword.
                        </p>
                        <Link :href="route('marketplace.index')" class="btn-outline mt-4">Clear all filters</Link>
                    </div>

                    <template v-else>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <AssetCard
                                v-for="asset in assets.data"
                                :key="asset.id"
                                :asset="asset"
                                :is-favorited="asset.is_favorited"
                            />
                        </div>
                        <div class="mt-8">
                            <Pagination :links="assets.links" :total="assets.total" />
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
