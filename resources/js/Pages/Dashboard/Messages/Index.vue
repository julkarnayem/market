<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
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
    order_number: string | null;
    asset_title: string;
    order_status: string;
    order_url: string | null;
}

const props = defineProps<{
    conversations: Paginated<ConversationRow>;
    selectedId: number | null;
    activeConversation: ActiveConversation | null;
    messages: MessageRow[];
    isRealtimeReady: boolean;
}>();

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
        router.reload({
            only: ['messages'],
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
                    <h2 class="text-sm font-semibold text-slate-900">Order Messages</h2>
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
                        <p class="mt-1 text-xs text-slate-500">Messages appear here when orders are created.</p>
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
                    <!-- Order context header -->
                    <div class="flex items-center gap-3 border-b border-slate-200 bg-white px-3 py-2">
                        <Link :href="route('dashboard.messages')" class="btn-ghost btn-sm p-1 lg:hidden">←</Link>
                        <span
                            class="grid h-9 w-9 flex-shrink-0 place-items-center rounded-full bg-brand-100 text-sm font-bold text-brand-700"
                        >
                            {{ activeConversation.other_initial }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ activeConversation.other_name }}</p>
                            <p class="truncate text-xs text-slate-500">
                                <template v-if="activeConversation.asset_title">{{ activeConversation.asset_title }} · </template>{{ activeConversation.order_number }}
                            </p>
                        </div>
                        <div class="flex flex-shrink-0 items-center gap-2">
                            <StatusBadge :status="activeConversation.order_status" />
                            <a
                                v-if="activeConversation.order_url"
                                :href="activeConversation.order_url"
                                class="btn-ghost btn-sm text-xs"
                            >
                                View order →
                            </a>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div ref="msgArea" class="flex flex-1 flex-col gap-2 overflow-y-auto p-3">
                        <template v-for="m in messages" :key="m.id">
                            <!-- System notice -->
                            <div v-if="m.is_system" class="my-1 text-center">
                                <span class="rounded-full bg-slate-200 px-2 py-1 text-xs text-slate-500">{{ m.body }}</span>
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
                        <form class="flex items-end gap-2" @submit.prevent="send">
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
                    <p class="mt-1 text-sm text-slate-500">Choose an order conversation from the left.</p>
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
