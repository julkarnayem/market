<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Category } from '@/types';

defineProps<{ category: Category }>();
</script>

<template>
    <Link
        :href="route('marketplace.index', { category: category.slug })"
        class="flex flex-col items-center gap-2 rounded-xl border border-slate-200 bg-white p-5 text-center
               transition duration-200 hover:-translate-y-0.5 hover:border-brand-500 hover:bg-brand-50
               hover:shadow-pop"
    >
        <span class="text-3xl leading-none">{{ category.icon ?? '🗂️' }}</span>
        <span class="text-sm font-semibold text-slate-900 group-hover:text-brand-700">{{ category.name }}</span>
        <span v-if="category.children_count > 0" class="text-xs text-slate-400">
            {{ category.children_count }} sub-categories
        </span>
    </Link>
</template>
