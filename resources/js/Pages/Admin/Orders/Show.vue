<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

interface OrderDetail {
    order_number: string;
    status: string;
    payment_status: string;
    asset_title: string;
    buyer_name: string;
    seller_name: string;
    quantity: number;
    buyer_total: string;
    seller_earning: string;
    platform_commission: string;
}
interface TimelineEntry {
    id: number;
    to_status: string;
    note: string | null;
    at: string | null;
}
interface Payment {
    gateway: string | null;
    status: string | null;
    amount: string;
    transaction_id: string | null;
    paid_at: string | null;
}

defineProps<{
    order: OrderDetail;
    timeline: TimelineEntry[];
    delivery: { note: string | null } | null;
    payment: Payment | null;
}>();
</script>

<template>
    <AdminLayout :title="'Order ' + order.order_number" heading="Order Details">
        <Breadcrumb :items="[{ label: 'Orders', url: route('admin.orders') }, { label: order.order_number }]" />

        <div class="lg:grid lg:grid-cols-[1fr_20rem] lg:gap-4">
            <!-- Main column -->
            <div class="flex flex-col gap-3">
                <div class="card-p">
                    <div class="mb-3 flex flex-wrap gap-2">
                        <StatusBadge :status="order.status" />
                        <StatusBadge :status="order.payment_status" />
                    </div>
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">Order #</dt>
                            <dd class="font-mono font-medium">{{ order.order_number }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">Asset</dt>
                            <dd class="truncate font-medium">{{ order.asset_title }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">Buyer</dt>
                            <dd>{{ order.buyer_name }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">Seller</dt>
                            <dd>{{ order.seller_name }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">Qty</dt>
                            <dd>{{ order.quantity }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">Total paid</dt>
                            <dd class="money font-bold">{{ order.buyer_total }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">Seller earning</dt>
                            <dd class="money font-bold text-emerald-600">{{ order.seller_earning }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">Platform commission</dt>
                            <dd class="money">{{ order.platform_commission }}</dd>
                        </div>
                    </dl>
                </div>

                <div v-if="delivery" class="card-p">
                    <h2 class="section-title mb-2">
                        Delivery <span class="badge-rose ml-2">Admin-only view</span>
                    </h2>
                    <div class="whitespace-pre-line rounded-lg bg-amber-50 p-3 text-sm text-amber-700">{{ delivery.note }}</div>
                </div>

                <div class="card-p">
                    <h2 class="section-title mb-2">Timeline</h2>
                    <ol v-if="timeline.length" class="flex flex-col gap-3">
                        <li v-for="h in timeline" :key="h.id" class="flex gap-3">
                            <span class="mt-1.5 h-3 w-3 flex-shrink-0 rounded-full bg-brand-500 ring-2 ring-white"></span>
                            <div class="flex flex-1 justify-between gap-2">
                                <div>
                                    <StatusBadge :status="h.to_status" />
                                    <p v-if="h.note" class="mt-1 text-xs text-slate-500">{{ h.note }}</p>
                                </div>
                                <span class="flex-shrink-0 text-xs text-slate-400">{{ h.at }}</span>
                            </div>
                        </li>
                    </ol>
                    <p v-else class="text-sm text-slate-500">No status changes recorded.</p>
                </div>
            </div>

            <!-- Sidebar -->
            <div v-if="payment" class="mt-3 flex flex-col gap-3 lg:mt-0">
                <div class="card-p">
                    <h2 class="section-title mb-2">Payment</h2>
                    <dl class="space-y-1.5 text-xs text-slate-500">
                        <div class="flex justify-between"><dt>Gateway</dt><dd>{{ payment.gateway ?? '—' }}</dd></div>
                        <div class="flex justify-between"><dt>Status</dt><dd>{{ payment.status ?? '—' }}</dd></div>
                        <div class="flex justify-between"><dt>Amount</dt><dd class="money">{{ payment.amount }}</dd></div>
                        <div v-if="payment.transaction_id" class="flex justify-between gap-2">
                            <dt>TXN ID</dt>
                            <dd class="max-w-[120px] truncate font-mono">{{ payment.transaction_id }}</dd>
                        </div>
                        <div class="flex justify-between"><dt>Paid at</dt><dd>{{ payment.paid_at ?? '—' }}</dd></div>
                    </dl>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
