<script setup lang="ts">
/**
 * Layout for every public-facing page. All the chrome lives in shared
 * components so DashboardLayout can reuse the header and mobile nav without
 * inheriting the footer.
 */
import SiteHeader from '@/Components/SiteHeader.vue';
import SiteFooter from '@/Components/SiteFooter.vue';
import MobileBottomNav from '@/Components/MobileBottomNav.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
</script>

<template>
    <!-- min-h-screen, not min-h-full: `min-height:100%` resolves against <body>,
         whose height is auto, so it collapses to the content height and the
         footer stops sticking to the bottom on short pages. -->
    <div class="flex min-h-screen flex-col">
        <SiteHeader />

        <FlashMessages />

        <main class="w-full flex-grow pb-20 md:pb-0">
            <slot />
        </main>

        <SiteFooter />

        <MobileBottomNav />
    </div>
</template>
