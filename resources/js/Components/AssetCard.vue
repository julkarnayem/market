<script setup lang="ts">
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import type { AssetCardData } from '@/types';

const props = withDefaults(
    defineProps<{
        asset: AssetCardData;
        isFavorited?: boolean;
    }>(),
    { isFavorited: false },
);

const page = usePage();
const favorited = ref(props.isFavorited);
const saving = ref(false);

/**
 * Favourites hit the JSON endpoint directly rather than going through an
 * Inertia visit: FavoriteController::toggle returns {favorited} for JSON
 * requests, so one card can update without re-rendering the whole page.
 */
async function toggleFavorite() {
    if (saving.value) return;
    saving.value = true;
    try {
        const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
        const res = await fetch(route('favorites.toggle'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify({ asset_id: props.asset.id }),
        });
        if (res.ok) {
            const data = (await res.json()) as { favorited: boolean };
            favorited.value = data.favorited;
        }
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <article
        class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white transition
               duration-200"
        :class="asset.is_sold_out
            ? 'opacity-75'
            : 'hover:border-brand-500 hover:shadow-pop'"
    >
        <!-- Cover -->
        <div class="relative aspect-[16/10] overflow-hidden bg-gradient-to-br from-brand-50 to-mint-50">
            <Link :href="route('marketplace.show', asset.slug)" class="block h-full w-full">
                <img
                    v-if="asset.cover_image_url"
                    :src="asset.cover_image_url"
                    :alt="asset.title"
                    loading="lazy"
                    class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                />
                <div v-else class="grid h-full w-full place-items-center text-4xl">
                    {{ asset.category.icon ?? '🧩' }}
                </div>
            </Link>

            <!-- Badges -->
            <div class="pointer-events-none absolute left-2 top-2 flex flex-col items-start gap-1">
                <span v-if="asset.is_featured" class="badge-amber">⭐ Featured</span>
                <span v-if="asset.is_sold_out" class="badge-slate">⊘ Sold Out</span>
            </div>

            <!-- Favourite -->
            <button
                v-if="page.props.auth.user"
                type="button"
                class="absolute right-2 top-2 grid h-8 w-8 place-items-center rounded-full bg-white/90 text-lg
                       shadow transition hover:bg-white disabled:opacity-60"
                :disabled="saving"
                :aria-pressed="favorited"
                :aria-label="favorited ? 'Remove from favourites' : 'Add to favourites'"
                @click="toggleFavorite"
            >
                <span :class="favorited ? 'text-amber-500' : 'text-slate-500'">{{ favorited ? '★' : '☆' }}</span>
            </button>
            <Link
                v-else
                :href="route('login')"
                title="Log in to save"
                aria-label="Log in to save"
                class="absolute right-2 top-2 grid h-8 w-8 place-items-center rounded-full bg-white/90 text-lg
                       text-slate-500 shadow transition hover:bg-white"
            >
                ☆
            </Link>
        </div>

        <!-- Body -->
        <div class="flex flex-grow flex-col p-3">
            <div class="mb-1 flex flex-wrap items-center gap-1">
                <span class="badge-slate">{{ asset.category.name }}</span>
                <span v-if="asset.seller.is_verified_seller" class="badge-mint">✓ Verified</span>
            </div>

            <Link
                :href="route('marketplace.show', asset.slug)"
                class="flex-grow text-sm font-semibold text-slate-900 hover:text-brand-700"
            >
                {{ asset.title }}
            </Link>

            <p v-if="asset.quantity > 1" class="mt-1 text-xs text-slate-500">
                <template v-if="asset.is_sold_out">Sold out</template>
                <template v-else>{{ asset.available_quantity }} of {{ asset.quantity }} available</template>
            </p>

            <div class="mt-2 flex items-end justify-between gap-2">
                <span
                    class="money text-lg font-bold"
                    :class="asset.is_sold_out ? 'text-slate-400' : 'text-brand-600'"
                >
                    {{ asset.price_formatted }}
                </span>
                <Link
                    :href="asset.seller.profile_url"
                    class="max-w-[90px] truncate text-xs text-slate-500 hover:text-slate-700"
                >
                    {{ asset.seller.name }}
                </Link>
            </div>
        </div>
    </article>
</template>
