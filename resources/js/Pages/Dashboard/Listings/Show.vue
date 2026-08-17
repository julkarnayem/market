<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

interface AttributeRow { id: number; label: string | null; value: string | null }
interface EditRow { id: number; status: string; note: string | null; at: string }
interface ImageRow { id: number; url: string }

interface ListingData {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    status: string;
    is_featured: boolean;
    category_name: string | null;
    price_formatted: string;
    quantity: number;
    available_quantity: number;
    sold_quantity: number;
    created_date: string;
    updated_human: string;
    rejection_reason: string | null;
    changes_requested_note: string | null;
    cover_url: string | null;
    images: ImageRow[];
    attributes: AttributeRow[];
    edits: EditRow[];
}

const props = defineProps<{
    listing: ListingData;
    canUpdate: boolean;
    canTogglePause: boolean;
}>();

const pauseForm = useForm({});
function togglePause() {
    pauseForm.post(route('dashboard.listings.pause', props.listing.id), { preserveScroll: true });
}

const submitForm = useForm({});
function submitDraft() {
    submitForm.post(route('dashboard.listings.submit', props.listing.id));
}
</script>

<template>
    <DashboardLayout :title="listing.title">
        <Breadcrumb
            :items="[
                { label: 'My Listings', url: route('dashboard.listings') },
                { label: listing.title },
            ]"
        />

        <div class="items-start gap-4 lg:grid lg:grid-cols-[1fr_18rem]">
            <!-- Main -->
            <div class="flex flex-col gap-3">
                <!-- Images -->
                <div v-if="listing.images.length" class="card-p">
                    <img
                        :src="listing.cover_url ?? listing.images[0].url"
                        class="aspect-video w-full rounded-lg object-cover"
                        alt=""
                    />
                    <div v-if="listing.images.length > 1" class="mt-2 flex gap-2 overflow-x-auto">
                        <img
                            v-for="img in listing.images"
                            :key="img.id"
                            :src="img.url"
                            class="h-16 w-16 flex-shrink-0 rounded-lg object-cover"
                            alt=""
                        />
                    </div>
                </div>

                <!-- Detail -->
                <div class="card-p">
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <StatusBadge :status="listing.status" />
                        <span v-if="listing.is_featured" class="badge-amber">⭐ Featured</span>
                        <span v-if="listing.category_name" class="badge-slate">{{ listing.category_name }}</span>
                    </div>
                    <h1 class="font-display text-xl font-bold text-slate-900">{{ listing.title }}</h1>
                    <p class="mt-1 text-xs text-slate-500">
                        Created {{ listing.created_date }} · {{ listing.available_quantity }}/{{ listing.quantity }} available
                    </p>
                    <p
                        v-if="listing.description"
                        class="mt-3 whitespace-pre-line text-sm leading-relaxed text-slate-700"
                    >
                        {{ listing.description }}
                    </p>
                </div>

                <!-- Attributes -->
                <div v-if="listing.attributes.length" class="card-p">
                    <h2 class="section-title mb-2">Details</h2>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <div v-for="a in listing.attributes" :key="a.id" class="rounded-lg bg-slate-50 p-3">
                            <p class="text-xs text-slate-500">{{ a.label }}</p>
                            <p class="mt-0.5 text-sm font-medium text-slate-900">{{ a.value }}</p>
                        </div>
                    </div>
                </div>

                <!-- Admin feedback -->
                <div v-if="listing.rejection_reason" class="alert-error">
                    <strong>Rejected:</strong> {{ listing.rejection_reason }}
                </div>
                <div v-if="listing.changes_requested_note" class="alert-warning">
                    <strong>Changes requested:</strong> {{ listing.changes_requested_note }}
                </div>

                <!-- Edit history -->
                <div v-if="listing.edits.length" class="card-p">
                    <h2 class="section-title mb-3">Edit History</h2>
                    <ul class="flex flex-col gap-3">
                        <li
                            v-for="e in listing.edits"
                            :key="e.id"
                            class="flex items-start justify-between gap-2 border-b border-slate-100 pb-3 last:border-0 last:pb-0"
                        >
                            <div>
                                <StatusBadge :status="e.status" />
                                <p v-if="e.note" class="mt-1 text-xs text-slate-500">{{ e.note }}</p>
                            </div>
                            <span class="flex-shrink-0 text-xs text-slate-400">{{ e.at }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="mt-3 flex flex-col gap-3 lg:mt-0">
                <div class="card-p">
                    <p class="money text-2xl font-bold text-slate-900">{{ listing.price_formatted }}</p>

                    <div class="mt-3 flex flex-col gap-2">
                        <Link
                            v-if="canUpdate"
                            :href="route('dashboard.listings.edit', listing.id)"
                            class="btn-outline w-full"
                        >
                            ✎ Edit listing
                        </Link>

                        <button
                            v-if="canTogglePause"
                            type="button"
                            :class="listing.status === 'paused' ? 'btn-outline w-full' : 'btn-ghost w-full'"
                            :disabled="pauseForm.processing"
                            @click="togglePause"
                        >
                            {{ listing.status === 'paused' ? '▶ Resume listing' : '❙❙ Pause listing' }}
                        </button>

                        <button
                            v-if="listing.status === 'draft'"
                            type="button"
                            class="btn-primary w-full"
                            :disabled="submitForm.processing"
                            @click="submitDraft"
                        >
                            Submit for review →
                        </button>

                        <a
                            v-if="listing.status === 'published'"
                            :href="route('marketplace.show', listing.slug)"
                            target="_blank"
                            rel="noopener"
                            class="btn-ghost w-full"
                        >
                            🔗 View live listing
                        </a>
                    </div>
                </div>

                <div class="card-p">
                    <h2 class="section-title mb-2">Stats</h2>
                    <dl class="space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-500">Status</dt>
                            <dd><StatusBadge :status="listing.status" /></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Sold</dt>
                            <dd class="font-medium text-slate-900">{{ listing.sold_quantity }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Available</dt>
                            <dd class="font-medium text-slate-900">{{ listing.available_quantity }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Updated</dt>
                            <dd class="text-slate-500">{{ listing.updated_human }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
