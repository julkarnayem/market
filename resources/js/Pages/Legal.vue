<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

defineProps<{
    page: { slug: string; title: string; body: string };
    lastUpdated: string;
}>();
</script>

<template>
    <Head :title="page.title" />

    <PublicLayout>
        <div class="mx-auto max-w-3xl px-3 py-8 sm:px-4">
            <Link :href="route('home')" class="text-sm font-medium text-brand-700 hover:text-brand-800">
                ← Home
            </Link>

            <h1 class="mt-2 font-display text-3xl font-bold text-slate-900">{{ page.title }}</h1>
            <p class="mt-1 text-sm text-slate-500">Last updated {{ lastUpdated }}</p>

            <!-- v-html is safe here: page.body is hardcoded in
                 PageController::pages(), never user input. Any future
                 user-supplied body must be sanitised server-side first. -->
            <!-- eslint-disable-next-line vue/no-v-html -->
            <div class="prose prose-slate mt-6 max-w-none" v-html="page.body"></div>
        </div>
    </PublicLayout>
</template>
