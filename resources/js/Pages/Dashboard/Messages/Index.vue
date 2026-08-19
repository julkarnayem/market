<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import CountdownTimer from '@/Components/CountdownTimer.vue';
import Pagination from '@/Components/Pagination.vue';
import type { Paginated } from '@/types';

interface ConversationRow {
    id: number;
    other_name: string;
    other_initial: string;
    subtitle: string;
    order_status: string | null;
    order_status_label: string;
    unread: number;
    last_human: string | null;
}
interface Attachment {
    name: string;
    url: string;
}
interface ReplyTo {
    sender_name: string;
    excerpt: string;
}
interface MessageRow {
    id: number;
    mine: boolean;
    is_system: boolean;
    /** text | attachment | system | custom_offer | custom_offer_event */
    type: string;
    /** Set on custom-offer cards; keys into `offers`. */
    offer_id: number | null;
    sender_name: string;
    sender_initial: string;
    body: string;
    time: string;
    attachment: Attachment | null;
    reply_to: ReplyTo | null;
}
interface ActiveConversation {
    id: number;
    other_name: string;
    other_initial: string;
    other_online: boolean;
    other_seen: string | null;
    order_number: string | null;
    asset_title: string;
    order_status: string;
    order_url: string | null;
}
interface ListingContext {
    title: string;
    price_formatted: string;
    inventory_type: 'single' | 'multiple' | 'unlimited';
    inventory_label: string;
    max_quantity: number;
    cover: string | null;
    url: string;
}
/**
 * A custom offer, as the chat card needs it. Every capability is decided
 * server-side by OfferPolicy — the buttons below only render what the server
 * already said this viewer may do.
 */
interface OfferCard {
    id: number;
    amount_formatted: string;
    quantity: number;
    delivery_days: number | null;
    note: string | null;
    status: string;
    status_label: string;
    is_pending: boolean;
    mine: boolean;
    expires_in_seconds: number;
    can_accept: boolean;
    can_decline: boolean;
    can_cancel: boolean;
    /** The buyer's accept goes straight to checkout; the seller's does not. */
    accept_is_pay: boolean;
    can_pay: boolean;
    pay_url: string | null;
    accept_url: string;
    reject_url: string;
    cancel_url: string;
}

const props = defineProps<{
    conversations: Paginated<ConversationRow>;
    selectedId: number | null;
    activeConversation: ActiveConversation | null;
    listing: ListingContext | null;
    messages: MessageRow[];
    offers: Record<number, OfferCard>;
    canOffer: boolean;
    isRealtimeReady: boolean;
}>();

const offerFor = (id: number | null): OfferCard | null => (id === null ? null : props.offers[id] ?? null);

// ── Scroll to newest ──────────────────────────────────────────────
const msgArea = ref<HTMLElement | null>(null);
function scrollToBottom() {
    nextTick(() => {
        const el = msgArea.value;
        if (el) el.scrollTop = el.scrollHeight;
    });
}

// ── Composer ──────────────────────────────────────────────────────
const fileInput = ref<HTMLInputElement | null>(null);
const form = useForm<{ body: string; attachment: File | null; client_message_id: string }>({
    body: '',
    attachment: null,
    client_message_id: '',
});

function onFile(e: Event) {
    const t = e.target as HTMLInputElement;
    form.attachment = t.files?.[0] ?? null;
}

function autoResize(e: Event) {
    const el = e.target as HTMLTextAreaElement;
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 128) + 'px';
}

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send();
    }
}

function send() {
    if (!props.activeConversation) return;
    if (!form.body.trim() && !form.attachment) return;
    // Idempotency key so a double-submit can't create two messages server-side.
    form.client_message_id = crypto.randomUUID?.() ?? String(Date.now());
    form.post(route('dashboard.messages.send', props.activeConversation.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            form.reset();
            if (fileInput.value) fileInput.value.value = '';
            scrollToBottom();
        },
    });
}

// ── Custom offers ─────────────────────────────────────────────────
// Chat-only, private to this thread, and never part of a listing's public bid
// history. Both parties can send one; whoever did not send it responds.
const offerOpen = ref(false);
const offerForm = useForm<{
    amount_bdt: string;
    quantity: number;
    delivery_days: string;
    note: string;
}>({ amount_bdt: '', quantity: 1, delivery_days: '', note: '' });

function submitOffer() {
    if (!props.activeConversation) return;
    offerForm.post(route('dashboard.messages.offers.store', props.activeConversation.id), {
        preserveScroll: true,
        onSuccess: () => {
            offerForm.reset();
            offerOpen.value = false;
            scrollToBottom();
        },
    });
}

// One in-flight action at a time: accepting and declining the same offer in
// quick succession would otherwise both post.
const offerBusy = ref(false);
function offerAction(url: string) {
    if (offerBusy.value) return;
    offerBusy.value = true;
    router.post(url, {}, { preserveScroll: true, onFinish: () => (offerBusy.value = false) });
}

// ── Polling fallback (only when broadcasting isn't configured) ─────
let pollTimer: ReturnType<typeof setInterval> | undefined;
function stopPolling() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = undefined;
    }
}
function startPolling() {
    stopPolling();
    if (props.isRealtimeReady || props.selectedId === null) return;
    pollTimer = setInterval(() => {
        if (document.hidden) return;
        const prev = props.messages.length;
        // `offers` rides along with `messages`: accepting an offer changes card
        // state, and the status line it posts is a new message — both have to
        // land without a page refresh.
        router.reload({
            only: ['messages', 'offers', 'canOffer'],
            onSuccess: () => {
                if (props.messages.length > prev) scrollToBottom();
            },
        });
    }, 5000);
}

onMounted(() => {
    scrollToBottom();
    startPolling();
});
onBeforeUnmount(stopPolling);

// Switching conversations reuses this component, so onMounted won't fire again.
watch(
    () => props.selectedId,
    () => {
        offerOpen.value = false;
        offerForm.reset();
        scrollToBottom();
        startPolling();
    },
);

// ── Report modal ──────────────────────────────────────────────────
const REPORT_REASONS = [
    { value: 'scam', label: 'Scam' },
    { value: 'abuse', label: 'Abuse' },
    { value: 'threat', label: 'Threat' },
    { value: 'spam', label: 'Spam' },
    { value: 'prohibited', label: 'Prohibited content' },
    { value: 'other', label: 'Other' },
] as const;

const reportTargetId = ref<number | null>(null);
const reportForm = useForm<{ reason: string; description: string }>({ reason: 'scam', description: '' });

function openReport(id: number) {
    reportForm.reset();
    reportForm.clearErrors();
    reportTargetId.value = id;
}
function submitReport() {
    if (reportTargetId.value === null) return;
    reportForm.post(route('messages.report', reportTargetId.value), {
        preserveScroll: true,
        onSuccess: () => {
            reportTargetId.value = null;
        },
    });
}
</script>

<template>
    <DashboardLayout title="Messages" heading="Messages">
        <div
            class="flex h-[calc(100vh-13rem)] min-h-[28rem] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
        >
            <!-- Conversation list -->
            <div
                class="flex w-full flex-col border-slate-200 lg:w-[22rem] lg:border-r"
                :class="selectedId !== null ? 'hidden lg:flex' : 'flex'"
            >
                <div class="border-b border-slate-100 px-4 py-3">
                    <h2 class="text-sm font-semibold text-slate-900">Conversations</h2>
                </div>
                <div class="flex-1 divide-y divide-slate-100 overflow-y-auto">
                    <template v-if="conversations.data.length">
                        <Link
                            v-for="c in conversations.data"
                            :key="c.id"
                            :href="route('dashboard.messages', { conversation: c.id })"
                            preserve-scroll
                            class="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-slate-50"
                            :class="selectedId === c.id && 'bg-brand-50'"
                        >
                            <span
                                class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700"
                            >
                                {{ c.other_initial }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-1">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ c.other_name }}</p>
                                    <div class="flex flex-shrink-0 items-center gap-1">
                                        <span
                                            v-if="c.unread > 0"
                                            class="grid h-5 min-w-[20px] place-items-center rounded-full bg-brand-600 px-1 text-xs font-bold text-white"
                                        >
                                            {{ c.unread }}
                                        </span>
                                        <span class="text-xs text-slate-400">{{ c.last_human }}</span>
                                    </div>
                                </div>
                                <p class="truncate text-xs text-slate-500">{{ c.subtitle }}</p>
                                <span
                                    v-if="c.order_status_label"
                                    class="mt-0.5 inline-flex text-[10px]"
                                    :class="c.order_status === 'completed' ? 'badge-mint' : 'badge-slate'"
                                >
                                    {{ c.order_status_label }}
                                </span>
                            </div>
                        </Link>
                    </template>
                    <div v-else class="flex flex-col items-center justify-center px-3 py-10 text-center">
                        <span class="mb-2 text-3xl">✉️</span>
                        <p class="text-sm font-semibold text-slate-900">No messages yet</p>
                        <p class="mt-1 text-xs text-slate-500">
                            Start one from a listing with Contact Seller, or wait for an order.
                        </p>
                    </div>
                </div>
                <div v-if="conversations.data.length" class="border-t border-slate-100 p-2">
                    <Pagination :links="conversations.links" :total="conversations.total" />
                </div>
            </div>

            <!-- Message pane -->
            <div
                class="flex-1 flex-col bg-slate-50"
                :class="selectedId !== null ? 'flex' : 'hidden lg:flex'"
            >
                <template v-if="activeConversation">
                    <!-- Header: who you are talking to -->
                    <div class="flex items-center gap-3 border-b border-slate-200 bg-white px-3 py-2">
                        <Link :href="route('dashboard.messages')" class="btn-ghost btn-sm p-1 lg:hidden">←</Link>
                        <span
                            class="grid h-9 w-9 flex-shrink-0 place-items-center rounded-full bg-brand-100 text-sm font-bold text-brand-700"
                        >
                            {{ activeConversation.other_initial }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ activeConversation.other_name }}</p>
                            <p class="flex items-center gap-1 truncate text-xs">
                                <span
                                    class="inline-block h-2 w-2 flex-shrink-0 rounded-full"
                                    :class="activeConversation.other_online ? 'bg-mint-500' : 'bg-slate-300'"
                                    aria-hidden="true"
                                ></span>
                                <span :class="activeConversation.other_online ? 'text-mint-700' : 'text-slate-500'">
                                    {{
                                        activeConversation.other_online
                                            ? 'Online'
                                            : activeConversation.other_seen
                                              ? `Active ${activeConversation.other_seen} ago`
                                              : 'Offline'
                                    }}
                                </span>
                            </p>
                        </div>
                        <div class="flex flex-shrink-0 items-center gap-2">
                            <span v-if="activeConversation.order_number" class="hidden text-xs text-slate-400 sm:inline">
                                {{ activeConversation.order_number }}
                            </span>
                            <StatusBadge v-if="activeConversation.order_url" :status="activeConversation.order_status" />
                            <a
                                v-if="activeConversation.order_url"
                                :href="activeConversation.order_url"
                                class="btn-ghost btn-sm text-xs"
                            >
                                View order →
                            </a>
                        </div>
                    </div>

                    <!-- Listing context: what this thread is about. Survives the
                         order being created, because the order carries the asset. -->
                    <div
                        v-if="listing"
                        class="flex items-center gap-3 border-b border-slate-200 bg-white/70 px-3 py-2"
                    >
                        <img
                            v-if="listing.cover"
                            :src="listing.cover"
                            :alt="listing.title"
                            class="h-10 w-10 flex-shrink-0 rounded-lg object-cover"
                            loading="lazy"
                        />
                        <span
                            v-else
                            class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-lg bg-slate-100 text-base"
                            aria-hidden="true"
                        >
                            🧩
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-900">{{ listing.title }}</p>
                            <p class="text-xs text-slate-500">
                                <span class="money font-semibold text-slate-700">{{ listing.price_formatted }}</span>
                                · {{ listing.inventory_label }}
                            </p>
                        </div>
                        <a :href="listing.url" class="btn-outline btn-sm flex-shrink-0 text-xs">View Listing</a>
                    </div>

                    <!-- Messages -->
                    <div ref="msgArea" class="flex flex-1 flex-col gap-2 overflow-y-auto p-3">
                        <template v-for="m in messages" :key="m.id">
                            <!-- System notice, and the one-line offer event trail -->
                            <div v-if="m.is_system || m.type === 'custom_offer_event'" class="my-1 text-center">
                                <span class="rounded-full bg-slate-200 px-2 py-1 text-xs text-slate-500">{{ m.body }}</span>
                            </div>

                            <!-- Custom offer card -->
                            <div
                                v-else-if="m.type === 'custom_offer' && offerFor(m.offer_id)"
                                class="flex"
                                :class="m.mine ? 'justify-end' : 'items-end gap-2'"
                            >
                                <span
                                    v-if="!m.mine"
                                    class="mb-1 grid h-7 w-7 flex-shrink-0 place-items-center rounded-full bg-slate-300 text-xs font-bold text-slate-700"
                                >
                                    {{ m.sender_initial }}
                                </span>
                                <div
                                    v-if="offerFor(m.offer_id)"
                                    class="w-full max-w-[19rem] rounded-2xl border bg-white p-3 shadow-sm"
                                    :class="offerFor(m.offer_id)!.is_pending ? 'border-brand-200' : 'border-slate-200'"
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">Custom Offer</p>
                                        <StatusBadge :status="offerFor(m.offer_id)!.status" />
                                    </div>
                                    <p class="money mt-1.5 text-2xl font-bold text-slate-900">
                                        {{ offerFor(m.offer_id)!.amount_formatted }}
                                    </p>
                                    <dl class="mt-1 flex flex-col gap-0.5 text-xs text-slate-500">
                                        <div v-if="offerFor(m.offer_id)!.quantity > 1" class="flex justify-between">
                                            <dt>Quantity</dt>
                                            <dd>{{ offerFor(m.offer_id)!.quantity }}</dd>
                                        </div>
                                        <div v-if="offerFor(m.offer_id)!.delivery_days" class="flex justify-between">
                                            <dt>Delivery</dt>
                                            <dd>{{ offerFor(m.offer_id)!.delivery_days }} day(s)</dd>
                                        </div>
                                        <div v-if="offerFor(m.offer_id)!.is_pending" class="flex justify-between">
                                            <dt>Expires in</dt>
                                            <dd><CountdownTimer :seconds="offerFor(m.offer_id)!.expires_in_seconds" /></dd>
                                        </div>
                                    </dl>
                                    <p v-if="offerFor(m.offer_id)!.note" class="mt-1.5 whitespace-pre-line text-xs text-slate-600">
                                        {{ offerFor(m.offer_id)!.note }}
                                    </p>

                                    <!-- Responding: only the party who did not send it -->
                                    <div v-if="offerFor(m.offer_id)!.can_accept" class="mt-2.5 flex gap-2">
                                        <button
                                            type="button"
                                            class="btn-primary btn-sm flex-1"
                                            :disabled="offerBusy"
                                            @click="offerAction(offerFor(m.offer_id)!.accept_url)"
                                        >
                                            {{ offerFor(m.offer_id)!.accept_is_pay ? 'Accept & Pay' : 'Accept' }}
                                        </button>
                                        <button
                                            type="button"
                                            class="btn-outline btn-sm"
                                            :disabled="offerBusy"
                                            @click="offerAction(offerFor(m.offer_id)!.reject_url)"
                                        >
                                            Decline
                                        </button>
                                    </div>
                                    <button
                                        v-else-if="offerFor(m.offer_id)!.can_cancel"
                                        type="button"
                                        class="btn-ghost btn-sm mt-2.5 w-full"
                                        :disabled="offerBusy"
                                        @click="offerAction(offerFor(m.offer_id)!.cancel_url)"
                                    >
                                        Withdraw offer
                                    </button>

                                    <!-- Accepted: the buyer pays, in both directions.
                                         A seller whose own offer was accepted has no
                                         Pay button here — can_pay says so. -->
                                    <div v-if="offerFor(m.offer_id)!.status === 'accepted'" class="mt-2.5">
                                        <p class="text-xs font-semibold text-mint-700">Offer Accepted ✅</p>
                                        <a
                                            v-if="offerFor(m.offer_id)!.pay_url"
                                            :href="offerFor(m.offer_id)!.pay_url!"
                                            class="btn-primary btn-sm mt-1.5 w-full"
                                        >
                                            Pay Now — {{ offerFor(m.offer_id)!.amount_formatted }} →
                                        </a>
                                        <p v-else-if="!offerFor(m.offer_id)!.can_pay" class="mt-1 text-xs text-slate-500">
                                            Waiting for the buyer to pay.
                                        </p>
                                    </div>

                                    <p class="mt-1.5 text-right text-xs text-slate-400">{{ m.time }}</p>
                                </div>
                            </div>

                            <!-- Normal message -->
                            <div v-else class="flex" :class="m.mine ? 'justify-end' : 'items-end gap-2'">
                                <span
                                    v-if="!m.mine"
                                    class="mb-1 grid h-7 w-7 flex-shrink-0 place-items-center rounded-full bg-slate-300 text-xs font-bold text-slate-700"
                                >
                                    {{ m.sender_initial }}
                                </span>
                                <div class="max-w-[75%]">
                                    <div
                                        v-if="m.reply_to"
                                        class="mb-1 rounded-t-xl bg-slate-200/70 px-2 py-1 text-xs text-slate-500"
                                    >
                                        <span class="font-semibold">{{ m.reply_to.sender_name }}</span>: {{ m.reply_to.excerpt }}
                                    </div>
                                    <div
                                        class="rounded-2xl px-4 py-2.5"
                                        :class="m.mine ? 'rounded-tr-sm bg-brand-600 text-white' : 'rounded-tl-sm bg-white text-slate-800 shadow-sm'"
                                    >
                                        <p v-if="m.body" class="whitespace-pre-line text-sm">{{ m.body }}</p>
                                        <a
                                            v-if="m.attachment"
                                            :href="m.attachment.url"
                                            class="mt-2 flex items-center gap-1.5 text-xs hover:underline"
                                            :class="m.mine ? 'text-brand-100' : 'text-brand-700'"
                                        >
                                            📎 {{ m.attachment.name }}
                                        </a>
                                        <div
                                            class="mt-1 flex items-center justify-between gap-3 text-xs"
                                            :class="m.mine ? 'text-brand-100' : 'text-slate-400'"
                                        >
                                            <span>{{ m.time }}</span>
                                            <button
                                                v-if="!m.mine"
                                                type="button"
                                                class="hover:underline"
                                                @click="openReport(m.id)"
                                            >
                                                Report
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Composer -->
                    <div class="border-t border-slate-200 bg-white px-3 py-2">
                        <!-- Custom offer form. Either party can send one, on any
                             inventory type; the server re-derives buyer, seller and
                             listing from the thread, so nothing here can re-point it. -->
                        <form
                            v-if="offerOpen && canOffer"
                            class="mb-2 flex flex-col gap-2 rounded-xl border border-brand-200 bg-brand-50/60 p-3"
                            @submit.prevent="submitOffer"
                        >
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">New custom offer</p>
                                <button type="button" class="text-xs text-slate-500 hover:underline" @click="offerOpen = false">
                                    Cancel
                                </button>
                            </div>
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <label for="offer-amount" class="mb-1 block text-xs text-slate-600">Amount (৳)</label>
                                    <input
                                        id="offer-amount"
                                        v-model="offerForm.amount_bdt"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        class="input text-sm"
                                        required
                                    />
                                    <p v-if="offerForm.errors.amount_bdt" class="field-error">{{ offerForm.errors.amount_bdt }}</p>
                                </div>
                                <div v-if="listing && listing.inventory_type !== 'single'" class="w-24">
                                    <label for="offer-qty" class="mb-1 block text-xs text-slate-600">Qty</label>
                                    <input
                                        id="offer-qty"
                                        v-model.number="offerForm.quantity"
                                        type="number"
                                        min="1"
                                        :max="listing.max_quantity"
                                        class="input text-sm"
                                    />
                                    <p v-if="offerForm.errors.quantity" class="field-error">{{ offerForm.errors.quantity }}</p>
                                </div>
                                <div class="w-28">
                                    <label for="offer-days" class="mb-1 block text-xs text-slate-600">Delivery (days)</label>
                                    <input
                                        id="offer-days"
                                        v-model="offerForm.delivery_days"
                                        type="number"
                                        min="1"
                                        max="365"
                                        class="input text-sm"
                                        placeholder="—"
                                    />
                                    <p v-if="offerForm.errors.delivery_days" class="field-error">{{ offerForm.errors.delivery_days }}</p>
                                </div>
                            </div>
                            <textarea
                                v-model="offerForm.note"
                                rows="2"
                                class="textarea text-sm"
                                maxlength="1000"
                                placeholder="Add a note (optional)"
                            ></textarea>
                            <p v-if="offerForm.errors.note" class="field-error">{{ offerForm.errors.note }}</p>
                            <button type="submit" class="btn-primary btn-sm" :disabled="offerForm.processing">
                                {{ offerForm.processing ? 'Sending…' : 'Send offer' }}
                            </button>
                        </form>

                        <form class="flex items-end gap-2" @submit.prevent="send">
                            <label class="btn-ghost btn-sm flex-shrink-0 cursor-pointer" title="Attach file">
                                📎
                                <input
                                    ref="fileInput"
                                    type="file"
                                    class="hidden"
                                    accept=".jpg,.jpeg,.png,.webp,.pdf,.txt"
                                    @change="onFile"
                                />
                            </label>
                            <button
                                v-if="canOffer"
                                type="button"
                                class="btn-outline btn-sm flex-shrink-0 whitespace-nowrap text-xs"
                                :class="offerOpen && 'bg-brand-50'"
                                @click="offerOpen = !offerOpen"
                            >
                                Custom Offer
                            </button>
                            <div class="flex-1">
                                <textarea
                                    v-model="form.body"
                                    rows="1"
                                    placeholder="Type a message…"
                                    class="max-h-32 w-full resize-none overflow-y-auto rounded-lg border border-slate-200 px-2 py-2 text-sm"
                                    maxlength="5000"
                                    @keydown="onKeydown"
                                    @input="autoResize"
                                ></textarea>
                            </div>
                            <button
                                type="submit"
                                class="btn-primary btn-sm flex-shrink-0 px-3"
                                :disabled="form.processing || (!form.body.trim() && !form.attachment)"
                            >
                                {{ form.processing ? '…' : 'Send' }}
                            </button>
                        </form>
                        <p v-if="form.attachment" class="mt-1 text-xs text-slate-500">📎 {{ form.attachment.name }}</p>
                        <p v-if="form.errors.body" class="field-error">{{ form.errors.body }}</p>
                        <p v-if="form.errors.attachment" class="field-error">{{ form.errors.attachment }}</p>
                        <p class="mt-1 text-xs text-slate-400">Enter to send · Shift+Enter for new line · Max 5000 chars</p>
                        <p v-if="!isRealtimeReady" class="mt-1 text-xs text-amber-600">
                            ⚡ Auto-refresh every 5s (real-time not configured)
                        </p>
                    </div>
                </template>

                <!-- Nothing selected -->
                <div v-else class="flex flex-1 flex-col items-center justify-center p-4 text-center">
                    <span class="mb-3 text-5xl">✉️</span>
                    <p class="font-semibold text-slate-900">Select a conversation</p>
                    <p class="mt-1 text-sm text-slate-500">Choose a conversation from the left.</p>
                </div>
            </div>
        </div>

        <!-- Report message modal -->
        <div v-if="reportTargetId !== null" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-slate-900/50" @click="reportTargetId = null"></div>
            <div class="relative mx-3 w-full max-w-sm rounded-2xl bg-white p-4 shadow-xl">
                <h3 class="mb-2 font-semibold text-slate-900">Report message</h3>
                <form class="flex flex-col gap-2" @submit.prevent="submitReport">
                    <select v-model="reportForm.reason" class="select text-sm">
                        <option v-for="r in REPORT_REASONS" :key="r.value" :value="r.value">{{ r.label }}</option>
                    </select>
                    <textarea
                        v-model="reportForm.description"
                        rows="3"
                        class="textarea text-sm"
                        placeholder="Additional details (optional)"
                        maxlength="1000"
                    ></textarea>
                    <div class="flex gap-2">
                        <button type="submit" class="btn-primary flex-1" :disabled="reportForm.processing">Submit report</button>
                        <button type="button" class="btn-outline" @click="reportTargetId = null">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </DashboardLayout>
</template>
