<script setup lang="ts">
import { ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

const props = defineProps<{
    order: { id: number; asset_title: string; seller_name: string };
}>();

/** Hover preview is transient; the committed value lives on the form. */
const hover = ref(0);
const form = useForm<{ rating: number; comment: string }>({ rating: 0, comment: '' });

const LABELS = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];

function submit() {
    form.post(route('dashboard.orders.review.store', props.order.id));
}
</script>

<template>
    <DashboardLayout title="Leave a Review" heading="Leave a Review">
        <div class="max-w-lg">
            <div class="card-p">
                <div class="mb-3 flex items-center gap-3 border-b border-slate-100 pb-3">
                    <div class="grid h-12 w-12 flex-shrink-0 place-items-center rounded-lg bg-mint-50 text-3xl">
                        ⭐
                    </div>
                    <div>
                        <p class="font-semibold text-slate-900">{{ order.asset_title }}</p>
                        <p class="text-sm text-slate-500">Seller: {{ order.seller_name }}</p>
                    </div>
                </div>

                <form novalidate @submit.prevent="submit">
                    <!-- Star rating -->
                    <div class="mb-3">
                        <label class="mb-2 block text-sm font-medium text-slate-900">Your Rating</label>
                        <div class="flex gap-2">
                            <button
                                v-for="i in 5"
                                :key="i"
                                type="button"
                                class="text-4xl text-amber-500 transition-opacity"
                                :class="(hover || form.rating) >= i ? 'opacity-100' : 'opacity-30'"
                                @click="form.rating = i"
                                @mouseenter="hover = i"
                                @mouseleave="hover = 0"
                            >
                                ★
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">{{ LABELS[form.rating] || 'Click to rate' }}</p>
                        <p v-if="form.errors.rating" class="field-error">{{ form.errors.rating }}</p>
                    </div>

                    <!-- Comment -->
                    <div class="mb-3">
                        <label class="mb-1 block text-sm font-medium text-slate-900">
                            Comment <span class="font-normal text-slate-500">(optional)</span>
                        </label>
                        <textarea
                            v-model="form.comment"
                            rows="4"
                            class="textarea"
                            maxlength="1000"
                            placeholder="Share your experience with this seller and asset…"
                        ></textarea>
                        <p v-if="form.errors.comment" class="field-error">{{ form.errors.comment }}</p>
                    </div>

                    <div class="flex gap-3">
                        <button
                            type="submit"
                            class="btn-success flex-1"
                            :disabled="form.rating === 0 || form.processing"
                        >
                            {{ form.processing ? 'Submitting…' : 'Submit Review' }}
                        </button>
                        <Link :href="route('dashboard.orders.show', order.id)" class="btn-outline">Cancel</Link>
                    </div>
                </form>
            </div>
        </div>
    </DashboardLayout>
</template>
