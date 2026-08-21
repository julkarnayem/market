<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import type { AssetCardData } from '@/types';

interface AssetDetail {
    id: number;
    slug: string;
    title: string;
    description: string | null;
    price_formatted: string;
    quantity: number;
    available_quantity: number;
    is_sold_out: boolean;
    is_featured: boolean;
    is_purchasable: boolean;
    status: string;
    status_label: string;
    /** single | multiple | unlimited — only `single` accepts bids. */
    inventory_type: 'single' | 'multiple' | 'unlimited';
    inventory_label: string;
    is_unlimited: boolean;
    allows_bidding: boolean;
    has_accepted_bid: boolean;
    top_bid_formatted: string | null;
    bid_count: number;
    min_bid_bdt: string;
    views_count: number;
    favorites_count: number;
    listed_on: string | null;
    images: { url: string }[];
    category: {
        name: string | null;
        slug: string | null;
        icon: string | null;
        parent: { name: string; slug: string } | null;
    };
    seller: {
        name: string | null;
        initial: string;
        is_verified_seller: boolean;
        member_since: string | null;
        bio: string | null;
        profile_url: string | null;
    };
    checkout_url: string;
    bid_url: string;
    contact_url: string;
    attributes: { label: string; value: string; unit: string | null }[];
}

/** One row of the public bid history. Every `can_*` flag is a server decision. */
interface BidRow {
    id: number;
    amount_formatted: string;
    bidder_name: string;
    bidder_initial: string;
    status: string;
    status_label: string;
    placed_human: string | null;
    is_mine: boolean;
    is_top: boolean;
    can_accept: boolean;
    can_reject: boolean;
    can_cancel: boolean;
}

const props = defineProps<{
    asset: AssetDetail;
    /** Rating aggregates + the latest few reviews, built by MarketplaceController. */
    reviews: {
        average: number | null;
        count: number;
        items: {
            id: number;
            rating: number;
            comment: string | null;
            reviewer_name: string;
            reviewer_initial: string;
            at: string | null;
        }[];
    };
    related: AssetCardData[];
    isFavorited: boolean;
    canManage: boolean;
    manageUrl: string | null;
    canBid: boolean;
    canContact: boolean;
    bids: BidRow[];
    acceptedBid: {
        id: number;
        amount_formatted: string;
        buyer_name: string;
        is_mine: boolean;
        pay_url: string | null;
    } | null;
    seo: { description: string; canonical: string; ogImage: string | null; jsonLd: string };
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);

// ── Gallery (was Alpine, which never loaded on the public layout) ──
const active = ref(0);
const hasImages = computed(() => props.asset.images.length > 0);
const next = () => (active.value = (active.value + 1) % props.asset.images.length);
const prev = () => (active.value = (active.value - 1 + props.asset.images.length) % props.asset.images.length);

// ── Bidding ──
// The panel is only rendered for single-item listings, and the server rejects a
// bid on anything else regardless of what the page shows.
const bidsOpen = ref(false);
const bidForm = useForm({ amount_bdt: props.asset.min_bid_bdt });

function submitBid() {
    bidForm.post(props.asset.bid_url, {
        preserveScroll: true,
        onSuccess: () => {
            bidsOpen.value = false;
            // The floor has moved — reset the field to the new minimum.
            bidForm.amount_bdt = props.asset.min_bid_bdt;
        },
    });
}

const busy = ref(false);
function act(url: string) {
    if (busy.value) return;
    busy.value = true;
    router.post(url, {}, { preserveScroll: true, onFinish: () => (busy.value = false) });
}

const contactSeller = () => act(props.asset.contact_url);
const acceptBid = (id: number) => act(route('bids.accept', id));
const rejectBid = (id: number) => act(route('bids.reject', id));
const cancelBid = (id: number) => act(route('bids.cancel', id));

// ── Favourite ──
const favorited = ref(props.isFavorited);
const savingFavorite = ref(false);
async function toggleFavorite() {
    if (savingFavorite.value) return;
    savingFavorite.value = true;
    try {
        const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
        const res = await fetch(route('favorites.toggle'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify({ asset_id: props.asset.id }),
        });
        if (res.ok) favorited.value = (await res.json()).favorited;
    } finally {
        savingFavorite.value = false;
    }
}

const categoryHref = computed(() =>
    route('marketplace.index', { category: props.asset.category.parent?.slug ?? props.asset.category.slug ?? undefined }),
);

/** Stock line under the price, which reads differently per inventory type. */
const stockLine = computed(() => {
    if (props.asset.is_unlimited) return 'Unlimited stock';
    if (props.asset.inventory_type === 'single') return 'One unique item';
    return `${props.asset.available_quantity} of ${props.asset.quantity} available`;
});
</script>

<template>
    <Head :title="asset.title">
        <meta name="description" :content="seo.description" />
        <meta property="og:title" :content="asset.title" />
        <meta property="og:description" :content="seo.description" />
        <meta v-if="seo.ogImage" property="og:image" :content="seo.ogImage" />
        <link rel="canonical" :href="seo.canonical" />
        <component :is="'script'" type="application/ld+json">{{ seo.jsonLd }}</component>
    </Head>

    <PublicLayout>
        <div class="mx-auto max-w-7xl px-3 py-6 sm:px-4">
            <!-- Breadcrumb -->
            <nav class="breadcrumb mb-4" aria-label="Breadcrumb">
                <Link :href="route('marketplace.index')" class="hover:text-slate-700">Marketplace</Link>
                <span class="breadcrumb-sep">/</span>
                <Link :href="categoryHref" class="hover:text-slate-700">
                    {{ asset.category.parent?.name ?? asset.category.name }}
                </Link>
                <template v-if="asset.category.parent">
                    <span class="breadcrumb-sep">/</span>
                    <Link :href="route('marketplace.index', { subcategory: asset.category.slug })" class="hover:text-slate-700">
                        {{ asset.category.name }}
                    </Link>
                </template>
                <span class="breadcrumb-sep">/</span>
                <span class="max-w-[200px] truncate text-slate-900">{{ asset.title }}</span>
            </nav>

            <div class="items-start gap-8 lg:grid lg:grid-cols-[1fr_22rem]">
                <!-- Left column -->
                <div class="flex flex-col gap-4">
                    <!-- Gallery -->
                    <div class="card overflow-hidden">
                        <div class="relative aspect-[16/10] bg-slate-100">
                            <template v-if="hasImages">
                                <img
                                    :src="asset.images[active].url"
                                    :alt="`${asset.title} image ${active + 1}`"
                                    class="h-full w-full object-cover"
                                />
                                <template v-if="asset.images.length > 1">
                                    <button
                                        type="button"
                                        class="absolute left-3 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-slate-900 shadow hover:bg-white"
                                        aria-label="Previous image"
                                        @click="prev"
                                    >‹</button>
                                    <button
                                        type="button"
                                        class="absolute right-3 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-slate-900 shadow hover:bg-white"
                                        aria-label="Next image"
                                        @click="next"
                                    >›</button>
                                </template>
                            </template>
                            <div v-else class="grid h-full w-full place-items-center text-6xl">
                                {{ asset.category.icon ?? '🧩' }}
                            </div>

                            <div class="absolute left-3 top-3 flex flex-wrap gap-2">
                                <span v-if="asset.is_featured" class="badge-amber">⭐ Featured</span>
                                <span v-if="asset.is_sold_out" class="badge-slate">⊘ Sold Out</span>
                                <span v-else-if="asset.has_accepted_bid" class="badge-amber">🔒 Bid Accepted</span>
                                <span v-if="asset.seller.is_verified_seller" class="badge-mint">✓ Verified Seller</span>
                            </div>
                        </div>

                        <div v-if="asset.images.length > 1" class="flex gap-2 overflow-x-auto p-2">
                            <button
                                v-for="(img, i) in asset.images"
                                :key="i"
                                type="button"
                                class="h-16 w-16 flex-shrink-0 overflow-hidden rounded-xl ring-2 transition"
                                :class="active === i ? 'ring-brand-500' : 'ring-transparent hover:ring-slate-300'"
                                :aria-label="`View image ${i + 1}`"
                                :aria-current="active === i"
                                @click="active = i"
                            >
                                <img :src="img.url" alt="" class="h-full w-full object-cover" loading="lazy" />
                            </button>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="card-p">
                        <h2 class="mb-2 font-display text-lg font-bold text-slate-900">About this asset</h2>
                        <div class="whitespace-pre-line text-sm text-slate-700">{{ asset.description }}</div>
                    </div>

                    <!-- Attributes -->
                    <div v-if="asset.attributes.length" class="card-p">
                        <h2 class="mb-3 font-display text-lg font-bold text-slate-900">Asset Details</h2>
                        <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <div v-for="(attr, i) in asset.attributes" :key="i" class="rounded-xl bg-slate-50 p-3">
                                <dt class="mb-1 text-xs text-slate-500">{{ attr.label }}</dt>
                                <dd class="text-sm font-semibold text-slate-900">
                                    {{ attr.value }}
                                    <span v-if="attr.unit" class="text-xs font-normal text-slate-500">{{ attr.unit }}</span>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Recent bids — public history, single-item listings only.
                         This is not chat: custom offers never appear here. -->
                    <div v-if="asset.inventory_type === 'single'" class="card-p">
                        <div class="mb-3 flex items-center justify-between">
                            <h2 class="font-display text-lg font-bold text-slate-900">Recent Bids</h2>
                            <span class="text-xs text-slate-500">{{ asset.bid_count }} total</span>
                        </div>

                        <p v-if="!bids.length" class="text-sm text-slate-500">
                            No bids yet. Be the first to place one.
                        </p>

                        <ul v-else class="flex flex-col divide-y divide-slate-100">
                            <li v-for="bid in bids" :key="bid.id" class="flex flex-wrap items-center gap-3 py-3">
                                <span class="grid h-9 w-9 flex-shrink-0 place-items-center rounded-xl bg-slate-100 text-sm font-bold text-slate-600">
                                    {{ bid.bidder_initial }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-slate-900">
                                        {{ bid.bidder_name }}
                                        <span v-if="bid.is_top && bid.status === 'active'" class="badge-mint ms-1">Top bid</span>
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{ bid.placed_human }} ago · {{ bid.status_label }}
                                    </p>
                                </div>
                                <span
                                    class="money text-sm font-bold"
                                    :class="bid.status === 'active' ? 'text-brand-600' : 'text-slate-400 line-through'"
                                >{{ bid.amount_formatted }}</span>
                                <div v-if="bid.can_accept || bid.can_reject || bid.can_cancel" class="flex w-full gap-2 sm:w-auto">
                                    <button v-if="bid.can_accept" type="button" class="btn-primary btn-sm" :disabled="busy" @click="acceptBid(bid.id)">
                                        Accept
                                    </button>
                                    <button v-if="bid.can_reject" type="button" class="btn-outline btn-sm" :disabled="busy" @click="rejectBid(bid.id)">
                                        Reject
                                    </button>
                                    <button v-if="bid.can_cancel" type="button" class="btn-ghost btn-sm" :disabled="busy" @click="cancelBid(bid.id)">
                                        Withdraw
                                    </button>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- Seller -->
                    <div class="card-p">
                        <h2 class="mb-3 font-display text-lg font-bold text-slate-900">Seller</h2>
                        <div class="flex items-center gap-3">
                            <span class="grid h-14 w-14 flex-shrink-0 place-items-center rounded-2xl bg-brand-50 text-2xl font-bold text-brand-700">
                                {{ asset.seller.initial }}
                            </span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <Link v-if="asset.seller.profile_url" :href="asset.seller.profile_url" class="font-semibold text-slate-900 hover:text-brand-700">
                                        {{ asset.seller.name }}
                                    </Link>
                                    <span v-else class="font-semibold text-slate-900">{{ asset.seller.name }}</span>
                                    <span v-if="asset.seller.is_verified_seller" class="badge-mint">✓ Verified</span>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">Member since {{ asset.seller.member_since }}</p>
                                <p v-if="asset.seller.bio" class="mt-1 text-sm text-slate-500">{{ asset.seller.bio }}</p>
                            </div>
                            <div class="ms-auto flex flex-shrink-0 flex-col gap-2">
                                <button v-if="canContact" type="button" class="btn-outline btn-sm" :disabled="busy" @click="contactSeller">
                                    Contact Seller
                                </button>
                                <Link
                                    v-if="asset.seller.profile_url"
                                    :href="asset.seller.profile_url"
                                    class="btn-ghost btn-sm"
                                >
                                    View profile
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Buyer protection -->
                    <div class="card-p">
                        <h2 class="mb-2 font-display text-lg font-bold text-slate-900">Buyer Protection</h2>
                        <ul class="flex flex-col gap-2 text-sm text-slate-600">
                            <li v-for="point in [
                                '72-hour protection window after payment',
                                'Verified seller required to list',
                                'Dispute resolution available',
                                'Funds held until delivery confirmed',
                            ]" :key="point" class="flex gap-2">
                                <span class="flex-shrink-0 text-mint-600">✓</span>{{ point }}
                            </li>
                        </ul>
                        <Link :href="route('legal', 'buyer-protection')" class="mt-3 inline-block text-xs font-medium text-brand-600 hover:text-brand-700">
                            Read buyer protection policy →
                        </Link>
                    </div>

                    <!-- Buyer reviews — aggregates computed server-side. -->
                    <div v-if="reviews.count > 0" class="card-p">
                        <div class="mb-3 flex items-center justify-between gap-2 border-b border-slate-100 pb-3">
                            <h2 class="font-display text-lg font-bold text-slate-900">Buyer reviews</h2>
                            <div class="flex items-center gap-2">
                                <span class="text-amber-500">{{ '★'.repeat(Math.round(reviews.average ?? 0)) }}</span>
                                <span class="font-semibold text-slate-900">{{ reviews.average }}</span>
                                <span class="text-sm text-slate-500">
                                    ({{ reviews.count }} {{ reviews.count === 1 ? 'review' : 'reviews' }})
                                </span>
                            </div>
                        </div>

                        <div class="flex flex-col gap-3">
                            <div v-for="rv in reviews.items" :key="rv.id" class="flex gap-3">
                                <div
                                    class="grid h-9 w-9 flex-shrink-0 place-items-center rounded-full bg-brand-50 text-sm font-bold text-brand-600"
                                >
                                    {{ rv.reviewer_initial }}
                                </div>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm font-semibold text-slate-900">{{ rv.reviewer_name }}</span>
                                        <span class="text-xs text-amber-500">{{ '★'.repeat(rv.rating) }}</span>
                                        <span class="text-xs text-slate-500">{{ rv.at }}</span>
                                    </div>
                                    <p v-if="rv.comment" class="mt-1 whitespace-pre-line text-sm text-slate-700">
                                        {{ rv.comment }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Related -->
                    <div v-if="related.length">
                        <h2 class="mb-3 font-display text-lg font-bold text-slate-900">Similar listings</h2>
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                            <Link
                                v-for="rel in related"
                                :key="rel.id"
                                :href="route('marketplace.show', rel.slug)"
                                class="card group overflow-hidden transition hover:border-brand-500"
                            >
                                <div class="aspect-[16/10] overflow-hidden bg-gradient-to-br from-brand-50 to-mint-50">
                                    <img
                                        v-if="rel.cover_image_url"
                                        :src="rel.cover_image_url"
                                        :alt="rel.title"
                                        loading="lazy"
                                        class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                    />
                                    <div v-else class="grid h-full w-full place-items-center text-3xl">
                                        {{ rel.category.icon ?? '🧩' }}
                                    </div>
                                </div>
                                <div class="p-3">
                                    <p class="truncate text-xs font-semibold text-slate-900">{{ rel.title }}</p>
                                    <span class="money mt-1 block text-sm font-bold text-brand-600">{{ rel.price_formatted }}</span>
                                </div>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Right: purchase panel -->
                <aside class="mt-6 lg:mt-0">
                    <div class="card-p sticky top-20 flex flex-col gap-4">
                        <div>
                            <p class="mb-1 text-xs text-slate-500">Asking Price</p>
                            <span class="money block text-3xl font-bold text-slate-900">{{ asset.price_formatted }}</span>
                            <p class="mt-1 text-xs text-slate-500">
                                <span v-if="asset.is_sold_out" class="font-medium text-rose-600">Sold out</span>
                                <template v-else>{{ stockLine }}</template>
                            </p>
                        </div>

                        <!-- Current top bid — single-item listings only -->
                        <div v-if="asset.inventory_type === 'single' && asset.top_bid_formatted" class="rounded-xl bg-brand-50 px-3 py-2">
                            <p class="text-xs text-brand-700">Current Top Bid</p>
                            <span class="money block text-xl font-bold text-brand-800">{{ asset.top_bid_formatted }}</span>
                        </div>

                        <!-- Bid accepted: off the market, but not sold until it is paid for -->
                        <div v-if="acceptedBid" class="rounded-xl bg-amber-50 p-3 text-sm">
                            <p class="font-semibold text-amber-800">Bid Accepted — awaiting payment</p>
                            <p class="mt-1 text-amber-700">
                                {{ acceptedBid.amount_formatted }} ·
                                {{ acceptedBid.is_mine ? 'You won this bid' : acceptedBid.buyer_name }}
                            </p>
                            <Link v-if="acceptedBid.pay_url" :href="acceptedBid.pay_url" class="btn-primary btn-sm mt-2 w-full">
                                Pay Now — {{ acceptedBid.amount_formatted }} →
                            </Link>
                        </div>

                        <div class="flex items-center gap-2 rounded-xl bg-mint-50 px-3 py-2 text-xs text-mint-800">
                            <span aria-hidden="true">🛡</span><span>72-hour buyer protection on every order.</span>
                        </div>

                        <template v-if="user">
                            <div v-if="canManage" class="rounded-xl bg-slate-50 px-3 py-3 text-center text-sm text-slate-600">
                                <p class="font-medium">This is your listing.</p>
                                <Link v-if="manageUrl" :href="manageUrl" class="text-xs font-medium text-brand-600 hover:text-brand-700">
                                    Manage listing →
                                </Link>
                            </div>
                            <template v-else>
                                <!-- Buy Now: available on all three inventory types, and
                                     disabled once a bid has been accepted. -->
                                <button v-if="asset.is_sold_out" type="button" disabled class="btn-outline w-full">Sold Out</button>
                                <button v-else-if="asset.has_accepted_bid" type="button" disabled class="btn-outline w-full">
                                    Buy Now unavailable — bid accepted
                                </button>
                                <button v-else-if="!asset.is_purchasable" type="button" disabled class="btn-outline w-full">Not available</button>
                                <Link v-else :href="asset.checkout_url" class="btn-primary w-full">
                                    Buy Now — {{ asset.price_formatted }} →
                                </Link>

                                <!-- New Bid: single-item listings only. Multiple and
                                     Unlimited never render it, and the server refuses
                                     a bid on them even if this markup is bypassed. -->
                                <template v-if="asset.inventory_type === 'single'">
                                    <button
                                        v-if="asset.has_accepted_bid"
                                        type="button"
                                        disabled
                                        class="btn-outline w-full"
                                    >Bidding closed — bid accepted</button>
                                    <template v-else-if="canBid">
                                        <button v-if="!bidsOpen" type="button" class="btn-outline w-full" @click="bidsOpen = true">
                                            New Bid
                                        </button>
                                        <form v-else class="flex flex-col gap-2 rounded-xl bg-slate-50 p-3" @submit.prevent="submitBid">
                                            <label :for="`bid-amount-${asset.id}`" class="text-xs font-medium text-slate-600">
                                                Your bid (৳) — must be above
                                                {{ asset.top_bid_formatted ?? asset.price_formatted }}
                                                <span v-if="!asset.top_bid_formatted" class="text-slate-400">is the asking price; any amount is allowed</span>
                                            </label>
                                            <input
                                                :id="`bid-amount-${asset.id}`"
                                                v-model="bidForm.amount_bdt"
                                                type="number"
                                                step="0.01"
                                                :min="asset.min_bid_bdt"
                                                class="input"
                                                required
                                            />
                                            <p v-if="bidForm.errors.amount_bdt" class="text-xs text-rose-600">{{ bidForm.errors.amount_bdt }}</p>
                                            <div class="flex gap-2">
                                                <button type="submit" class="btn-primary btn-sm flex-1" :disabled="bidForm.processing">
                                                    {{ bidForm.processing ? 'Placing…' : 'Place bid' }}
                                                </button>
                                                <button type="button" class="btn-ghost btn-sm" @click="bidsOpen = false">Cancel</button>
                                            </div>
                                        </form>
                                    </template>
                                    <button v-else type="button" disabled class="btn-outline w-full">Bidding unavailable</button>
                                </template>

                                <!-- Contact Seller — opens the buyer↔seller chat -->
                                <button v-if="canContact" type="button" class="btn-outline w-full" :disabled="busy" @click="contactSeller">
                                    Contact Seller
                                </button>
                            </template>

                            <button type="button" class="btn-ghost w-full" :disabled="savingFavorite" :aria-pressed="favorited" @click="toggleFavorite">
                                {{ favorited ? '★ Saved to favorites' : '☆ Save to favorites' }}
                            </button>
                        </template>
                        <template v-else>
                            <Link :href="route('login')" class="btn-primary w-full">Log in to buy</Link>
                            <Link v-if="asset.inventory_type === 'single'" :href="route('login')" class="btn-outline w-full">Log in to bid</Link>
                            <Link :href="route('login')" class="btn-outline w-full">Log in to contact seller</Link>
                            <Link :href="route('login')" class="btn-ghost w-full">☆ Save to favorites</Link>
                        </template>

                        <dl class="flex flex-col gap-1 border-t border-slate-200 pt-3 text-xs text-slate-500">
                            <div class="flex justify-between"><dt>Category</dt><dd>{{ asset.category.name }}</dd></div>
                            <div class="flex justify-between"><dt>Listing type</dt><dd>{{ asset.inventory_label }}</dd></div>
                            <div class="flex justify-between"><dt>Listed</dt><dd>{{ asset.listed_on }}</dd></div>
                            <div class="flex justify-between"><dt>Views</dt><dd>{{ asset.views_count.toLocaleString() }}</dd></div>
                            <div class="flex justify-between"><dt>Saved by</dt><dd>{{ asset.favorites_count.toLocaleString() }}</dd></div>
                        </dl>
                    </div>
                </aside>
            </div>
        </div>
    </PublicLayout>
</template>
