<script setup lang="ts">
/**
 * Layout for the authenticated dashboard. Reuses the site header and mobile
 * bottom nav, drops the footer, and adds the section rail.
 *
 * The Blade original hid the sidebar entirely below lg (`d-none d-lg-block`),
 * which left 10 of the 15 dashboard sections unreachable on a phone — the
 * fixed bottom nav only has five slots. Below lg the same items now render as
 * a horizontal scroller instead.
 */
import { Head } from '@inertiajs/vue3';
import SiteHeader from '@/Components/SiteHeader.vue';
import MobileBottomNav from '@/Components/MobileBottomNav.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import DashboardSidebar from '@/Components/DashboardSidebar.vue';

const props = defineProps<{
    /** Document title, and the h1 unless `heading` overrides it. */
    title: string;
    heading?: string;
}>();
</script>

<template>
    <Head :title="props.title">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <div class="flex min-h-screen flex-col bg-slate-50">
        <SiteHeader />

        <FlashMessages />

        <!-- pb-24 clears the fixed mobile bottom nav; there is no footer here. -->
        <div class="mx-auto w-full max-w-7xl flex-grow px-3 py-4 pb-24 sm:px-4 md:pb-8 lg:px-4">
            <div class="lg:grid lg:grid-cols-[15rem_1fr] lg:gap-5">
                <aside class="hidden lg:block">
                    <div class="sticky top-20">
                        <DashboardSidebar />
                    </div>
                </aside>

                <div class="min-w-0">
                    <!-- Mobile section nav: same links, horizontally scrollable. -->
                    <div class="-mx-3 mb-3 overflow-x-auto px-3 lg:hidden">
                        <DashboardSidebar orientation="horizontal" />
                    </div>

                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h1 class="font-display text-2xl font-bold text-slate-900">{{ props.heading ?? props.title }}</h1>
                        <div v-if="$slots.actions" class="flex-shrink-0">
                            <slot name="actions" />
                        </div>
                    </div>

                    <slot />
                </div>
            </div>
        </div>

        <MobileBottomNav />
    </div>
</template>
