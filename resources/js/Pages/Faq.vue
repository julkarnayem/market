<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

interface FaqItem {
    question: string;
    answer: string;
}

const props = defineProps<{ faqs: FaqItem[] }>();

/** First item starts expanded, matching the previous Blade/Alpine behaviour. */
const open = ref<boolean[]>(props.faqs.map((_, i) => i === 0));

const toggle = (index: number) => (open.value[index] = !open.value[index]);
</script>

<template>
    <Head title="Frequently asked questions">
        <meta
            name="description"
            content="Answers about listing fees, buyer protection, seller payouts, cancellations and verification."
        />
    </Head>

    <PublicLayout>
        <div class="mx-auto max-w-3xl px-3 py-8 sm:px-4">
            <h1 class="font-display text-3xl font-bold text-slate-900">Frequently asked questions</h1>

            <div class="mt-6 flex flex-col gap-3">
                <div v-for="(faq, i) in faqs" :key="faq.question" class="card overflow-hidden">
                    <h2>
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-4 p-4 text-start font-medium
                                   text-slate-900 transition-colors hover:bg-slate-50"
                            :aria-expanded="open[i]"
                            :aria-controls="`faq-panel-${i}`"
                            @click="toggle(i)"
                        >
                            <span>{{ faq.question }}</span>
                            <svg
                                class="h-5 w-5 flex-shrink-0 text-slate-400 transition-transform duration-200"
                                :class="open[i] && 'rotate-45'"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14" />
                            </svg>
                        </button>
                    </h2>

                    <!-- 0fr -> 1fr animates to the content's natural height, no fixed max-height needed. -->
                    <div
                        :id="`faq-panel-${i}`"
                        role="region"
                        class="grid transition-all duration-200 ease-out"
                        :class="open[i] ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'"
                    >
                        <div class="overflow-hidden">
                            <p class="px-4 pb-4 text-sm text-slate-600">{{ faq.answer }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
