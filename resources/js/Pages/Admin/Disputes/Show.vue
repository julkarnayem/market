<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

interface DisputeDetail {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    /** Whether a decision can still be applied — mirrors DisputeStatus::isActive(). */
    is_active: boolean;
    is_escalated: boolean;
    reason: string;
    reason_code: string | null;
    description: string | null;
    opener: string;
    opened: string | null;
    seller_responded: string | null;
    escalated_at: string | null;
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
    asset_title: string;
    buyer_total: string;
    buyer_total_bdt: number;
    seller_earning: string;
}
interface EvidenceRef {
    id: number;
    name: string | null;
    size: string | null;
    is_image: boolean;
    url: string;
}
/** One thread row from DisputeService::threadFor() — staff see internal notes too. */
interface ThreadMessage {
    id: number;
    type: string;
    role: string | null;
    author: string;
    body: string | null;
    is_internal: boolean;
    is_system: boolean;
    is_mine: boolean;
    at: string | null;
    metadata: Record<string, unknown> | null;
    evidence: EvidenceRef | null;
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
    file_size: string | null;
    is_image: boolean;
    url: string | null;
}
interface PendingProposal {
    id: number;
    type_label: string;
    amount: string | null;
    note: string | null;
    proposer: string;
    role: string;
    awaiting: string | null;
}
interface ResolutionRow {
    id: number;
    type_label: string;
    amount: string | null;
    role: string;
    proposer: string;
    status: string;
    executed: boolean;
    note: string | null;
    at: string | null;
}
interface OrderMessage {
    id: number;
    sender: string;
    body: string;
}

const props = defineProps<{
    dispute: DisputeDetail;
    order: OrderSummary;
    thread: ThreadMessage[];
    evidence: EvidenceItem[];
    pending: PendingProposal | null;
    resolutions: ResolutionRow[];
    messages: OrderMessage[];
    reasons: { value: string; label: string }[];
}>();

// Every action here authorizes disputes.manage server-side; mirror it client-side
// so a read-only reviewer is not shown buttons that would 403.
const canManage = computed(() => {
    const u = usePage().props.auth.user;
    if (!u) return false;
    return u.roles.includes('admin') || u.permissions.includes('disputes.manage');
});

// One in-flight flag disables every action button so no two decisions race.
const processing = ref(false);

function post(routeName: string, data: Record<string, string> = {}): void {
    processing.value = true;
    router.post(route(routeName, props.dispute.id), data, {
        preserveScroll: true,
        onFinish: () => (processing.value = false),
    });
}

// Money decisions — a reason note is required client-side before the button enables.
const fullNote = ref('');
const partialAmount = ref('');
const partialNote = ref('');
const releaseNote = ref('');
const replacementNote = ref('');
const closeNote = ref('');
const escalateNote = ref('');

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

// Thread writes get their own forms so validation errors land on the right box.
const messageForm = useForm<{ body: string }>({ body: '' });
const noteForm = useForm<{ body: string }>({ body: '' });
const evidenceForm = useForm<{ from: string; note: string }>({ from: 'both', note: '' });

function sendMessage(): void {
    messageForm.post(route('admin.disputes.message', props.dispute.id), {
        preserveScroll: true,
        onSuccess: () => messageForm.reset(),
    });
}
function saveNote(): void {
    noteForm.post(route('admin.disputes.note', props.dispute.id), {
        preserveScroll: true,
        onSuccess: () => noteForm.reset(),
    });
}
function requestEvidence(): void {
    evidenceForm.post(route('admin.disputes.request-evidence', props.dispute.id), {
        preserveScroll: true,
        onSuccess: () => evidenceForm.reset(),
    });
}

/** Bubble tone per message type — internal notes must never look like a reply. */
function toneFor(m: ThreadMessage): string {
    if (m.is_internal) return 'border-violet-300 bg-violet-50';
    if (m.type === 'admin_decision') return 'border-brand-200 bg-brand-50';
    if (m.is_system) return 'border-slate-200 bg-slate-50';
    return 'border-slate-200 bg-white';
}
</script>

<template>
    <AdminLayout :title="`Dispute ${dispute.reference}`" heading="Dispute Review">
        <Breadcrumb
            :items="[{ label: 'Disputes', url: route('admin.disputes') }, { label: dispute.reference }]"
        />

        <div class="grid gap-4 lg:grid-cols-[1fr_22rem]">
            <!-- ── Main column ────────────────────────────────────── -->
            <div class="flex flex-col gap-3">
                <!-- Dispute details -->
                <div class="card-p">
                    <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h2 class="section-title">{{ dispute.reference }} — {{ dispute.reason }}</h2>
                            <p class="section-sub">Opened by {{ dispute.opener }} on {{ dispute.opened }}</p>
                        </div>
                        <StatusBadge :status="dispute.status" />
                    </div>

                    <dl class="mb-3 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">Order</dt>
                            <dd class="font-mono font-medium">
                                <Link v-if="order.url" :href="order.url" class="text-brand-600">
                                    {{ order.number }}
                                </Link>
                                <span v-else>{{ order.number }}</span>
                            </dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">Asset</dt>
                            <dd class="truncate font-medium">{{ order.asset_title }}</dd>
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
                            <dt class="text-xs text-slate-500">Seller Earning (held)</dt>
                            <dd class="money font-bold text-amber-600">{{ order.seller_earning }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">Seller responded</dt>
                            <dd>{{ dispute.seller_responded ?? 'Not yet' }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">Escalated</dt>
                            <dd>{{ dispute.escalated_at ?? '—' }}</dd>
                        </div>
                    </dl>

                    <div v-if="dispute.description" class="rounded-lg bg-rose-50 p-3">
                        <p class="mb-1 font-semibold text-rose-900">What the buyer reported</p>
                        <p class="whitespace-pre-line text-sm text-rose-800">{{ dispute.description }}</p>
                    </div>
                </div>

                <!-- The dispute thread, internal notes included -->
                <div class="card-p">
                    <div class="mb-2 flex items-center justify-between">
                        <h2 class="section-title">Dispute Thread</h2>
                        <span class="badge-slate">{{ thread.length }} entries</span>
                    </div>

                    <div class="flex flex-col gap-2">
                        <p v-if="thread.length === 0" class="py-4 text-center text-sm text-slate-500">
                            Nothing here yet.
                        </p>
                        <div
                            v-for="m in thread"
                            :key="m.id"
                            class="rounded-xl border p-3"
                            :class="toneFor(m)"
                        >
                            <div class="mb-1 flex flex-wrap items-center gap-2 text-xs">
                                <span class="font-semibold text-slate-900">{{ m.author }}</span>
                                <span v-if="m.role" class="badge-slate capitalize">{{ m.role }}</span>
                                <span v-if="m.type === 'admin_decision'" class="badge-brand">Decision</span>
                                <span v-if="m.is_internal" class="badge-violet">Internal — staff only</span>
                                <span class="ml-auto text-slate-500">{{ m.at }}</span>
                            </div>
                            <p v-if="m.body" class="whitespace-pre-line text-sm text-slate-900">{{ m.body }}</p>
                            <a
                                v-if="m.evidence"
                                :href="m.evidence.url"
                                class="mt-2 inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs text-brand-600 hover:bg-slate-50"
                            >
                                <span aria-hidden="true">{{ m.evidence.is_image ? '🖼️' : '📎' }}</span>
                                {{ m.evidence.name ?? 'Attachment' }}
                                <span v-if="m.evidence.size" class="text-slate-400">{{ m.evidence.size }}</span>
                            </a>
                        </div>
                    </div>

                    <template v-if="canManage">
                        <!-- Visible to both parties -->
                        <form class="mt-3 flex flex-col gap-2" @submit.prevent="sendMessage">
                            <textarea
                                v-model="messageForm.body"
                                rows="2"
                                class="textarea text-sm"
                                :class="messageForm.errors.body && 'input-error'"
                                maxlength="5000"
                                placeholder="Reply to both parties…"
                            ></textarea>
                            <p v-if="messageForm.errors.body" class="field-error">
                                {{ messageForm.errors.body }}
                            </p>
                            <button
                                type="submit"
                                class="btn-primary self-end"
                                :disabled="messageForm.processing || messageForm.body.trim().length < 2"
                            >
                                Post reply
                            </button>
                        </form>

                        <!-- Never leaves this screen — threadFor() drops it for parties -->
                        <form
                            class="mt-3 flex flex-col gap-2 rounded-xl border border-violet-200 bg-violet-50/60 p-3"
                            @submit.prevent="saveNote"
                        >
                            <label class="label mb-0 text-violet-900" for="internal-note">
                                Internal note (staff only)
                            </label>
                            <textarea
                                id="internal-note"
                                v-model="noteForm.body"
                                rows="2"
                                class="textarea text-sm"
                                :class="noteForm.errors.body && 'input-error'"
                                maxlength="5000"
                                placeholder="Findings, prior history, what to check next…"
                            ></textarea>
                            <p v-if="noteForm.errors.body" class="field-error">{{ noteForm.errors.body }}</p>
                            <button
                                type="submit"
                                class="btn-outline self-end"
                                :disabled="noteForm.processing || noteForm.body.trim().length < 2"
                            >
                                Save note
                            </button>
                        </form>
                    </template>
                </div>

                <!-- Evidence -->
                <div v-if="evidence.length" class="card-p">
                    <h2 class="section-title mb-2">Evidence Submitted</h2>
                    <div class="flex flex-col gap-2">
                        <div v-for="ev in evidence" :key="ev.id" class="rounded-lg border border-slate-200 p-2">
                            <div class="mb-1 flex items-center gap-2">
                                <span :class="ev.is_buyer ? 'badge-rose' : 'badge-brand'">{{ ev.role }}</span>
                                <span class="text-xs text-slate-500">
                                    {{ ev.submitter }} · {{ ev.created }}
                                </span>
                            </div>
                            <p v-if="ev.message" class="text-sm text-slate-900">{{ ev.message }}</p>
                            <a
                                v-if="ev.has_file && ev.url"
                                :href="ev.url"
                                class="mt-1 inline-flex items-center gap-2 text-xs text-brand-600 hover:underline"
                            >
                                <span aria-hidden="true">{{ ev.is_image ? '🖼️' : '📎' }}</span>
                                {{ ev.file_name }}
                                <span v-if="ev.file_size" class="text-slate-400">{{ ev.file_size }}</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Every proposal the parties made -->
                <div v-if="resolutions.length" class="card-p">
                    <h2 class="section-title mb-2">Proposals &amp; Decisions</h2>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>By</th>
                                    <th>Status</th>
                                    <th>When</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in resolutions" :key="r.id">
                                    <td class="text-sm font-medium">{{ r.type_label }}</td>
                                    <td class="money text-sm">{{ r.amount ?? '—' }}</td>
                                    <td class="text-sm">{{ r.proposer }} <span class="text-xs text-slate-500">({{ r.role }})</span></td>
                                    <td>
                                        <span class="badge-slate capitalize">{{ r.status }}</span>
                                        <span v-if="r.executed" class="badge-mint ml-1">executed</span>
                                    </td>
                                    <td class="text-xs text-slate-500">{{ r.at }}</td>
                                </tr>
                            </tbody>
                        </table>
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
                            <span class="w-20 flex-shrink-0 truncate text-xs font-medium text-slate-500">
                                {{ m.sender }}
                            </span>
                            <p class="flex-grow rounded bg-slate-50 px-2 py-1 text-xs text-slate-900">
                                {{ m.body }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Action panel ───────────────────────────────────── -->
            <div class="flex flex-col gap-3">
                <!-- What the parties have on the table right now -->
                <div v-if="pending" class="card-p ring-2 ring-amber-400/60">
                    <h2 class="section-title mb-1">Proposal pending</h2>
                    <p class="section-sub mb-2">
                        {{ pending.proposer }} ({{ pending.role }}) proposed this; waiting on the
                        {{ pending.awaiting ?? 'other party' }}.
                    </p>
                    <div class="rounded-lg bg-amber-50 p-2 text-sm">
                        <p class="font-semibold text-amber-900">{{ pending.type_label }}</p>
                        <p v-if="pending.amount" class="money mt-1 font-bold text-amber-900">
                            {{ pending.amount }}
                        </p>
                        <p v-if="pending.note" class="mt-1 whitespace-pre-line text-amber-800">
                            {{ pending.note }}
                        </p>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">
                        Deciding below overrides it — the proposal is withdrawn.
                    </p>
                </div>

                <template v-if="dispute.is_active && canManage">
                    <!-- Take ownership -->
                    <div v-if="!dispute.is_escalated" class="card-p">
                        <h2 class="section-title mb-1">Take Over</h2>
                        <p class="section-sub mb-2">
                            Marks it escalated so the parties know staff are deciding it.
                        </p>
                        <form
                            class="flex flex-col gap-2"
                            @submit.prevent="post('admin.disputes.escalate', { note: escalateNote })"
                        >
                            <textarea
                                v-model="escalateNote"
                                rows="2"
                                class="textarea text-sm"
                                placeholder="Note to the parties (optional)…"
                            ></textarea>
                            <button type="submit" class="btn-outline w-full" :disabled="processing">
                                Escalate to staff
                            </button>
                        </form>
                    </div>

                    <!-- Ask for more -->
                    <div class="card-p">
                        <h2 class="section-title mb-2">Request Evidence</h2>
                        <form class="flex flex-col gap-2" @submit.prevent="requestEvidence">
                            <select v-model="evidenceForm.from" class="select text-sm">
                                <option value="both">From both parties</option>
                                <option value="buyer">From the buyer</option>
                                <option value="seller">From the seller</option>
                            </select>
                            <textarea
                                v-model="evidenceForm.note"
                                rows="2"
                                class="textarea text-sm"
                                placeholder="What exactly do you need?"
                            ></textarea>
                            <button
                                type="submit"
                                class="btn-outline w-full"
                                :disabled="evidenceForm.processing"
                            >
                                Request evidence
                            </button>
                        </form>
                    </div>

                    <!-- Full refund -->
                    <div class="card-p">
                        <h2 class="section-title mb-1">Full Refund</h2>
                        <p class="section-sub mb-2">
                            Buyer receives <span class="money font-semibold">{{ order.buyer_total }}</span>. The
                            seller's held earning is reversed.
                        </p>
                        <form
                            class="flex flex-col gap-2"
                            @submit.prevent="post('admin.disputes.full-refund', { note: fullNote })"
                        >
                            <textarea
                                v-model="fullNote"
                                rows="2"
                                class="textarea text-sm"
                                placeholder="Reason for full refund (required)…"
                            ></textarea>
                            <button
                                type="submit"
                                class="btn-danger w-full"
                                :disabled="processing || !fullNote.trim()"
                            >
                                Issue full refund
                            </button>
                        </form>
                    </div>

                    <!-- Partial refund -->
                    <div class="card-p">
                        <h2 class="section-title mb-1">Partial Refund</h2>
                        <p class="section-sub mb-2">
                            The seller keeps the rest of their earning; the held remainder is released.
                        </p>
                        <form
                            class="flex flex-col gap-2"
                            @submit.prevent="
                                post('admin.disputes.partial-refund', {
                                    refund_bdt: partialAmount,
                                    note: partialNote,
                                })
                            "
                        >
                            <div class="relative">
                                <span
                                    class="pointer-events-none absolute inset-y-0 left-2 flex items-center font-mono text-sm text-slate-400"
                                >
                                    ৳
                                </span>
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
                            <textarea
                                v-model="partialNote"
                                rows="2"
                                class="textarea text-sm"
                                placeholder="Reason…"
                            ></textarea>
                            <button type="submit" class="btn-warning w-full" :disabled="!canPartial">
                                Issue partial refund
                            </button>
                        </form>
                    </div>

                    <!-- Release to seller -->
                    <div class="card-p">
                        <h2 class="section-title mb-1">Release to Seller</h2>
                        <p class="section-sub mb-2">
                            Seller receives
                            <span class="money font-semibold text-mint-600">{{ order.seller_earning }}</span>. No
                            refund to buyer.
                        </p>
                        <form
                            class="flex flex-col gap-2"
                            @submit.prevent="post('admin.disputes.release-seller', { note: releaseNote })"
                        >
                            <textarea
                                v-model="releaseNote"
                                rows="2"
                                class="textarea text-sm"
                                placeholder="Reason for releasing to seller…"
                            ></textarea>
                            <button
                                type="submit"
                                class="btn-success w-full"
                                :disabled="processing || !releaseNote.trim()"
                            >
                                Release to seller
                            </button>
                        </form>
                    </div>

                    <!-- Replacement — no money moves -->
                    <div class="card-p">
                        <h2 class="section-title mb-1">Order a Replacement</h2>
                        <p class="section-sub mb-2">
                            No money moves. The order goes back to awaiting delivery and the seller re-delivers.
                        </p>
                        <form
                            class="flex flex-col gap-2"
                            @submit.prevent="post('admin.disputes.replacement', { note: replacementNote })"
                        >
                            <textarea
                                v-model="replacementNote"
                                rows="2"
                                class="textarea text-sm"
                                placeholder="What must the seller re-deliver? (required)"
                            ></textarea>
                            <button
                                type="submit"
                                class="btn-outline w-full"
                                :disabled="processing || !replacementNote.trim()"
                            >
                                Order replacement
                            </button>
                        </form>
                    </div>

                    <!-- Close with nothing -->
                    <div class="card-p">
                        <h2 class="section-title mb-1">Close — No Refund</h2>
                        <p class="section-sub mb-2">
                            Dismisses the claim. The order returns to the status it held before the dispute.
                        </p>
                        <form
                            class="flex flex-col gap-2"
                            @submit.prevent="post('admin.disputes.close', { note: closeNote })"
                        >
                            <textarea
                                v-model="closeNote"
                                rows="2"
                                class="textarea text-sm"
                                placeholder="Why is the claim being dismissed? (required)"
                            ></textarea>
                            <button
                                type="submit"
                                class="btn-ghost w-full"
                                :disabled="processing || !closeNote.trim()"
                            >
                                Close dispute
                            </button>
                        </form>
                    </div>
                </template>

                <!-- Settled, or nothing this reviewer can do -->
                <div v-else class="card-p">
                    <h2 class="section-title mb-2">Resolution</h2>
                    <div class="flex flex-col gap-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Result</span>
                            <span class="font-medium">{{ dispute.status_label }}</span>
                        </div>
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
                    <p v-if="dispute.is_active && !canManage" class="mt-3 text-xs text-slate-500">
                        You don't have permission to resolve disputes.
                    </p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
