<script setup lang="ts">
/**
 * Three-dot progress indicator for the register and password-reset flows.
 * `current` is 1-based; anything before it renders as done.
 */
const props = defineProps<{ current: 1 | 2 | 3; steps?: number }>();

const total = props.steps ?? 3;
const state = (i: number) => (i < props.current ? 'done' : i === props.current ? 'active' : 'inactive');
</script>

<template>
    <div class="mb-6 flex items-center gap-2" role="group" :aria-label="`Step ${current} of ${total}`">
        <template v-for="i in total" :key="i">
            <div
                class="grid h-8 w-8 place-items-center rounded-full text-xs font-bold"
                :class="{
                    'bg-brand-500 text-white': state(i) === 'active',
                    'bg-brand-100 text-brand-800': state(i) === 'done',
                    'bg-slate-100 text-slate-400': state(i) === 'inactive',
                }"
                :aria-current="state(i) === 'active' ? 'step' : undefined"
            >
                {{ state(i) === 'done' ? '✓' : i }}
            </div>
            <div
                v-if="i < total"
                class="h-0.5 flex-1 rounded-full"
                :class="i < current ? 'bg-brand-500' : 'bg-slate-200'"
            ></div>
        </template>
    </div>
</template>
