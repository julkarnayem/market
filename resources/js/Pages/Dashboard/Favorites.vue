<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import type { AssetCardData, Paginated } from '@/types';

/** One saved asset — whitelisted by FavoriteController::index(). */
interface FavoriteRow {
    /** Favorite row id, for the remove route. */
    id: number;
    asset: AssetCardData;
    /** Asset status (AssetCardData omits it), for the badge. */
    status: string;
}

defineProps<{
    favorites: Paginated<FavoriteRow>;
}>();

/** Unsave and let the redirect back re-render the list without the row. */
function remove(id: number) {
    router.delete(route('favorites.remove', id), { preserveScroll: true });
}
</script>

<template>
    <DashboardLayout title="Favorites" heading="Saved Assets">
        <div v-if="favorites.data.length === 0" class="card-p text-center">
            <p class="mb-2 text-4xl">★</p>
            <h2 class="font-display text-lg font-bold text-slate-900">No saved assets</h2>
            <p class="mt-1 text-sm text-slate-500">
                Tap the ★ on any listing to save it here for later.
            </p>
            <Link :href="route('marketplace.index')" class="btn-outline mt-4">Browse marketplace</Link>
        </div>

        <template v-else>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="fav in favorites.data"
                    :key="fav.id"
                    class="card flex flex-col overflow-hidden"
                >
                    <Link
                        :href="route('marketplace.show', fav.asset.slug)"
                        class="grid aspect-[16/10] place-items-center bg-gradient-to-br from-brand-50 to-mint-50 text-4xl"
                    >
                        {{ fav.asset.category.icon ?? '🧩' }}
                    </Link>
                    <div class="flex flex-grow flex-col p-3">
                        <div class="flex items-start justify-between gap-2">
                            <Link
                                :href="route('marketplace.show', fav.asset.slug)"
                                class="font-semibold text-slate-900 hover:text-brand-700"
                            >
                                {{ fav.asset.title }}
                            </Link>
                            <button
                                type="button"
                                class="btn-ghost btn-sm flex-shrink-0 text-rose-600"
                                title="Remove"
                                aria-label="Remove from favorites"
                                @click="remove(fav.id)"
                            >
                                ✕
                            </button>
                        </div>
                        <div class="mt-2 flex items-center justify-between">
                            <span class="money font-bold text-slate-900">{{ fav.asset.price_formatted }}</span>
                            <StatusBadge :status="fav.status" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <Pagination :links="favorites.links" :total="favorites.total" />
            </div>
        </template>
    </DashboardLayout>
</template>
