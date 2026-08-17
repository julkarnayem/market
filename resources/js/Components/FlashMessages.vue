<script setup lang="ts">
/**
 * Renders the shared `flash` prop from HandleInertiaRequests. Values are
 * printed verbatim, so controllers must flash human-readable sentences rather
 * than sentinels like Breeze's 'verification-link-sent'.
 */
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const flash = computed(() => usePage().props.flash);
</script>

<template>
    <div v-if="flash.success || flash.status || flash.error" class="mx-auto w-full max-w-7xl px-3 pt-3 sm:px-4 lg:px-4">
        <div v-if="flash.success" class="alert-success" role="status">{{ flash.success }}</div>
        <div v-if="flash.status" class="alert-info" role="status">{{ flash.status }}</div>
        <div v-if="flash.error" class="alert-error" role="alert">{{ flash.error }}</div>
    </div>
</template>
