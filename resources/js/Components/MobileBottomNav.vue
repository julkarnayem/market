<script setup lang="ts">
/**
 * Fixed five-slot bottom navigation, mobile only. Shared by PublicLayout and
 * DashboardLayout. Anything rendering this must reserve ~5rem of bottom space
 * on its own bottom-most element (see SiteFooter / DashboardLayout).
 */
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const user = computed(() => usePage().props.auth.user);
const isCurrent = (pattern: string) => route().current(pattern);
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden"
        aria-label="Mobile navigation"
    >
        <div class="grid h-16 grid-cols-5">
            <Link :href="route('home')" aria-label="Home"
                  class="flex flex-col items-center justify-center gap-0.5 text-[11px]"
                  :class="isCurrent('home') ? 'text-brand-700' : 'text-slate-500'">
                <span class="text-lg" aria-hidden="true">🏠</span>Home
            </Link>
            <Link :href="route('marketplace.index')" aria-label="Browse"
                  class="flex flex-col items-center justify-center gap-0.5 text-[11px]"
                  :class="isCurrent('marketplace.*') ? 'text-brand-700' : 'text-slate-500'">
                <span class="text-lg" aria-hidden="true">🧭</span>Browse
            </Link>

            <template v-if="user">
                <Link :href="route('dashboard.messages')" aria-label="Messages"
                      class="flex flex-col items-center justify-center gap-0.5 text-[11px]"
                      :class="isCurrent('dashboard.messages') ? 'text-brand-700' : 'text-slate-500'">
                    <span class="text-lg" aria-hidden="true">✉️</span>Messages
                </Link>
                <Link :href="route('dashboard.wallet')" aria-label="Wallet"
                      class="flex flex-col items-center justify-center gap-0.5 text-[11px]"
                      :class="isCurrent('dashboard.wallet') ? 'text-brand-700' : 'text-slate-500'">
                    <span class="text-lg" aria-hidden="true">👛</span>Wallet
                </Link>
                <Link :href="route('dashboard')" aria-label="Account"
                      class="flex flex-col items-center justify-center gap-0.5 text-[11px]"
                      :class="isCurrent('dashboard') ? 'text-brand-700' : 'text-slate-500'">
                    <span class="text-lg" aria-hidden="true">▦</span>Account
                </Link>
            </template>
            <template v-else>
                <Link :href="route('faq')" aria-label="Help"
                      class="flex flex-col items-center justify-center gap-0.5 text-[11px]"
                      :class="isCurrent('faq') ? 'text-brand-700' : 'text-slate-500'">
                    <span class="text-lg" aria-hidden="true">❔</span>Help
                </Link>
                <Link :href="route('login')" aria-label="Log in"
                      class="flex flex-col items-center justify-center gap-0.5 text-[11px] text-slate-500">
                    <span class="text-lg" aria-hidden="true">→</span>Log in
                </Link>
                <Link :href="route('register')" aria-label="Sign up"
                      class="flex flex-col items-center justify-center gap-0.5 text-[11px] text-brand-700">
                    <span class="text-lg" aria-hidden="true">＋</span>Sign up
                </Link>
            </template>
        </div>
    </nav>
</template>
