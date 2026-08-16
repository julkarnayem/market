<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import AssetCard from '@/Components/AssetCard.vue';
import CategoryCard from '@/Components/CategoryCard.vue';
import type { AssetCardData, Category } from '@/types';

defineProps<{
    categories: Category[];
    featuredAssets: AssetCardData[];
    latestAssets: AssetCardData[];
}>();

const q = ref('');
function search() {
    router.get(route('marketplace.index'), { q: q.value || undefined });
}

const trustItems = [
    { title: 'Payment Protection', desc: 'All payments held securely until delivery confirmed.', d: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z' },
    { title: '72h Buyer Protection', desc: 'Dispute within 72 hours of delivery — fully protected.', d: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
    { title: 'Verified Sellers', desc: 'Every seller completes identity verification before listing.', d: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
    { title: 'Admin Moderated', desc: 'Every listing reviewed by our team before going live.', d: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' },
];

const steps = [
    { num: '01', title: 'Find a Listing', desc: 'Browse verified listings across categories. Filter by price, type, or category.', icon: '🔍' },
    { num: '02', title: 'Pay Securely', desc: 'Complete payment through UddoktaPay. Funds are held securely until delivery.', icon: '🔒' },
    { num: '03', title: 'Receive Your Asset', desc: 'The seller delivers your asset through the private order chat.', icon: '📦' },
    { num: '04', title: 'Confirm & Complete', desc: 'Accept delivery or raise a dispute within 72 hours. Orders auto-complete.', icon: '✅' },
];

const verificationPoints = [
    { title: 'NID, Passport, or Driving License', sub: 'Document identity check' },
    { title: 'Admin review process', sub: 'Manual approval by our team' },
    { title: 'Verified badge on profile', sub: 'Publicly visible on all listings' },
];

const verificationCards = [
    { icon: '🪪', title: 'Identity Verified', sub: 'NID / DOB check' },
    { icon: '✅', title: 'Admin Approved', sub: 'Manual review passed' },
    { icon: '🛡️', title: 'Buyer Protected', sub: '72h dispute window' },
];
</script>

<template>
    <Head>
        <meta
            name="description"
            content="Bangladesh's trusted marketplace for digital assets — social accounts, websites, domains and software. Verified sellers, 72h buyer protection, BDT payouts."
        />
    </Head>

    <PublicLayout>
        <!-- ── Hero ─────────────────────────────────────────── -->
        <section class="border-b border-slate-200 bg-gradient-to-br from-white to-mint-50">
            <div class="mx-auto max-w-7xl px-3 py-14 sm:px-4">
                <div class="mx-auto max-w-3xl text-center">
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 px-3 py-2 text-sm font-medium text-brand-800">
                        <svg class="h-4 w-4 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        Secure Digital Asset Marketplace
                    </div>

                    <h1 class="mb-4 font-display text-4xl font-bold leading-tight text-slate-900 sm:text-5xl lg:text-6xl">
                        Buy &amp; Sell<br />
                        <span class="text-brand-500">Digital Assets</span><br />
                        With Confidence
                    </h1>

                    <p class="mx-auto mb-6 max-w-[540px] text-lg text-slate-600">
                        Social pages, websites, domains and software — from verified sellers, with secure
                        escrow-style payouts in <strong class="font-semibold text-slate-900">৳ BDT</strong>.
                    </p>

                    <div class="mb-6 flex flex-wrap items-center justify-center gap-3">
                        <Link
                            :href="route('marketplace.index')"
                            class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-brand-500 px-7 py-3
                                   font-semibold text-white transition hover:bg-brand-600"
                        >
                            Browse Marketplace
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                            </svg>
                        </Link>
                        <Link
                            :href="route('register')"
                            class="inline-flex items-center justify-center gap-1.5 rounded-xl border-[1.5px]
                                   border-slate-200 px-7 py-3 font-semibold text-slate-700 transition
                                   hover:border-slate-300 hover:bg-slate-50"
                        >
                            Start Selling
                        </Link>
                    </div>

                    <!-- Hero search -->
                    <form class="relative mx-auto max-w-2xl" @submit.prevent="search">
                        <div class="flex items-center overflow-hidden rounded-2xl border-2 border-slate-200 bg-white shadow-lg focus-within:border-brand-500">
                            <div class="flex-shrink-0 pe-2 ps-3">
                                <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input
                                v-model="q"
                                type="search"
                                name="q"
                                aria-label="Search digital assets"
                                placeholder="Search digital assets, pages, groups, websites…"
                                class="flex-grow border-0 px-2 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:ring-0"
                            />
                            <div class="p-2">
                                <button type="submit" class="rounded-xl bg-brand-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-600">
                                    Search
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Trust stats -->
                <div class="mx-auto mt-10 grid max-w-sm grid-cols-3 text-center">
                    <div>
                        <div class="money text-2xl font-bold text-brand-500">72h</div>
                        <div class="mt-1 text-xs text-slate-500">Buyer protection</div>
                    </div>
                    <div class="border-x border-slate-200">
                        <div class="money text-2xl font-bold text-brand-500">10%</div>
                        <div class="mt-1 text-xs text-slate-500">Flat seller fee</div>
                    </div>
                    <div>
                        <div class="money text-2xl font-bold text-brand-500">৳50</div>
                        <div class="mt-1 text-xs text-slate-500">Min. withdrawal</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Trust strip ──────────────────────────────────── -->
        <section class="border-b border-slate-200 bg-white">
            <div class="mx-auto max-w-7xl px-3 py-6 sm:px-4">
                <div class="grid grid-cols-2 gap-px overflow-hidden rounded-2xl bg-slate-100 md:grid-cols-4">
                    <div v-for="item in trustItems" :key="item.title" class="flex flex-col items-center gap-2 bg-white p-5 text-center">
                        <div class="grid h-10 w-10 place-items-center rounded-xl bg-brand-50">
                            <svg class="h-5 w-5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path :d="item.d" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ item.title }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ item.desc }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Categories ───────────────────────────────────── -->
        <section class="bg-slate-50 py-12">
            <div class="mx-auto max-w-7xl px-3 sm:px-4">
                <div class="mb-6 flex items-end justify-between gap-4">
                    <div>
                        <h2 class="font-display text-2xl font-bold text-slate-900">Explore Digital Assets</h2>
                        <p class="mt-1 text-slate-500">Find the right digital asset for your needs.</p>
                    </div>
                    <Link :href="route('marketplace.index')" class="whitespace-nowrap text-sm font-semibold text-brand-500 hover:text-brand-600">
                        All assets →
                    </Link>
                </div>

                <div v-if="categories.length === 0" class="py-12 text-center">
                    <p class="mb-2 text-4xl">🗂️</p>
                    <p class="font-medium text-slate-500">Categories coming soon</p>
                </div>
                <div v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                    <CategoryCard v-for="cat in categories" :key="cat.slug" :category="cat" />
                </div>
            </div>
        </section>

        <!-- ── Featured listings ────────────────────────────── -->
        <section v-if="featuredAssets.length" class="bg-white py-12">
            <div class="mx-auto max-w-7xl px-3 sm:px-4">
                <div class="mb-6 flex items-end justify-between gap-4">
                    <div>
                        <div class="mb-2 inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-800">
                            ⭐ Promoted
                        </div>
                        <h2 class="font-display text-2xl font-bold text-slate-900">Featured Listings</h2>
                    </div>
                    <Link :href="route('marketplace.index', { featured_only: 1 })" class="whitespace-nowrap text-sm font-semibold text-brand-500 hover:text-brand-600">
                        View all →
                    </Link>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <AssetCard v-for="asset in featuredAssets" :key="asset.id" :asset="asset" />
                </div>
            </div>
        </section>

        <!-- ── Latest listings ──────────────────────────────── -->
        <section v-if="latestAssets.length" class="bg-slate-50 py-12">
            <div class="mx-auto max-w-7xl px-3 sm:px-4">
                <div class="mb-6 flex items-end justify-between gap-4">
                    <div>
                        <h2 class="font-display text-2xl font-bold text-slate-900">Latest Listings</h2>
                        <p class="mt-1 text-slate-500">Newest digital assets on the marketplace.</p>
                    </div>
                    <Link :href="route('marketplace.index')" class="whitespace-nowrap text-sm font-semibold text-brand-500 hover:text-brand-600">
                        View all →
                    </Link>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <AssetCard v-for="asset in latestAssets" :key="asset.id" :asset="asset" />
                </div>
            </div>
        </section>

        <!-- ── How it works ─────────────────────────────────── -->
        <section class="bg-white py-12">
            <div class="mx-auto max-w-7xl px-3 sm:px-4">
                <div class="mb-8 text-center">
                    <h2 class="font-display text-2xl font-bold text-slate-900">How It Works</h2>
                    <p class="mt-2 text-slate-500">Simple, secure, and transparent</p>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="step in steps" :key="step.num" class="rounded-2xl border border-slate-200 bg-white p-6">
                        <div class="money mb-2 text-3xl font-extrabold leading-none text-brand-500">{{ step.num }}</div>
                        <div class="mb-2 text-3xl">{{ step.icon }}</div>
                        <h3 class="mb-2 font-semibold text-slate-900">{{ step.title }}</h3>
                        <p class="text-sm text-slate-500">{{ step.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Seller verification ──────────────────────────── -->
        <section class="bg-brand-50/60 py-12">
            <div class="mx-auto max-w-7xl px-3 sm:px-4">
                <div class="grid grid-cols-1 items-center gap-10 md:grid-cols-2">
                    <div>
                        <div class="mb-3 inline-flex items-center gap-2 rounded-full bg-brand-50 px-2 py-1 text-sm font-medium text-brand-800">
                            <svg class="h-4 w-4 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            Seller Verification
                        </div>
                        <h2 class="mb-3 font-display text-2xl font-bold text-slate-900 sm:text-3xl">
                            Buy From <span class="text-brand-500">Verified Sellers</span> Only
                        </h2>
                        <p class="mb-6 text-slate-600">
                            Every seller must complete identity verification before they can list or sell. This
                            ensures every transaction is with a real, accountable person.
                        </p>

                        <ul class="mb-6 flex flex-col gap-3">
                            <li v-for="point in verificationPoints" :key="point.title" class="flex items-start gap-3">
                                <span class="mt-1 grid h-5 w-5 flex-shrink-0 place-items-center rounded-full bg-brand-50">
                                    <svg class="h-3 w-3 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ point.title }}</p>
                                    <p class="text-xs text-slate-500">{{ point.sub }}</p>
                                </div>
                            </li>
                        </ul>

                        <Link
                            :href="route('legal', 'seller-policy')"
                            class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-brand-500 px-5 py-2.5
                                   text-sm font-semibold text-white transition hover:bg-brand-600"
                        >
                            Learn About Verification
                        </Link>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div
                            v-for="card in verificationCards"
                            :key="card.title"
                            class="rounded-2xl border border-slate-200 bg-white p-4 text-center shadow-sm"
                        >
                            <div class="mb-2 text-3xl">{{ card.icon }}</div>
                            <p class="text-sm font-semibold text-slate-900">{{ card.title }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ card.sub }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Final CTA ────────────────────────────────────── -->
        <section class="border-t border-slate-200 bg-white py-12">
            <div class="mx-auto max-w-3xl px-3 text-center sm:px-4">
                <h2 class="mb-2 font-display text-2xl font-bold text-slate-900 sm:text-3xl">
                    Ready to Buy or Sell Digital Assets?
                </h2>
                <p class="mb-6 text-slate-600">Explore the marketplace or become a verified seller today.</p>
                <div class="flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <Link
                        :href="route('marketplace.index')"
                        class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-brand-500 px-7 py-3
                               font-semibold text-white transition hover:bg-brand-600 sm:w-auto"
                    >
                        Browse Marketplace
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </Link>
                    <Link
                        :href="route('register')"
                        class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl border-[1.5px]
                               border-slate-200 px-7 py-3 font-semibold text-slate-700 transition
                               hover:border-slate-300 hover:bg-slate-50 sm:w-auto"
                    >
                        Sell an Asset
                    </Link>
                </div>
                <p class="mt-6 text-xs text-slate-500">
                    Free to join · Listings are free · 10% seller fee on sales only
                </p>
            </div>
        </section>
    </PublicLayout>
</template>
