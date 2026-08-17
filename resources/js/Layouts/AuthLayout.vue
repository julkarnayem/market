<script setup lang="ts">
import PublicLayout from '@/Layouts/PublicLayout.vue';

defineProps<{
    /** Page heading inside the card. */
    title: string;
    subtitle?: string;
}>();
</script>

<template>
    <PublicLayout>
        <div class="flex items-center justify-center px-3 py-12">
            <div class="w-full max-w-[26rem] rounded-2xl border border-slate-200 bg-white p-8 shadow-card">
                <!-- Optional step indicator for the multi-step register / reset flows -->
                <slot name="steps" />

                <div class="mb-6 text-center">
                    <h1 class="font-display text-2xl font-bold text-slate-900">{{ title }}</h1>
                    <p v-if="subtitle || $slots.subtitle" class="mt-1 text-sm text-slate-500">
                        <slot name="subtitle">{{ subtitle }}</slot>
                    </p>
                </div>

                <slot />
            </div>
        </div>
    </PublicLayout>
</template>
