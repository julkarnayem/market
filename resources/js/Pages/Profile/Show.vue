<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import AssetCard from '@/Components/AssetCard.vue';
import Pagination from '@/Components/Pagination.vue';
import type { AssetCardData, Paginated } from '@/types';

interface Review {
    id: number;
    reviewer_name: string;
    reviewer_initial: string;
    rating: number;
    comment: string | null;
    created_at: string | null;
    asset_title: string | null;
}

const props = defineProps<{
    profile: {
        name: string;
        initial: string;
        is_verified_seller: boolean;
        has_phone: boolean;
        member_since: string | null;
        profile_url: string;
    };
    stats: { listed: number; sold: number; purchases: number; reviews: number; trades: number };
    tab: string;
    assets: Paginated<AssetCardData> | null;
    reviews: Paginated<Review> | null;
    isOwnProfile: boolean;
}>();

const tabs = computed(() => [
    { key: 'listings', label: 'Active Listings', count: props.stats.listed },
    { key: 'sales', label: 'Completed Sales', count: props.stats.sold },
    { key: 'purchases', label: 'Completed Purchases', count: props.stats.purchases },
    { key: 'reviews', label: 'Reviews', count: props.stats.reviews },
]);

const tabUrl = (key: string) => `${props.profile.profile_url}?tab=${key}`;

const emptyCopy: Record<string, { icon: string; text: string; hint?: string }> = {
    listings: { icon: '🗂️', text: 'No active listings.' },
    sales: { icon: '📦', text: 'No completed sales yet.' },
    purchases: { icon: '🛒', text: 'No completed purchases yet.' },
    reviews: { icon: '⭐', text: 'No reviews yet.', hint: 'Reviews will appear here after completed trades.' },
};
</script>

<template>
    <Head :title="`${profile.name} — Seller Profile`" />

    <PublicLayout>
        <div class="mx-auto max-w-6xl px-3 py-6 sm:px-4">
            <div class="gap-6 lg:grid lg:grid-cols-[18rem_1fr]">
                <!-- ── Sidebar ── -->
                <div class="flex flex-col gap-4">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 text-center">
                        <div class="mb-3 flex justify-center">
                            <div class="grid h-20 w-20 flex-shrink-0 place-items-center rounded-full bg-orange-500 font-display text-3xl font-bold text-white">
                                {{ profile.initial }}
                            </div>
                        </div>

                        <h1 class="text-lg font-bold text-slate-900">{{ profile.name }}</h1>

                        <div
                            class="mt-2 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium"
                            :class="profile.is_verified_seller ? 'bg-brand-50 text-brand-800' : 'bg-slate-100 text-slate-500'"
                        >
                            <svg class="h-3.5 w-3.5" :class="profile.is_verified_seller && 'text-brand-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path
                                    stroke-linecap="round" stroke-linejoin="round" :stroke-width="profile.is_verified_seller ? 2.5 : 2"
                                    :d="profile.is_verified_seller
                                        ? 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'
                                        : 'M20.618 5.984A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'"
                                />
                            </svg>
                            {{ profile.is_verified_seller ? 'Verified Seller' : 'Not Verified' }}
                        </div>

                        <div class="mt-2 flex items-center justify-center gap-1 text-xs font-medium text-brand-600">
                            <span class="h-2 w-2 rounded-full bg-brand-500"></span>
                            Active now
                        </div>

                        <div class="mt-4 grid grid-cols-4 border-t border-slate-200 pt-4">
                            <div v-for="stat in [
                                { label: 'Reviews', value: stats.reviews },
                                { label: 'Trades', value: stats.trades },
                                { label: 'Listed', value: stats.listed },
                                { label: 'Sold', value: stats.sold },
                            ]" :key="stat.label" class="p-1 text-center">
                                <div class="text-xl font-bold text-brand-500">{{ stat.value }}</div>
                                <div class="mt-0.5 text-[0.7rem] uppercase tracking-wide text-slate-400">{{ stat.label }}</div>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-col gap-2 border-t border-slate-200 pt-4 text-start text-sm">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Member since</span>
                                <span class="font-medium text-slate-800">{{ profile.member_since }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Completed sales</span>
                                <span class="font-medium text-slate-800">{{ stats.sold }}</span>
                            </div>
                        </div>

                        <Link
                            v-if="isOwnProfile"
                            :href="route('dashboard.profile')"
                            class="mt-4 block w-full rounded-xl bg-brand-500 py-2 text-center text-sm font-semibold text-white transition hover:bg-brand-600"
                        >
                            Edit Profile
                        </Link>
                    </div>

                    <!-- Reliability -->
                    <div class="rounded-xl border border-slate-200 bg-white p-5">
                        <p class="mb-3 text-xs font-bold uppercase tracking-wide text-slate-400">Reliability Scores</p>
                        <div class="flex gap-2">
                            <div v-for="row in [
                                { role: 'Seller', value: stats.sold > 0 ? 'Active' : 'New' },
                                { role: 'Buyer', value: stats.purchases > 0 ? 'Active' : 'New' },
                            ]" :key="row.role" class="flex-1 rounded-xl border border-slate-200 p-3 text-center">
                                <div class="text-[0.65rem] font-bold uppercase tracking-wider text-slate-400">{{ row.role }}</div>
                                <div class="mt-0.5 font-bold text-slate-700">{{ row.value }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Trust & Safety -->
                    <div class="rounded-xl border border-slate-200 bg-white p-5">
                        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">Trust &amp; Safety</p>
                        <div class="divide-y divide-slate-100">
                            <div v-for="row in [
                                { ok: profile.is_verified_seller, yes: 'ID Verified', no: 'ID Not Verified' },
                                { ok: profile.has_phone, yes: 'Phone Registered', no: 'Phone Not Registered' },
                                { ok: stats.trades > 0, yes: 'Has Completed Trades', no: 'Has Completed Trades' },
                            ]" :key="row.yes" class="flex items-center gap-2.5 py-2 text-[0.8125rem]">
                                <span
                                    class="grid h-[1.125rem] w-[1.125rem] flex-shrink-0 place-items-center rounded-full text-[0.625rem]"
                                    :class="row.ok ? 'bg-brand-500 text-white' : 'bg-slate-200 text-slate-400'"
                                >{{ row.ok ? '✓' : '○' }}</span>
                                <span :class="row.ok ? 'font-medium text-brand-600' : 'text-slate-400'">
                                    {{ row.ok ? row.yes : row.no }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Content ── -->
                <div class="mt-6 lg:mt-0">
                    <div class="mb-4 flex overflow-x-auto border-b border-slate-200">
                        <Link
                            v-for="t in tabs"
                            :key="t.key"
                            :href="tabUrl(t.key)"
                            class="whitespace-nowrap border-b-2 px-4 py-2.5 text-sm transition"
                            :class="tab === t.key
                                ? 'border-orange-500 font-semibold text-orange-500'
                                : 'border-transparent font-medium text-slate-500 hover:text-slate-900'"
                            :aria-current="tab === t.key ? 'page' : undefined"
                        >
                            {{ t.label }}
                            <span class="ms-1.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-slate-100 px-1 text-[0.7rem] font-semibold text-slate-500">
                                {{ t.count }}
                            </span>
                        </Link>
                    </div>

                    <!-- Asset-backed tabs -->
                    <template v-if="assets">
                        <div v-if="assets.data.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                            <p class="mb-2 text-4xl">{{ emptyCopy[tab]?.icon }}</p>
                            <p class="font-medium text-slate-500">{{ emptyCopy[tab]?.text }}</p>
                        </div>
                        <template v-else>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                <AssetCard v-for="asset in assets.data" :key="asset.id" :asset="asset" :is-favorited="asset.is_favorited" />
                            </div>
                            <div class="mt-6">
                                <Pagination :links="assets.links" :total="assets.total" />
                            </div>
                        </template>
                    </template>

                    <!-- Reviews tab -->
                    <template v-else-if="reviews">
                        <div v-if="reviews.data.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                            <p class="mb-2 text-4xl">{{ emptyCopy.reviews.icon }}</p>
                            <p class="font-medium text-slate-500">{{ emptyCopy.reviews.text }}</p>
                            <p class="mt-1 text-sm text-slate-400">{{ emptyCopy.reviews.hint }}</p>
                        </div>
                        <template v-else>
                            <div class="flex flex-col gap-3">
                                <div v-for="review in reviews.data" :key="review.id" class="rounded-2xl border border-slate-200 bg-white p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex items-center gap-3">
                                            <div class="grid h-9 w-9 flex-shrink-0 place-items-center rounded-full bg-brand-500 text-sm font-bold text-white">
                                                {{ review.reviewer_initial }}
                                            </div>
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900">{{ review.reviewer_name }}</p>
                                                <p class="text-xs text-slate-400">{{ review.created_at }}</p>
                                            </div>
                                        </div>
                                        <div class="flex flex-shrink-0 gap-1" :aria-label="`${review.rating} out of 5`">
                                            <span
                                                v-for="i in 5"
                                                :key="i"
                                                class="text-amber-500"
                                                :class="i > review.rating && 'opacity-20'"
                                                aria-hidden="true"
                                            >★</span>
                                        </div>
                                    </div>
                                    <p v-if="review.comment" class="mt-2 text-sm text-slate-600">{{ review.comment }}</p>
                                    <p v-if="review.asset_title" class="mt-2 text-xs text-slate-400">{{ review.asset_title }}</p>
                                </div>
                            </div>
                            <div class="mt-6">
                                <Pagination :links="reviews.links" :total="reviews.total" />
                            </div>
                        </template>
                    </template>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
