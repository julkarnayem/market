<script setup lang="ts">
/**
 * Layout for the admin area. Dark sidebar rail (AdminSidebar) + sticky header.
 *
 * Ported from resources/views/components/layouts/admin.blade.php, restyled from
 * Bootstrap to Tailwind. The mobile drawer is a Vue ref rather than Alpine
 * x-data. Since most admin destinations are still Blade, navigation between
 * them is full-page (see AdminSidebar); this layout only mounts on the admin
 * pages already migrated to Inertia.
 */
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminSidebar from '@/Components/AdminSidebar.vue';
import FlashMessages from '@/Components/FlashMessages.vue';

const props = defineProps<{
    /** Document title, and the h1 unless `heading` overrides it. */
    title: string;
    heading?: string;
}>();

const sidebarOpen = ref(false);
</script>

<template>
    <Head :title="props.title">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <div class="min-h-screen bg-slate-100 lg:grid lg:grid-cols-[16rem_1fr]">
        <!-- Desktop sidebar -->
        <aside class="hidden bg-slate-900 p-3 text-slate-300 lg:block">
            <div class="sticky top-3">
                <AdminSidebar />
            </div>
        </aside>

        <!-- Mobile drawer -->
        <div v-if="sidebarOpen" class="fixed inset-0 z-50 lg:hidden">
            <div class="absolute inset-0 bg-slate-900/50" @click="sidebarOpen = false"></div>
            <aside
                class="absolute inset-y-0 left-0 w-72 overflow-y-auto bg-slate-900 p-3 text-slate-300"
                @click="sidebarOpen = false"
            >
                <AdminSidebar />
            </aside>
        </div>

        <div class="flex min-h-screen flex-col">
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-white">
                <div class="flex h-16 items-center gap-3 px-3 sm:px-4">
                    <button
                        type="button"
                        class="btn-ghost btn-sm lg:hidden"
                        aria-label="Open menu"
                        @click="sidebarOpen = true"
                    >
                        ☰
                    </button>
                    <h1 class="font-display text-lg font-bold text-slate-900">{{ props.heading ?? props.title }}</h1>
                    <div class="ml-auto flex items-center gap-2">
                        <slot name="actions" />
                        <a href="/" class="btn-outline btn-sm">View site</a>
                        <Link :href="route('logout')" method="post" as="button" class="btn-ghost btn-sm text-rose-600">
                            Log out
                        </Link>
                    </div>
                </div>
            </header>

            <FlashMessages />

            <main class="flex-1 p-3 sm:p-4">
                <slot />
            </main>
        </div>
    </div>
</template>
