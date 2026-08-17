<script setup lang="ts">
/**
 * Live HH:MM:SS countdown. Vue port of the inline Alpine timer the offers
 * table used: seed from server-computed remaining seconds, tick down once a
 * second, and stop at zero. Each instance owns its interval and clears it on
 * unmount so a paginated table of timers leaks nothing.
 */
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps<{ seconds: number }>();

const secs = ref(Math.max(0, props.seconds));
let handle: ReturnType<typeof setInterval> | undefined;

const pad = (n: number) => n.toString().padStart(2, '0');
const display = computed(
    () =>
        `${pad(Math.floor(secs.value / 3600))}:${pad(Math.floor((secs.value % 3600) / 60))}:${pad(secs.value % 60)}`,
);

onMounted(() => {
    handle = setInterval(() => {
        if (secs.value > 0) secs.value--;
    }, 1000);
});
onBeforeUnmount(() => clearInterval(handle));
</script>

<template>
    <span class="font-mono text-amber-600">{{ display }}</span>
</template>
