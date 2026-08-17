<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

interface ListingData {
    id: number;
    title: string;
    slug: string;
    status: string;
    price: string;
    quantity: number;
    description: string | null;
    category_name: string | null;
    seller: string;
    seller_verified: boolean;
    created: string;
    marketplace_url: string;
    rejection_reason: string | null;
    changes_requested_note: string | null;
}
interface ImageRow {
    id: number;
    url: string;
}
interface AttributeRow {
    id: number;
    label: string | null;
    value: string | null;
}
interface PendingEdit {
    id: number;
    old_title: string;
    old_price: string;
    new_title: string;
    new_price: string;
}
interface EditRow {
    id: number;
    status: string;
    requester: string;
    reviewer: string | null;
    note: string | null;
    at: string;
}

const props = defineProps<{
    listing: ListingData;
    images: ImageRow[];
    attributes: AttributeRow[];
    pendingEdit: PendingEdit | null;
    edits: EditRow[];
}>();

// Mirror the server gates: approve/reject/request-changes/edit-review need
// listings.approve; suspend needs listings.suspend. Server authorize() re-checks.
const user = computed(() => usePage().props.auth.user);
const canApprove = computed(
    () => !!user.value && (user.value.roles.includes('admin') || user.value.permissions.includes('listings.approve')),
);
const canSuspend = computed(
    () => !!user.value && (user.value.roles.includes('admin') || user.value.permissions.includes('listings.suspend')),
);

// One in-flight flag disables every action button so no two writes race.
const processing = ref(false);

const approveNotes = ref('');
const rejectReason = ref('');
const rejectNotes = ref('');
const changesMessage = ref('');
const editRejectReason = ref('');

function post(routeName: string, param: number, data: Record<string, string> = {}): void {
    processing.value = true;
    router.post(route(routeName, param), data, {
        preserveScroll: true,
        onFinish: () => (processing.value = false),
    });
}
</script>

<template>
    <AdminLayout :title="listing.title" heading="Review Listing">
        <Breadcrumb
            :items="[{ label: 'Listings', url: route('admin.listings') }, { label: listing.title }]"
        />

        <div class="grid gap-4 lg:grid-cols-[1fr_22rem]">
            <!-- Main column -->
            <div class="flex flex-col gap-3">
                <!-- Images -->
                <div v-if="images.length" class="card-p">
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <img
                            v-for="img in images"
                            :key="img.id"
                            :src="img.url"
                            class="aspect-square w-full rounded-lg object-cover"
                            alt=""
                        />
                    </div>
                </div>

                <!-- Detail -->
                <div class="card-p">
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <StatusBadge :status="listing.status" />
                        <span v-if="listing.category_name" class="badge-slate">{{ listing.category_name }}</span>
                        <span v-if="listing.seller_verified" class="badge-mint">✓ Verified seller</span>
                    </div>
                    <h1 class="font-display text-xl font-bold text-slate-900">{{ listing.title }}</h1>
                    <div class="mt-2 flex flex-wrap gap-3 text-sm text-slate-500">
                        <span>Seller: <strong class="text-slate-900">{{ listing.seller }}</strong></span>
                        <span class="money font-semibold text-slate-900">{{ listing.price }}</span>
                        <span>Qty: {{ listing.quantity }}</span>
                        <span>Listed {{ listing.created }}</span>
                    </div>
                    <p
                        v-if="listing.description"
                        class="mt-3 whitespace-pre-line text-sm leading-relaxed text-slate-700"
                    >
                        {{ listing.description }}
                    </p>
                </div>

                <!-- Prior reviewer feedback (context when re-reviewing) -->
                <div v-if="listing.rejection_reason" class="alert-error">
                    <strong>Rejected:</strong> {{ listing.rejection_reason }}
                </div>
                <div v-if="listing.changes_requested_note" class="alert-warning">
                    <strong>Changes requested:</strong> {{ listing.changes_requested_note }}
                </div>

                <!-- Attributes -->
                <div v-if="attributes.length" class="card-p">
                    <h2 class="section-title mb-2">Attributes</h2>
                    <dl class="grid grid-cols-2 gap-3">
                        <div v-for="a in attributes" :key="a.id" class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">{{ a.label }}</dt>
                            <dd class="text-sm font-medium text-slate-900">{{ a.value }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Pending edit diff -->
                <div v-if="pendingEdit" class="card overflow-hidden border-2 border-amber-200">
                    <div class="border-b border-amber-200 bg-amber-50 px-3 py-2">
                        <h2 class="font-semibold text-amber-700">⚠ Pending Edit — review changes</h2>
                    </div>
                    <div class="grid grid-cols-2 gap-3 p-3 text-sm">
                        <div>
                            <h3 class="mb-2 font-semibold text-slate-500">Current (live)</h3>
                            <p class="font-medium">{{ pendingEdit.old_title }}</p>
                            <p class="money text-slate-900">{{ pendingEdit.old_price }}</p>
                        </div>
                        <div>
                            <h3 class="mb-2 font-semibold text-brand-600">Proposed changes</h3>
                            <p class="font-medium">{{ pendingEdit.new_title }}</p>
                            <p class="money text-brand-600">{{ pendingEdit.new_price }}</p>
                        </div>
                    </div>
                    <div v-if="canApprove" class="flex flex-wrap items-start gap-3 px-3 pb-3">
                        <button
                            type="button"
                            class="btn-success btn-sm"
                            :disabled="processing"
                            @click="post('admin.listings.approve-edit', pendingEdit.id)"
                        >
                            Approve edit
                        </button>
                        <div class="flex gap-2">
                            <input
                                v-model="editRejectReason"
                                class="input text-sm"
                                placeholder="Rejection reason"
                            />
                            <button
                                type="button"
                                class="btn-danger btn-sm"
                                :disabled="processing || !editRejectReason.trim()"
                                @click="post('admin.listings.reject-edit', pendingEdit.id, { reason: editRejectReason })"
                            >
                                Reject edit
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Edit history -->
                <div v-if="edits.length" class="card-p">
                    <h2 class="section-title mb-2">Edit History</h2>
                    <div class="flex flex-col divide-y divide-slate-100">
                        <div v-for="e in edits" :key="e.id" class="flex items-start justify-between gap-3 py-2">
                            <div>
                                <StatusBadge :status="e.status" />
                                <p class="mt-1 text-xs text-slate-500">By {{ e.requester }} · {{ e.at }}</p>
                                <p v-if="e.note" class="text-xs text-slate-500">Note: {{ e.note }}</p>
                            </div>
                            <span v-if="e.reviewer" class="text-xs text-slate-400">{{ e.reviewer }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions sidebar -->
            <div class="flex flex-col gap-3">
                <div class="card-p">
                    <a
                        :href="listing.marketplace_url"
                        target="_blank"
                        rel="noopener"
                        class="btn-outline btn-sm w-full"
                    >
                        🔗 View public listing
                    </a>
                </div>

                <template v-if="canApprove && listing.status === 'pending_review'">
                    <!-- Approve -->
                    <div class="card-p">
                        <h2 class="section-title mb-2">Approve</h2>
                        <div class="flex flex-col gap-2">
                            <textarea
                                v-model="approveNotes"
                                rows="2"
                                class="textarea text-sm"
                                placeholder="Admin notes (optional)…"
                            ></textarea>
                            <button
                                type="button"
                                class="btn-success w-full"
                                :disabled="processing"
                                @click="post('admin.listings.approve', listing.id, { notes: approveNotes })"
                            >
                                ✓ Approve &amp; Publish
                            </button>
                        </div>
                    </div>

                    <!-- Reject -->
                    <div class="card-p">
                        <h2 class="section-title mb-2">Reject</h2>
                        <div class="flex flex-col gap-2">
                            <textarea
                                v-model="rejectReason"
                                rows="3"
                                class="textarea text-sm"
                                placeholder="Rejection reason (required)…"
                            ></textarea>
                            <textarea
                                v-model="rejectNotes"
                                rows="2"
                                class="textarea text-sm"
                                placeholder="Internal notes (optional)…"
                            ></textarea>
                            <button
                                type="button"
                                class="btn-danger w-full"
                                :disabled="processing || !rejectReason.trim()"
                                @click="post('admin.listings.reject', listing.id, { reason: rejectReason, notes: rejectNotes })"
                            >
                                ✕ Reject
                            </button>
                        </div>
                    </div>

                    <!-- Request changes -->
                    <div class="card-p">
                        <h2 class="section-title mb-2">Request Changes</h2>
                        <div class="flex flex-col gap-2">
                            <textarea
                                v-model="changesMessage"
                                rows="4"
                                class="textarea text-sm"
                                placeholder="Describe what the seller needs to fix or improve…"
                            ></textarea>
                            <button
                                type="button"
                                class="btn-warning w-full"
                                :disabled="processing || !changesMessage.trim()"
                                @click="post('admin.listings.request-changes', listing.id, { message: changesMessage })"
                            >
                                ↩ Request changes
                            </button>
                        </div>
                    </div>
                </template>

                <!-- Suspend (published only) -->
                <div v-else-if="canSuspend && listing.status === 'published'" class="card-p">
                    <h2 class="section-title mb-2">Moderation</h2>
                    <button
                        type="button"
                        class="btn-danger w-full"
                        :disabled="processing"
                        @click="post('admin.listings.suspend', listing.id)"
                    >
                        Suspend listing
                    </button>
                    <p class="mt-2 text-xs text-slate-500">Removes the listing from the marketplace.</p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
