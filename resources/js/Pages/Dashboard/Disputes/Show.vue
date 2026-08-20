<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

interface EvidenceRef {
    id: number;
    name: string | null;
    size: string | null;
    is_image: boolean;
    /** Authorizing download route — never a public storage URL. */
    url: string;
}
/** One thread row, built by DisputeService::threadFor() for this viewer. */
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
interface DisputeDetail {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    is_active: boolean;
    is_escalated: boolean;
    reason: string;
    description: string | null;
    opener: string;
    opened: string | null;
    resolution_type: string | null;
    resolution_amount: string | null;
    resolution_note: string | null;
    resolved_at: string | null;
}
interface OrderSummary {
    number: string;
    url: string | null;
    asset_title: string;
    buyer: string;
    seller: string;
    buyer_total: string;
    buyer_total_bdt: number;
}
interface PendingProposal {
    id: number;
    type: string;
    type_label: string;
    amount: string | null;
    note: string | null;
    proposer: string;
    role: string;
    is_mine: boolean;
    /** The role expected to answer — buyer or seller. */
    awaiting: string | null;
    at: string | null;
}
interface HistoryRow {
    id: number;
    type_label: string;
    amount: string | null;
    role: string;
    proposer: string;
    status: string;
    executed: boolean;
    at: string | null;
}
interface Option {
    value: string;
    label: string;
}

const props = defineProps<{
    dispute: DisputeDetail;
    order: OrderSummary;
    thread: ThreadMessage[];
    /** buyer | seller | admin — whatever Dispute::roleOf() said. */
    role: string | null;
    pending: PendingProposal | null;
    history: HistoryRow[];
    /** Negotiable resolution types; release-to-seller is admin-only. */
    options: Option[];
    can: {
        message: boolean;
        evidence: boolean;
        propose: boolean;
        escalate: boolean;
        cancel: boolean;
        respond: boolean;
        withdraw: boolean;
    };
}>();

// ── Thread composer ──────────────────────────────────────────────
// The id is minted on first submit and only cleared once the post lands, so a
// double-clicked button reuses it and the server dedupes on
// (dispute_id, client_message_id) instead of posting twice.
const newId = (): string =>
    globalThis.crypto?.randomUUID?.() ??
    `m-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;

const messageForm = useForm<{ body: string; client_message_id: string }>({
    body: '',
    client_message_id: '',
});

function sendMessage(): void {
    if (!messageForm.client_message_id) messageForm.client_message_id = newId();
    messageForm.post(route('dashboard.disputes.message', props.dispute.id), {
        preserveScroll: true,
        onSuccess: () => messageForm.reset(),
    });
}

// ── Evidence ─────────────────────────────────────────────────────
const fileInput = ref<HTMLInputElement | null>(null);
const evidenceForm = useForm<{ file: File | null; note: string }>({ file: null, note: '' });

function pickFile(event: Event): void {
    evidenceForm.file = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function uploadEvidence(): void {
    if (!evidenceForm.file) return;
    evidenceForm.post(route('dashboard.disputes.evidence.store', props.dispute.id), {
        preserveScroll: true,
        onSuccess: () => {
            evidenceForm.reset();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}

// ── Settlement between the parties ───────────────────────────────
const proposalForm = useForm<{ type: string; amount_bdt: string; note: string }>({
    type: '',
    amount_bdt: '',
    note: '',
});

const needsAmount = computed(() => proposalForm.type === 'partial_refund');

const canPropose = computed(() => {
    if (proposalForm.processing || !proposalForm.type) return false;
    if (!needsAmount.value) return true;
    const n = Number(proposalForm.amount_bdt);
    return proposalForm.amount_bdt.trim() !== '' && n > 0 && n <= props.order.buyer_total_bdt;
});

function propose(): void {
    proposalForm.post(route('dashboard.disputes.propose', props.dispute.id), {
        preserveScroll: true,
        onSuccess: () => proposalForm.reset(),
    });
}

// One in-flight flag for the plain POST actions so no two decisions race.
const processing = ref(false);
const declineNote = ref('');
const escalateNote = ref('');
const cancelNote = ref('');

function post(routeName: string, param: number, data: Record<string, string> = {}): void {
    processing.value = true;
    router.post(route(routeName, param), data, {
        preserveScroll: true,
        onFinish: () => (processing.value = false),
    });
}

/** Bubble tone per message type — system and admin rows read as announcements. */
function toneFor(m: ThreadMessage): string {
    if (m.is_internal) return 'border-violet-200 bg-violet-50';
    if (m.type === 'admin_decision') return 'border-brand-200 bg-brand-50';
    if (m.is_system) return 'border-slate-200 bg-slate-50';
    return m.is_mine ? 'border-brand-200 bg-brand-50/60' : 'border-slate-200 bg-white';
}
</script>

<template>
    <DashboardLayout :title="`Dispute ${dispute.reference}`" heading="Dispute">
        <Breadcrumb
            :items="[
                { label: 'Disputes', url: route('dashboard.disputes') },
                { label: dispute.reference },
            ]"
        />

        <div class="grid gap-4 lg:grid-cols-[1fr_22rem]">
            <!-- ── Thread ─────────────────────────────────────────── -->
            <div class="flex flex-col gap-3">
                <div class="card-p">
                    <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h2 class="section-title">{{ dispute.reference }} — {{ dispute.reason }}</h2>
                            <p class="section-sub">
                                Opened by {{ dispute.opener }} on {{ dispute.opened }} ·
                                you are the <span class="font-medium capitalize">{{ role ?? 'observer' }}</span>
                            </p>
                        </div>
                        <StatusBadge :status="dispute.status" />
                    </div>
                    <div v-if="dispute.description" class="rounded-lg bg-rose-50 p-3">
                        <p class="mb-1 font-semibold text-rose-900">What the buyer reported</p>
                        <p class="whitespace-pre-line text-sm text-rose-800">{{ dispute.description }}</p>
                    </div>
                </div>

                <div class="card-p">
                    <h2 class="section-title mb-3">Conversation</h2>

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
                                <span v-if="m.is_internal" class="badge-violet">Internal</span>
                                <span class="ml-auto text-slate-500">{{ m.at }}</span>
                            </div>
                            <p v-if="m.body" class="whitespace-pre-line text-sm text-slate-900">{{ m.body }}</p>

                            <!-- Evidence lives behind an authorizing route, so a plain anchor. -->
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

                    <!-- Composer -->
                    <form v-if="can.message" class="mt-3 flex flex-col gap-2" @submit.prevent="sendMessage">
                        <textarea
                            v-model="messageForm.body"
                            rows="3"
                            class="textarea text-sm"
                            :class="messageForm.errors.body && 'input-error'"
                            maxlength="5000"
                            placeholder="Write to the other party…"
                        ></textarea>
                        <p v-if="messageForm.errors.body" class="field-error">{{ messageForm.errors.body }}</p>
                        <div class="flex justify-end">
                            <button
                                type="submit"
                                class="btn-primary"
                                :disabled="messageForm.processing || messageForm.body.trim().length < 2"
                            >
                                {{ messageForm.processing ? 'Sending…' : 'Send message' }}
                            </button>
                        </div>
                    </form>
                    <p v-else class="mt-3 text-xs text-slate-500">
                        This dispute is closed — the thread is read-only.
                    </p>
                </div>

                <!-- Evidence upload -->
                <div v-if="can.evidence" class="card-p">
                    <h2 class="section-title mb-1">Add evidence</h2>
                    <p class="section-sub mb-2">
                        Screenshots, receipts or logs. Images, PDF, TXT or ZIP up to 10 MB. Only the two of you
                        and our staff can open them.
                    </p>
                    <form class="flex flex-col gap-2" @submit.prevent="uploadEvidence">
                        <input
                            ref="fileInput"
                            type="file"
                            class="input text-sm"
                            :class="evidenceForm.errors.file && 'input-error'"
                            accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.txt,.zip"
                            @change="pickFile"
                        />
                        <p v-if="evidenceForm.errors.file" class="field-error">{{ evidenceForm.errors.file }}</p>
                        <input
                            v-model="evidenceForm.note"
                            type="text"
                            class="input text-sm"
                            maxlength="1000"
                            placeholder="What does this show? (optional)"
                        />
                        <div class="flex items-center justify-between gap-2">
                            <span v-if="evidenceForm.progress" class="text-xs text-slate-500">
                                Uploading… {{ evidenceForm.progress.percentage }}%
                            </span>
                            <button
                                type="submit"
                                class="btn-outline ml-auto"
                                :disabled="evidenceForm.processing || !evidenceForm.file"
                            >
                                Attach evidence
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ── Sidebar ────────────────────────────────────────── -->
            <div class="flex flex-col gap-3">
                <!-- Order -->
                <div class="card-p">
                    <h2 class="section-title mb-2">Order</h2>
                    <dl class="flex flex-col gap-2 text-sm">
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Number</dt>
                            <dd class="font-mono text-xs">
                                <Link v-if="order.url" :href="order.url" class="text-brand-600">
                                    {{ order.number }}
                                </Link>
                                <span v-else>{{ order.number }}</span>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Asset</dt>
                            <dd class="max-w-[60%] truncate text-right font-medium">{{ order.asset_title }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Buyer</dt>
                            <dd>{{ order.buyer }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Seller</dt>
                            <dd>{{ order.seller }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Total paid</dt>
                            <dd class="money font-bold">{{ order.buyer_total }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- The proposal on the table -->
                <div v-if="pending" class="card-p ring-2 ring-amber-400/60">
                    <h2 class="section-title mb-1">Proposed settlement</h2>
                    <p class="section-sub mb-2">
                        {{ pending.proposer }} ({{ pending.role }}) suggested this on {{ pending.at }}.
                    </p>
                    <div class="mb-2 rounded-lg bg-amber-50 p-3 text-sm">
                        <p class="font-semibold text-amber-900">{{ pending.type_label }}</p>
                        <p v-if="pending.amount" class="money mt-1 font-bold text-amber-900">
                            {{ pending.amount }}
                        </p>
                        <p v-if="pending.note" class="mt-1 whitespace-pre-line text-amber-800">
                            {{ pending.note }}
                        </p>
                    </div>

                    <!-- Accepting executes it: money moves and the dispute closes. -->
                    <template v-if="can.respond">
                        <button
                            type="button"
                            class="btn-success w-full"
                            :disabled="processing"
                            @click="post('dashboard.disputes.proposal.accept', pending.id)"
                        >
                            Accept and settle
                        </button>
                        <form
                            class="mt-2 flex flex-col gap-2"
                            @submit.prevent="
                                post('dashboard.disputes.proposal.decline', pending.id, { note: declineNote })
                            "
                        >
                            <textarea
                                v-model="declineNote"
                                rows="2"
                                class="textarea text-sm"
                                maxlength="1000"
                                placeholder="Why not? (optional)"
                            ></textarea>
                            <button type="submit" class="btn-outline w-full" :disabled="processing">
                                Decline
                            </button>
                        </form>
                    </template>

                    <button
                        v-else-if="can.withdraw"
                        type="button"
                        class="btn-outline w-full"
                        :disabled="processing"
                        @click="post('dashboard.disputes.proposal.withdraw', pending.id)"
                    >
                        Withdraw my proposal
                    </button>

                    <p v-else-if="pending.awaiting" class="text-xs text-slate-500">
                        Waiting on the {{ pending.awaiting }}.
                    </p>
                </div>

                <!-- Propose a settlement -->
                <div v-if="can.propose" class="card-p">
                    <h2 class="section-title mb-1">Propose a settlement</h2>
                    <p class="section-sub mb-2">
                        Settle it between yourselves and no one has to arbitrate. Only one proposal stands at a
                        time — a new one replaces yours.
                    </p>
                    <form class="flex flex-col gap-2" @submit.prevent="propose">
                        <select
                            v-model="proposalForm.type"
                            class="select text-sm"
                            :class="proposalForm.errors.type && 'input-error'"
                        >
                            <option value="" disabled>Choose an outcome…</option>
                            <option v-for="o in options" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <p v-if="proposalForm.errors.type" class="field-error">{{ proposalForm.errors.type }}</p>

                        <div v-if="needsAmount" class="relative">
                            <span
                                class="pointer-events-none absolute inset-y-0 left-2 flex items-center font-mono text-sm text-slate-400"
                            >
                                ৳
                            </span>
                            <input
                                v-model="proposalForm.amount_bdt"
                                type="number"
                                min="1"
                                step="1"
                                :max="order.buyer_total_bdt"
                                class="input pl-6 text-sm"
                                placeholder="Refund amount"
                            />
                        </div>
                        <p v-if="proposalForm.errors.amount_bdt" class="field-error">
                            {{ proposalForm.errors.amount_bdt }}
                        </p>

                        <textarea
                            v-model="proposalForm.note"
                            rows="2"
                            class="textarea text-sm"
                            maxlength="1000"
                            placeholder="Explain the offer (optional)"
                        ></textarea>
                        <button type="submit" class="btn-primary w-full" :disabled="!canPropose">
                            {{ proposalForm.processing ? 'Sending…' : 'Send proposal' }}
                        </button>
                    </form>
                </div>

                <!-- Escalate -->
                <div v-if="can.escalate" class="card-p">
                    <h2 class="section-title mb-1">Ask an admin to decide</h2>
                    <p class="section-sub mb-2">
                        Use this once you have tried to settle it. A staff member takes over and their decision
                        is final.
                    </p>
                    <form
                        class="flex flex-col gap-2"
                        @submit.prevent="
                            post('dashboard.disputes.escalate', dispute.id, { note: escalateNote })
                        "
                    >
                        <textarea
                            v-model="escalateNote"
                            rows="2"
                            class="textarea text-sm"
                            maxlength="1000"
                            placeholder="Anything the admin should know first? (optional)"
                        ></textarea>
                        <button type="submit" class="btn-warning w-full" :disabled="processing">
                            Escalate to admin
                        </button>
                    </form>
                </div>

                <div v-else-if="dispute.is_escalated" class="card-p">
                    <h2 class="section-title mb-1">With our team</h2>
                    <p class="section-sub">
                        An admin is reviewing this dispute. You will be notified when they decide.
                    </p>
                </div>

                <!-- Withdraw the claim -->
                <div v-if="can.cancel" class="card-p">
                    <h2 class="section-title mb-1">Withdraw the dispute</h2>
                    <p class="section-sub mb-2">
                        Closes it with no refund and releases the order back to its normal flow.
                    </p>
                    <form
                        class="flex flex-col gap-2"
                        @submit.prevent="post('dashboard.disputes.cancel', dispute.id, { note: cancelNote })"
                    >
                        <textarea
                            v-model="cancelNote"
                            rows="2"
                            class="textarea text-sm"
                            maxlength="1000"
                            placeholder="Reason (optional)"
                        ></textarea>
                        <button type="submit" class="btn-ghost w-full" :disabled="processing">
                            Withdraw dispute
                        </button>
                    </form>
                </div>

                <!-- Outcome, once there is one -->
                <div v-if="!dispute.is_active" class="card-p">
                    <h2 class="section-title mb-2">Outcome</h2>
                    <dl class="flex flex-col gap-2 text-sm">
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Result</dt>
                            <dd class="font-medium">{{ dispute.status_label }}</dd>
                        </div>
                        <div v-if="dispute.resolution_type" class="flex justify-between gap-2">
                            <dt class="text-slate-500">Type</dt>
                            <dd class="font-medium">{{ dispute.resolution_type }}</dd>
                        </div>
                        <div v-if="dispute.resolution_amount" class="flex justify-between gap-2">
                            <dt class="text-slate-500">Amount</dt>
                            <dd class="money font-bold">{{ dispute.resolution_amount }}</dd>
                        </div>
                        <div v-if="dispute.resolved_at" class="flex justify-between gap-2">
                            <dt class="text-slate-500">Closed</dt>
                            <dd>{{ dispute.resolved_at }}</dd>
                        </div>
                    </dl>
                    <p v-if="dispute.resolution_note" class="mt-2 rounded-lg bg-slate-50 p-2 text-xs text-slate-500">
                        {{ dispute.resolution_note }}
                    </p>
                </div>

                <!-- Every proposal ever made, so neither side can rewrite history -->
                <div v-if="history.length" class="card-p">
                    <h2 class="section-title mb-2">Proposal history</h2>
                    <div class="flex flex-col gap-2">
                        <div
                            v-for="h in history"
                            :key="h.id"
                            class="rounded-lg border border-slate-200 p-2 text-xs"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold text-slate-900">{{ h.type_label }}</span>
                                <span class="badge-slate capitalize">{{ h.status }}</span>
                            </div>
                            <p class="mt-1 text-slate-500">
                                {{ h.proposer }} ({{ h.role }}) · {{ h.at }}
                                <span v-if="h.amount" class="money font-semibold text-slate-900">
                                    · {{ h.amount }}
                                </span>
                                <span v-if="h.executed" class="text-mint-600">· executed</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
