<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

interface OrderData {
    id: number;
    order_number: string;
    status: string;
    payment_status: string;
    delivery_status: string;
    created_full: string;
    asset_title: string;
    asset_category: string | null;
    asset_cover_url: string | null;
    unit_price_formatted: string;
    quantity: number;
    subtotal_formatted: string;
    buyer_fee_amount: number;
    buyer_fee_formatted: string;
    buyer_total_formatted: string;
    seller_fee_percent: string;
    seller_fee_formatted: string;
    seller_earning_formatted: string;
    earning_locked: boolean;
    earning_available_at: string | null;
    can_be_delivered: boolean;
    can_be_completed: boolean;
    can_open_dispute: boolean;
    auto_complete_human: string | null;
}

interface TimelineEntry { id: number; to_status: string; note: string | null; at: string | null }
interface ChatMessage { id: number; body: string; mine: boolean; time: string }
interface Participant { role: string; name: string; initial: string }
/** The order's current dispute, if it has one. */
interface DisputeRef {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    is_active: boolean;
    url: string;
}

const props = defineProps<{
    order: OrderData;
    isBuyer: boolean;
    isSeller: boolean;
    /** Present once the seller has delivered. */
    delivery: { note: string; has_attachment: boolean; delivered_human: string | null } | null;
    timeline: TimelineEntry[];
    /** Order chat — links to the (still-Blade) full inbox; null until a conversation exists. */
    conversation: { id: number; messages: ChatMessage[] } | null;
    participants: Participant[];
    payment: { paid_at_full: string | null } | null;
    alreadyReviewed: boolean;
    /** Null until someone opens one. Both parties get the link, not just the buyer. */
    dispute: DisputeRef | null;
}>();

/** Inline composer — the send endpoint redirects back(), so Inertia re-renders with the new message. */
const messageForm = useForm<{ body: string }>({ body: '' });
function sendMessage() {
    if (!props.conversation) return;
    messageForm.post(route('dashboard.messages.send', props.conversation.id), {
        preserveScroll: true,
        onSuccess: () => messageForm.reset('body'),
    });
}

const completeForm = useForm({});
function completeOrder() {
    completeForm.post(route('dashboard.orders.complete', props.order.id));
}
</script>

<template>
    <DashboardLayout :title="'Order ' + order.order_number">
        <Breadcrumb
            :items="[
                { label: 'Orders', url: route('dashboard.orders') },
                { label: order.order_number },
            ]"
        />

        <div class="items-start gap-4 lg:grid lg:grid-cols-[1fr_20rem]">
            <!-- Main -->
            <div class="flex flex-col gap-3">
                <!-- Header -->
                <div class="card-p">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-mono text-sm text-slate-500">{{ order.order_number }}</p>
                            <div class="mt-1 flex items-center gap-2">
                                <StatusBadge :status="order.status" />
                                <StatusBadge :status="order.payment_status" />
                                <StatusBadge
                                    v-if="order.delivery_status !== 'not_started'"
                                    :status="order.delivery_status"
                                />
                            </div>
                        </div>
                        <p class="text-xs text-slate-500">{{ order.created_full }}</p>
                    </div>

                    <!-- Asset summary -->
                    <div class="flex items-center gap-3 rounded-lg bg-slate-50 p-3">
                        <img
                            v-if="order.asset_cover_url"
                            :src="order.asset_cover_url"
                            class="h-14 w-14 flex-shrink-0 rounded-lg object-cover"
                            alt=""
                        />
                        <div
                            v-else
                            class="grid h-14 w-14 flex-shrink-0 place-items-center rounded-lg bg-brand-50 text-3xl"
                        >
                            🧩
                        </div>
                        <div>
                            <p class="font-semibold text-slate-900">{{ order.asset_title }}</p>
                            <p class="text-xs text-slate-500">{{ order.asset_category }}</p>
                        </div>
                    </div>

                    <!-- Fee breakdown -->
                    <dl class="mt-3 flex flex-col gap-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Unit price</dt>
                            <dd class="money">{{ order.unit_price_formatted }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Quantity</dt>
                            <dd>× {{ order.quantity }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Subtotal</dt>
                            <dd class="money">{{ order.subtotal_formatted }}</dd>
                        </div>
                        <div v-if="order.buyer_fee_amount > 0" class="flex justify-between">
                            <dt class="text-slate-500">Buyer fee</dt>
                            <dd class="money text-rose-600">+ {{ order.buyer_fee_formatted }}</dd>
                        </div>
                        <div class="flex justify-between border-t border-slate-100 pt-2 font-semibold">
                            <dt>Total paid</dt>
                            <dd class="money">{{ order.buyer_total_formatted }}</dd>
                        </div>
                        <template v-if="isSeller">
                            <div class="flex justify-between text-xs">
                                <dt class="text-slate-500">Platform fee ({{ order.seller_fee_percent }}%)</dt>
                                <dd class="money text-rose-600">— {{ order.seller_fee_formatted }}</dd>
                            </div>
                            <div
                                class="flex justify-between font-bold"
                                :class="order.earning_locked ? 'text-amber-700' : 'text-mint-700'"
                            >
                                <dt>Your earning {{ order.earning_locked ? '(locked)' : '(available)' }}</dt>
                                <dd class="money">{{ order.seller_earning_formatted }}</dd>
                            </div>
                            <p v-if="order.earning_locked" class="text-xs text-amber-600">
                                Available at {{ order.earning_available_at }}
                            </p>
                        </template>
                    </dl>
                </div>

                <!-- Delivery info -->
                <div v-if="delivery" class="card-p">
                    <h2 class="section-title mb-2">Delivery</h2>
                    <div class="rounded-lg bg-mint-50 p-3">
                        <p class="whitespace-pre-line text-sm text-mint-700">{{ delivery.note }}</p>
                    </div>
                    <a
                        v-if="delivery.has_attachment"
                        :href="route('orders.delivery.attachment', order.id)"
                        class="btn-outline btn-sm mt-2 inline-flex"
                    >
                        📎 Download delivery file
                    </a>
                    <p class="mt-2 text-xs text-slate-500">Delivered {{ delivery.delivered_human }}</p>
                </div>

                <!-- Order timeline -->
                <div class="card-p">
                    <h2 class="section-title mb-3">Order Timeline</h2>
                    <ol class="relative ml-2 flex flex-col gap-3 border-l border-slate-200 pl-5">
                        <li v-for="h in timeline" :key="h.id" class="relative">
                            <span
                                class="absolute -left-[1.625rem] top-1 h-3 w-3 rounded-full border-2 border-white bg-brand-500"
                            ></span>
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <StatusBadge :status="h.to_status" />
                                    <p v-if="h.note" class="mt-1 text-xs text-slate-500">{{ h.note }}</p>
                                </div>
                                <span class="flex-shrink-0 text-xs text-slate-400">{{ h.at }}</span>
                            </div>
                        </li>
                    </ol>
                </div>

                <!-- Order chat -->
                <div v-if="conversation" class="card-p">
                    <div class="mb-2 flex items-center justify-between">
                        <h2 class="section-title">Order Messages</h2>
                        <Link
                            :href="route('dashboard.messages', { conversation: conversation.id })"
                            class="btn-ghost btn-sm"
                        >
                            Open full chat
                        </Link>
                    </div>
                    <div class="flex max-h-64 flex-col gap-2 overflow-y-auto">
                        <template v-if="conversation.messages.length">
                            <div
                                v-for="msg in conversation.messages"
                                :key="msg.id"
                                class="flex"
                                :class="msg.mine && 'justify-end'"
                            >
                                <div
                                    class="max-w-sm rounded-2xl px-3 py-2 text-sm"
                                    :class="msg.mine ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-800'"
                                >
                                    {{ msg.body }}
                                    <span class="mt-1 block text-xs opacity-70">{{ msg.time }}</span>
                                </div>
                            </div>
                        </template>
                        <p v-else class="py-3 text-center text-sm text-slate-500">No messages yet.</p>
                    </div>
                    <form class="mt-2 flex gap-2" @submit.prevent="sendMessage">
                        <input
                            v-model="messageForm.body"
                            placeholder="Type a message…"
                            class="input flex-1 text-sm"
                            autocomplete="off"
                            required
                        />
                        <button type="submit" class="btn-primary btn-sm" :disabled="messageForm.processing">
                            Send
                        </button>
                    </form>
                </div>
            </div>

            <!-- Sidebar actions -->
            <div class="mt-3 flex flex-col gap-3 lg:mt-0">
                <!-- Seller: Deliver -->
                <div v-if="isSeller && order.can_be_delivered" class="card-p">
                    <Link :href="route('dashboard.orders.deliver', order.id)" class="btn-primary w-full">
                        📦 Deliver asset
                    </Link>
                </div>

                <!-- Live dispute — both parties work it from the same thread. -->
                <div v-if="dispute" class="card-p">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <h2 class="section-title">Dispute</h2>
                        <StatusBadge :status="dispute.status" />
                    </div>
                    <p class="mb-2 font-mono text-xs text-slate-500">{{ dispute.reference }}</p>
                    <Link :href="dispute.url" :class="dispute.is_active ? 'btn-danger w-full' : 'btn-outline w-full'">
                        {{ dispute.is_active ? '⚑ Open dispute thread' : 'View dispute' }}
                    </Link>
                </div>

                <!-- Buyer: Complete / Review / Dispute -->
                <template v-if="isBuyer">
                    <div v-if="order.can_be_completed" class="card-p">
                        <h2 class="section-title mb-2">Actions</h2>
                        <form @submit.prevent="completeOrder">
                            <button type="submit" class="btn-success w-full" :disabled="completeForm.processing">
                                ✓ Complete order
                            </button>
                        </form>
                        <p class="mt-2 text-xs text-slate-500">Auto-completes {{ order.auto_complete_human }}</p>

                        <!-- Leave a review -->
                        <div
                            v-if="alreadyReviewed"
                            class="mt-2 flex items-center gap-2 text-sm font-medium text-mint-600"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2.5"
                                    d="M5 13l4 4L19 7"
                                />
                            </svg>
                            Review submitted
                        </div>
                        <Link
                            v-else
                            :href="route('dashboard.orders.review', order.id)"
                            class="mt-2 flex w-full items-center justify-center gap-2 rounded-lg border-2 border-mint-500 py-2 text-sm font-semibold text-mint-600 hover:bg-mint-50"
                        >
                            ⭐ Leave a Review
                        </Link>
                    </div>

                    <!-- Only offer to open one when there isn't one already. -->
                    <div v-if="order.can_open_dispute && !dispute" class="card-p">
                        <Link :href="route('dashboard.orders.dispute', order.id)" class="btn-danger w-full">
                            ⚑ Open dispute
                        </Link>
                    </div>
                </template>

                <!-- Participants -->
                <div class="card-p">
                    <h2 class="section-title mb-2">Participants</h2>
                    <div
                        v-for="(p, idx) in participants"
                        :key="p.role"
                        class="flex items-center gap-3"
                        :class="idx < participants.length - 1 && 'mb-3 border-b border-slate-100 pb-3'"
                    >
                        <span
                            class="grid h-9 w-9 flex-shrink-0 place-items-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700"
                        >
                            {{ p.initial }}
                        </span>
                        <div>
                            <p class="text-sm font-medium text-slate-900">{{ p.name }}</p>
                            <p class="text-xs text-slate-500">{{ p.role }}</p>
                        </div>
                    </div>
                </div>

                <!-- Payment info -->
                <div v-if="payment" class="card-p">
                    <h2 class="section-title mb-2">Payment</h2>
                    <dl class="space-y-1.5 text-xs text-slate-500">
                        <div class="flex justify-between"><dt>Gateway</dt><dd>UddoktaPay</dd></div>
                        <div class="flex items-center justify-between">
                            <dt>Status</dt>
                            <dd><StatusBadge :status="order.payment_status" /></dd>
                        </div>
                        <div v-if="payment.paid_at_full" class="flex justify-between">
                            <dt>Paid at</dt>
                            <dd>{{ payment.paid_at_full }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
