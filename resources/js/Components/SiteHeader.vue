<script setup lang="ts">
/**
 * The site header: logo, primary nav, search, account actions and the mobile
 * drop-down. Shared by PublicLayout and DashboardLayout — extracted from
 * PublicLayout when the dashboard needed the same chrome without the footer.
 */
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useClickOutside } from '@/composables/useClickOutside';

const page = usePage();
const user = computed(() => page.props.auth.user);
const appName = computed(() => page.props.appName ?? 'Marketplace');
const initial = computed(() => (appName.value.charAt(0) || 'M').toUpperCase());

/** Desktop + mobile search. Kept as an Inertia visit so navigation stays SPA. */
const search = ref((route().params?.q as string) ?? '');
function submitSearch() {
    router.get(route('marketplace.index'), { q: search.value || undefined }, {
        preserveState: true,
        replace: true,
    });
}

const userMenuOpen = ref(false);
const userMenuRef = ref<HTMLElement | null>(null);
useClickOutside(userMenuRef, () => (userMenuOpen.value = false));

const mobileMenuOpen = ref(false);
const mobileMenuRef = ref<HTMLElement | null>(null);
useClickOutside(mobileMenuRef, () => (mobileMenuOpen.value = false));

/** Ziggy-backed active-route helper for nav highlighting. */
const isCurrent = (pattern: string) => route().current(pattern);

const navLinks = [
    { label: 'Home', href: () => route('home'), match: 'home' },
    { label: 'Marketplace', href: () => route('marketplace.index'), match: 'marketplace.*' },
    { label: 'Help', href: () => route('faq'), match: 'faq' },
    { label: 'Contact', href: () => route('contact'), match: 'contact' },
];
</script>

<template>
    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur">
        <div class="mx-auto max-w-7xl px-3 sm:px-4 lg:px-4">
            <div class="flex h-16 items-center gap-3">
                <!-- Logo -->
                <Link :href="route('home')" class="flex flex-shrink-0 items-center gap-2">
                    <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-500 font-display font-bold text-white">
                        {{ initial }}
                    </span>
                    <span class="hidden font-display text-lg font-bold text-slate-900 sm:block">{{ appName }}</span>
                </Link>

                <!-- Desktop nav -->
                <nav class="ms-2 hidden items-center gap-1 md:flex">
                    <Link
                        v-for="link in navLinks"
                        :key="link.label"
                        :href="link.href()"
                        class="rounded-xl px-2 py-2 text-sm font-medium transition-colors"
                        :class="isCurrent(link.match)
                            ? 'bg-brand-50 text-brand-700'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                    >
                        {{ link.label }}
                    </Link>
                </nav>

                <!-- Desktop search -->
                <form class="mx-3 hidden max-w-sm flex-grow lg:flex" @submit.prevent="submitSearch">
                    <div class="relative w-full">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            v-model="search"
                            name="q"
                            type="search"
                            placeholder="Search assets…"
                            aria-label="Search assets"
                            class="w-full rounded-xl border border-slate-300 bg-slate-50 py-2 pe-3 ps-9 text-sm
                                   focus:border-brand-500 focus:bg-white focus:ring-brand-500"
                        />
                    </div>
                </form>

                <div class="ms-auto flex items-center gap-2">
                    <template v-if="user">
                        <Link :href="route('dashboard.messages')" title="Messages" aria-label="Messages"
                              class="hidden rounded-xl p-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 sm:flex">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </Link>
                        <Link :href="route('dashboard.notifications')" title="Notifications" aria-label="Notifications"
                              class="hidden rounded-xl p-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 sm:flex">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                        </Link>

                        <Link
                            v-if="user.can_sell"
                            :href="route('dashboard.listings.create')"
                            class="hidden items-center gap-1 rounded-xl bg-brand-600 px-3 py-2 text-sm font-semibold
                                   text-white transition-colors hover:bg-brand-700 sm:inline-flex"
                        >
                            + Sell asset
                        </Link>
                        <Link
                            v-else
                            :href="route('dashboard.verification')"
                            class="hidden rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold
                                   text-slate-900 transition-colors hover:bg-slate-50 sm:inline-flex"
                        >
                            Become a seller
                        </Link>

                        <!-- User menu -->
                        <div ref="userMenuRef" class="relative">
                            <button
                                type="button"
                                class="flex items-center gap-2 rounded-xl p-1 hover:bg-slate-100"
                                :aria-expanded="userMenuOpen"
                                aria-haspopup="true"
                                aria-label="Account menu"
                                @click="userMenuOpen = !userMenuOpen"
                            >
                                <img
                                    v-if="user.avatar"
                                    :src="user.avatar"
                                    :alt="user.name"
                                    class="h-8 w-8 rounded-full object-cover"
                                />
                                <span
                                    v-else
                                    class="grid h-8 w-8 place-items-center rounded-full bg-brand-500 text-sm font-bold text-white"
                                >
                                    {{ user.name.charAt(0).toUpperCase() }}
                                </span>
                            </button>

                            <Transition
                                enter-active-class="transition ease-out duration-150"
                                enter-from-class="opacity-0 scale-95"
                                enter-to-class="opacity-100 scale-100"
                                leave-active-class="transition ease-in duration-100"
                                leave-from-class="opacity-100 scale-100"
                                leave-to-class="opacity-0 scale-95"
                            >
                                <div
                                    v-show="userMenuOpen"
                                    class="absolute end-0 mt-2 w-56 origin-top-right rounded-2xl border border-slate-200
                                           bg-white p-1 shadow-pop"
                                >
                                    <div class="mb-1 border-b border-slate-200 px-2 py-2">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ user.name }}</p>
                                        <p class="truncate text-xs text-slate-500">{{ user.email }}</p>
                                    </div>
                                    <Link :href="route('dashboard')" class="flex items-center gap-2 rounded-xl px-2 py-2 text-sm text-slate-900 hover:bg-slate-100">Dashboard</Link>
                                    <Link :href="route('dashboard.wallet')" class="flex items-center gap-2 rounded-xl px-2 py-2 text-sm text-slate-900 hover:bg-slate-100">Wallet</Link>
                                    <Link :href="route('dashboard.orders')" class="flex items-center gap-2 rounded-xl px-2 py-2 text-sm text-slate-900 hover:bg-slate-100">Orders</Link>
                                    <Link :href="route('dashboard.profile')" class="flex items-center gap-2 rounded-xl px-2 py-2 text-sm text-slate-900 hover:bg-slate-100">Profile</Link>
                                    <Link
                                        v-if="user.is_admin"
                                        :href="route('admin.dashboard')"
                                        class="flex items-center gap-2 rounded-xl px-2 py-2 text-sm font-medium text-brand-600 hover:bg-brand-50"
                                    >
                                        Admin panel
                                    </Link>
                                    <div class="mt-1 border-t border-slate-200 pt-1">
                                        <Link
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                            type="button"
                                            class="flex w-full items-center gap-2 rounded-xl px-2 py-2 text-start text-sm text-rose-600 hover:bg-rose-50"
                                        >
                                            Log out
                                        </Link>
                                    </div>
                                </div>
                            </Transition>
                        </div>
                    </template>

                    <template v-else>
                        <Link :href="route('login')" class="px-3 py-2 text-sm font-medium text-slate-900 hover:text-brand-700">Log in</Link>
                        <Link :href="route('register')" class="rounded-xl bg-brand-600 px-3 py-2 text-sm font-semibold text-white transition-colors hover:bg-brand-700">
                            Get Started
                        </Link>
                    </template>

                    <!-- Mobile menu toggle -->
                    <button
                        type="button"
                        class="rounded-xl p-2 text-slate-600 hover:bg-slate-100 md:hidden"
                        :aria-expanded="mobileMenuOpen"
                        aria-label="Menu"
                        @click="mobileMenuOpen = !mobileMenuOpen"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Mobile menu -->
            <div v-show="mobileMenuOpen" ref="mobileMenuRef" class="flex flex-col gap-1 border-t border-slate-200 py-2 md:hidden">
                <form class="mb-2" @submit.prevent="submitSearch(); mobileMenuOpen = false">
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            v-model="search"
                            name="q"
                            type="search"
                            placeholder="Search assets…"
                            aria-label="Search assets"
                            class="w-full rounded-xl border border-slate-300 bg-slate-50 py-2 pe-3 ps-9 text-sm focus:border-brand-500 focus:bg-white focus:ring-brand-500"
                        />
                    </div>
                </form>

                <Link
                    v-for="link in navLinks"
                    :key="link.label"
                    :href="link.href()"
                    class="flex items-center gap-2 rounded-xl px-2 py-2 text-sm font-medium text-slate-900 hover:bg-slate-100"
                    @click="mobileMenuOpen = false"
                >
                    {{ link.label }}
                </Link>

                <template v-if="user">
                    <Link
                        v-if="user.can_sell"
                        :href="route('dashboard.listings.create')"
                        class="mt-2 flex items-center gap-2 rounded-xl bg-brand-600 px-2 py-2 text-sm font-semibold text-white"
                    >
                        + Sell Asset
                    </Link>
                    <Link
                        v-else
                        :href="route('dashboard.verification')"
                        class="mt-2 flex items-center gap-2 rounded-xl border border-slate-300 px-2 py-2 text-sm font-semibold text-slate-900"
                    >
                        Become a Seller
                    </Link>
                </template>
                <div v-else class="mt-2 grid grid-cols-2 gap-2 border-t border-slate-200 pt-2">
                    <Link :href="route('login')" class="rounded-xl border border-slate-300 px-3 py-2 text-center text-sm font-medium text-slate-900">Log in</Link>
                    <Link :href="route('register')" class="rounded-xl bg-brand-600 px-3 py-2 text-center text-sm font-semibold text-white">Get Started</Link>
                </div>
            </div>
        </div>
    </header>
</template>
