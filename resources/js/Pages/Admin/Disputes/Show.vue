<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';

interface DisputeDetail {
    id: number;
    status: string;
    status_label: string;
    is_open: boolean;
    reason: string;
    opener: string;
    resolution_type: string | null;
    resolution_amount: string | null;
    resolution_note: string | null;
    resolver: string;
    resolved_at: string;
}
interface OrderSummary {
    number: string;
    url: string | null;
    buyer: string;
    seller: string;
    buyer_total: string;
    buyer_total_bdt: number;
    seller_earning: string;
}
interface EvidenceItem {
    id: number;
    role: string;
    is_buyer: boolean;
    submitter: string;
    created: string;
    message: string | null;
    has_file: boolean;
    file_name: string | null;
}
interface OrderMessage {
    id: number;
    sender: string;
    body: string;
}

const props = defineProps<{
    dispute: DisputeDetail;
    order: OrderSummary;
    evidence: EvidenceItem[];
    messages: OrderMessage[];
}>();

// Resolution actions authorize disputes.manage server-side; mirror the sidebar rule client-side.
const canManage = computed(() => {
    const u = usePage().props.auth.user;
    if (!u) return false;
    return u.roles.includes('admin') || u.permissions.includes('disputes.manage');
});

// One in-flight flag disables every action button so no two resolutions race.
const processing = ref(false);

// Status update (PATCH) — note is optional server-side.
const statusValue = ref('under_review');
const statusNote = ref('');

// Money resolutions (POST) — a reason note is required client-side before the button enables.
const fullNote = ref('');
const partialAmount = ref('');
const partialNote = ref('');
const releaseNote = ref('');

const canPartial = computed(() => {
    const n = Number(partialAmount.value);
    return (
        !processing.value &&
        partialAmount.value.trim() !== '' &&
        n > 0 &&
        n <= props.order.buyer_total_bdt &&
        partialNote.value.trim() !== ''
    );
});

function post(routeName: string, data: Record<string, string>): void {
    processing.value = true;
    router.post(route(routeName, props.dispute.id), data, {
        preserveScroll: true,
        onFinish: () => (processing.value = false),
    });
}

function updateStatus(): void {
    processing.value = true;
    router.patch(
        route('admin.disputes.status', props.dispute.id),
        { status: statusValue.value, note: statusNote.value },
        { preserveScroll: true, onFinish: () => (processing.value = false) },
    );
}
</script>

<template>
    <AdminLayout :title="`Dispute #${dispute.id}`" heading="Dispute Review">
        <Breadcrumb
            :items="[{ label: 'Disputes', url: route('admin.disputes') }, { label: `#${dispute.id}` }]"
        />

        <div class="grid gap-4 lg:grid-cols-[1fr_22rem]">
            <!-- Main column -->
            <div class="flex flex-col gap-3">
                <!-- Dispute details -->
                <div class="card-p">
                    <h2 class="section-title mb-3">Dispute Details</h2>
                    <dl class="mb-3 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">Order</dt>
                            <dd class="font-mono font-medium">
                                <Link v-if="order.url" :href="order.url" class="text-brand-600">{{ order.number }}</Link>
                                <span v-else>{{ order.number }}</span>
                            </dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">Status</dt>
                            <dd class="font-medium">{{ dispute.status_label }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">Buyer</dt>
                            <dd>{{ order.buyer }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">Seller</dt>
                            <dd>{{ order.seller }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">Order Total</dt>
                            <dd class="money font-bold">{{ order.buyer_total }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">Seller Earning (at risk)</dt>
                            <dd class="money font-bold text-amber-600">{{ order.seller_earning }}</dd>
                        </div>
                    </dl>
                    <div class="rounded-lg bg-rose-50 p-3">
                        <p class="mb-1 font-semibold text-rose-900">Buyer's Reason</p>
                        <p class="text-sm text-rose-800">{{ dispute.reason }}</p>
                    </div>
                </div>

                <!-- Evidence -->
                <div v-if="evidence.length" class="card-p">
                    <h2 class="section-title mb-2">Evidence Submitted</h2>
                    <div class="flex flex-col gap-2">
                        <div v-for="ev in evidence" :key="ev.id" class="rounded-lg border border-slate-200 p-2">
                            <div class="mb-1 flex items-center gap-2">
                                <span :class="ev.is_buyer ? 'badge-rose' : 'badge-brand'">{{ ev.role }}</span>
                                <span class="text-xs text-slate-500">{{ ev.submitter }} · {{ ev.created }}</span>
                            </div>
                            <p v-if="ev.message" class="text-sm text-slate-900">{{ ev.message }}</p>
                            <span v-if="ev.has_file" class="mt-1 inline-flex text-xs text-slate-500">📎 {{ ev.file_name }}</span>
                        </div>
                    </div>
                </div>

                <!-- Order conversation snapshot -->
                <div v-if="messages.length" class="card-p">
                    <div class="mb-2 flex items-center justify-between">
                        <h2 class="section-title">Order Messages</h2>
                        <span class="badge-slate">{{ messages.length }} messages</span>
                    </div>
                    <div class="flex max-h-48 flex-col gap-2 overflow-y-auto">
                        <div v-for="m in messages" :key="m.id" class="flex items-start gap-2">
                            <span class="w-20 flex-shrink-0 truncate text-xs font-medium text-slate-500">{{ m.sender }}</span>
                            <p class="flex-grow rounded bg-slate-50 px-2 py-1 text-xs text-slate-900">{{ m.body }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resolution panel -->
            <div class="flex flex-col gap-3">
                <template v-if="dispute.is_open && canManage">
                    <!-- Update status -->
                    <div class="card-p">
                        <h2 class="section-title mb-2">Update Status</h2>
                        <form class="flex flex-col gap-2" @submit.prevent="updateStatus">
                            <select v-model="statusValue" class="select text-sm">
                                <option value="under_review">Under Review</option>
                                <option value="waiting_for_buyer">Waiting for Buyer</option>
                                <option value="waiting_for_seller">Waiting for Seller</option>
                            </select>
                            <textarea v-model="statusNote" rows="2" class="textarea text-sm" placeholder="Admin note…"></textarea>
                            <button type="submit" class="btn-outline w-full" :disabled="processing">Update status</button>
                        </form>
                    </div>

                    <!-- Full refund -->
                    <div class="card-p">
                        <h2 class="section-title mb-1">Full Refund</h2>
                        <p class="section-sub mb-2">
                            Buyer receives <span class="money font-semibold">{{ order.buyer_total }}</span>. Seller loses full earning.
                        </p>
                        <form class="flex flex-col gap-2" @submit.prevent="post('admin.disputes.full-refund', { note: fullNote })">
                            <textarea
                                v-model="fullNote"
                                rows="2"
                                class="textarea text-sm"
                                placeholder="Reason for full refund (required)…"
                            ></textarea>
                            <button type="submit" class="btn-danger w-full" :disabled="processing || !fullNote.trim()">
                                Issue full refund
                            </button>
                        </form>
                    </div>

                    <!-- Partial refund -->
                    <div class="card-p">
                        <h2 class="section-title mb-1">Partial Refund</h2>
                        <form class="flex flex-col gap-2" @submit.prevent="post('admin.disputes.partial-refund', { refund_bdt: partialAmount, note: partialNote })">
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-2 flex items-center font-mono text-sm text-slate-400">৳</span>
                                <input
                                    v-model="partialAmount"
                                    type="number"
                                    min="1"
                                    step="1"
                                    :max="order.buyer_total_bdt"
                                    class="input pl-6 text-sm"
                                    placeholder="0"
                                />
                            </div>
                            <textarea v-model="partialNote" rows="2" class="textarea text-sm" placeholder="Reason…"></textarea>
                            <button type="submit" class="btn-warning w-full" :disabled="!canPartial">Issue partial refund</button>
                        </form>
                    </div>

                    <!-- Release to seller -->
                    <div class="card-p">
                        <h2 class="section-title mb-1">Release to Seller</h2>
                        <p class="section-sub mb-2">
                            Seller receives <span class="money font-semibold text-mint-600">{{ order.seller_earning }}</span>. No refund to buyer.
                        </p>
                        <form class="flex flex-col gap-2" @submit.prevent="post('admin.disputes.release-seller', { note: releaseNote })">
                            <textarea
                                v-model="releaseNote"
                                rows="2"
                                class="textarea text-sm"
                                placeholder="Reason for releasing to seller…"
                            ></textarea>
                            <button type="submit" class="btn-success w-full" :disabled="processing || !releaseNote.trim()">
                                Release to seller
                            </button>
                        </form>
                    </div>
                </template>

                <!-- Resolved: read-only summary -->
                <div v-else class="card-p">
                    <h2 class="section-title mb-2">Resolution</h2>
                    <div class="flex flex-col gap-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Type</span>
                            <span class="font-medium">{{ dispute.resolution_type ?? '—' }}</span>
                        </div>
                        <div v-if="dispute.resolution_amount" class="flex justify-between">
                            <span class="text-slate-500">Amount</span>
                            <span class="money font-bold">{{ dispute.resolution_amount }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Resolved by</span>
                            <span>{{ dispute.resolver }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Resolved at</span>
                            <span>{{ dispute.resolved_at }}</span>
                        </div>
                    </div>
                    <div v-if="dispute.resolution_note" class="mt-2 rounded-lg bg-slate-50 p-2 text-xs text-slate-500">
                        {{ dispute.resolution_note }}
                    </div>
                    <p v-if="dispute.is_open && !canManage" class="mt-3 text-xs text-slate-500">
                        You don't have permission to resolve disputes.
                    </p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
