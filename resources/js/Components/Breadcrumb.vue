<script setup lang="ts">
/**
 * Vue port of resources/views/components/breadcrumb.blade.php. The last item is
 * always rendered as plain text (even if it carries a url), earlier items with
 * a url become Inertia links; everything else is inert text.
 */
import { Link } from '@inertiajs/vue3';

defineProps<{
    items: Array<{ label: string; url?: string | null }>;
}>();
</script>

<template>
    <nav aria-label="Breadcrumb" class="breadcrumb mb-3">
        <template v-for="(item, i) in items" :key="i">
            <span v-if="i > 0" class="breadcrumb-sep" aria-hidden="true">/</span>
            <Link v-if="item.url && i < items.length - 1" :href="item.url">{{ item.label }}</Link>
            <span v-else class="font-medium text-slate-900">{{ item.label }}</span>
        </template>
    </nav>
</template>
