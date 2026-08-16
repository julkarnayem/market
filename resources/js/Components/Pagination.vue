<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PaginationLink } from '@/types';

const props = defineProps<{
    links: PaginationLink[];
    total: number;
}>();

/**
 * Laravel emits "&laquo; Previous" / "Next &raquo;" as HTML entities in the
 * label, plus "..." separators. Decode the arrows and mark the gaps so the
 * template can render them as inert spans rather than links.
 */
function label(raw: string): string {
    return raw
        .replace('&laquo;', '‹')
        .replace('&raquo;', '›')
        .replace(/&hellip;|\.\.\./g, '…')
        .trim();
}

const isGap = (link: PaginationLink) => link.url === null && label(link.label) === '…';
</script>

<template>
    <nav v-if="links.length > 3" class="flex flex-wrap items-center justify-center gap-1" aria-label="Pagination">
        <template v-for="(link, i) in links" :key="i">
            <span
                v-if="isGap(link) || link.url === null"
                class="px-3 py-2 text-sm text-slate-400"
                aria-hidden="true"
            >
                {{ label(link.label) }}
            </span>
            <Link
                v-else
                :href="link.url"
                preserve-scroll
                :aria-current="link.active ? 'page' : undefined"
                class="min-w-9 rounded-xl px-3 py-2 text-center text-sm font-medium transition"
                :class="link.active
                    ? 'bg-brand-600 text-white'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
            >
                {{ label(link.label) }}
            </Link>
        </template>
    </nav>
</template>
