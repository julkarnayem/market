<script setup lang="ts">
/**
 * Dashboard section navigation. Desktop renders it as a sticky rail; below lg
 * DashboardLayout renders the same items as a horizontal scroller, since the
 * fixed bottom nav only has room for five slots.
 */
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

type Item = { label: string; icon: string; route: string; match: string[] };

/**
 * `match` is explicit rather than a `dashboard.listings.*` wildcard so that
 * "Create Listing" and "My Listings" cannot both light up at once.
 */
const items: Item[] = [
    { label: 'Overview', icon: '▦', route: 'dashboard', match: ['dashboard'] },
    { label: 'My Purchases', icon: '🛍️', route: 'dashboard.purchases', match: ['dashboard.purchases'] },
    { label: 'My Orders', icon: '📦', route: 'dashboard.orders', match: ['dashboard.orders', 'dashboard.orders.*'] },
    { label: 'Disputes', icon: '⚑', route: 'dashboard.disputes', match: ['dashboard.disputes', 'dashboard.disputes.*'] },
    { label: 'My Listings', icon: '🏷️', route: 'dashboard.listings', match: ['dashboard.listings', 'dashboard.listings.show', 'dashboard.listings.edit'] },
    { label: 'Create Listing', icon: '＋', route: 'dashboard.listings.create', match: ['dashboard.listings.create'] },
    // No "Offers" entry: custom offers live inside the chat thread, so Messages
    // is where they are read and answered.
    { label: 'Messages', icon: '✉️', route: 'dashboard.messages', match: ['dashboard.messages'] },
    { label: 'Notifications', icon: '🔔', route: 'dashboard.notifications', match: ['dashboard.notifications'] },
    { label: 'Wallet', icon: '👛', route: 'dashboard.wallet', match: ['dashboard.wallet'] },
    { label: 'Withdrawals', icon: '🏦', route: 'dashboard.withdrawals', match: ['dashboard.withdrawals'] },
    { label: 'Verification', icon: '✅', route: 'dashboard.verification', match: ['dashboard.verification'] },
    { label: 'Favorites', icon: '★', route: 'dashboard.favorites', match: ['dashboard.favorites'] },
    { label: 'Support', icon: '🎧', route: 'dashboard.tickets', match: ['dashboard.tickets', 'dashboard.tickets.*'] },
    { label: 'Profile', icon: '👤', route: 'dashboard.profile', match: ['dashboard.profile'] },
    { label: 'Security', icon: '🔒', route: 'dashboard.security', match: ['dashboard.security'] },
];

const isActive = (item: Item) => item.match.some((p) => route().current(p));

/**
 * An explicit prop rather than a `class` override: `space-x-1` and `space-y-0.5`
 * would collide in one class attribute and resolve by stylesheet order.
 */
const props = withDefaults(defineProps<{ orientation?: 'vertical' | 'horizontal' }>(), {
    orientation: 'vertical',
});

const navClass = computed(() =>
    props.orientation === 'horizontal'
        ? 'flex w-max flex-row space-x-1 whitespace-nowrap'
        : 'space-y-0.5',
);
</script>

<template>
    <nav class="card p-2" :class="navClass" aria-label="Dashboard sections">
        <Link
            v-for="item in items"
            :key="item.route"
            :href="route(item.route)"
            class="nav-link"
            :class="isActive(item) && 'nav-link-active'"
            :aria-current="isActive(item) ? 'page' : undefined"
        >
            <span class="w-5 flex-shrink-0 text-center" aria-hidden="true">{{ item.icon }}</span>
            <span>{{ item.label }}</span>
        </Link>
    </nav>
</template>
