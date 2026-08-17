<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import CountdownTimer from '@/Components/CountdownTimer.vue';
import type { Paginated } from '@/types';

/** One offer row — whitelisted by OfferController::index(). */
interface OfferRow {
    id: number;
    asset_slug: string;
    asset_title: string;
    /** Counterparty name — seller on sent/expired tabs, buyer otherwise. */
    party_name: string;
    amount_formatted: string;
    list_price_formatted: string;
    /** OfferStatus value — drives the badge and the action logic. */
    status: string;
    is_pending: boolean;
    is_expired: boolean;
    /** Seconds until expiry (clamped ≥0) — seeds the live countdown. */
    time_remaining_seconds: number;
    /** created_at, "d M, H:i". */
    created_short: string;
}

const props = defineProps<{
    /** received | sent | accepted | rejected | expired. */
    tab: string;
    offers: Paginated<OfferRow>;
}>();

const TABS = [
    { key: 'received', label: 'Received' },
    { key: 'sent', label: 'Sent' },
    { key: 'accepted', label: 'Accepted' },
    { key: 'rejected', label: 'Rejected' },
    { key: 'expired', label: 'Expired' },
] as const;

/** Id of the row whose accept/reject request is in flight (disables its buttons). */
const processingId = ref<number | null>(null);

function respond(id: number, action: 'accept' | 'reject') {
    processingId.value = id;
    router.post(
        route(`offers.${action}`, id),
        {},
        {
            preserveScroll: true,
            onFinish: () => (processingId.value = null),
        },
    );
}
</script>

<template>
    <DashboardLayout title="Offers" heading="Offers">
        <div class="tabs mb-3 overflow-x-auto whitespace-nowrap">
            <Link
                v-for="t in TABS"
                :key="t.key"
                :href="route('dashboard.offers', { tab: t.key })"
                class="tab"
                :class="tab === t.key && 'tab-active'"
            >
                {{ t.label }}
            </Link>
        </div>

        <div v-if="offers.data.length === 0" class="card-p text-center">
            <p class="mb-2 text-4xl">🤝</p>
            <h2 class="font-display text-lg font-bold text-slate-900">No offers</h2>
            <p class="mt-1 text-sm text-slate-500">
                <template v-if="tab === 'sent'">You haven't made any offers yet.</template>
                <template v-else-if="tab === 'received'">No offers on your listings.</template>
                <template v-else>No {{ tab }} offers.</template>
            </p>
            <Link v-if="tab === 'sent'" :href="route('marketplace.index')" class="btn-outline mt-3">
                Browse marketplace
            </Link>
        </div>

        <template v-else>
            <!-- Desktop table -->
            <div class="table-wrap hidden sm:block">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Asset</th>
                            <th>{{ ['sent', 'expired'].includes(tab) ? 'Seller' : 'Buyer' }}</th>
                            <th>Offer</th>
                            <th>List price</th>
                            <th>Status</th>
                            <th>{{ tab === 'received' ? 'Expires' : 'Date' }}</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="o in offers.data" :key="o.id">
                            <td>
                                <Link
                                    :href="route('marketplace.show', o.asset_slug)"
                                    class="block max-w-[180px] truncate font-medium text-slate-900"
                                >
                                    {{ o.asset_title }}
                                </Link>
                            </td>
                            <td class="text-sm">{{ o.party_name }}</td>
                            <td class="money font-semibold text-slate-900">{{ o.amount_formatted }}</td>
                            <td class="money text-slate-500">{{ o.list_price_formatted }}</td>
                            <td><StatusBadge :status="o.status" /></td>
                            <td class="text-xs text-slate-500">
                                <CountdownTimer
                                    v-if="tab === 'received' && o.is_pending"
                                    :seconds="o.time_remaining_seconds"
                                />
                                <template v-else>{{ o.created_short }}</template>
                            </td>
                            <td class="text-right">
                                <template v-if="tab === 'received' && o.is_pending && !o.is_expired">
                                    <button
                                        class="btn-success btn-sm"
                                        :disabled="processingId === o.id"
                                        @click="respond(o.id, 'accept')"
                                    >
                                        Accept
                                    </button>
                                    <button
                                        class="btn-danger btn-sm ml-1"
                                        :disabled="processingId === o.id"
                                        @click="respond(o.id, 'reject')"
                                    >
                                        Reject
                                    </button>
                                </template>
                                <span
                                    v-else-if="o.status === 'accepted' && tab === 'sent'"
                                    class="badge-mint text-xs"
                                >
                                    Payment required
                                </span>
                                <span v-else class="text-xs text-slate-300">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <div class="flex flex-col gap-2 sm:hidden">
                <div v-for="o in offers.data" :key="o.id" class="card-p flex flex-col gap-2">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <Link
                                :href="route('marketplace.show', o.asset_slug)"
                                class="block truncate text-sm font-semibold text-slate-900"
                            >
                                {{ o.asset_title }}
                            </Link>
                            <p class="text-xs text-slate-500">{{ o.party_name }}</p>
                        </div>
                        <StatusBadge :status="o.status" />
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <div>
                            <p class="text-xs text-slate-500">Your offer</p>
                            <span class="money font-bold text-slate-900">{{ o.amount_formatted }}</span>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-slate-500">List price</p>
                            <span class="money text-slate-500">{{ o.list_price_formatted }}</span>
                        </div>
                    </div>
                    <div
                        v-if="tab === 'received' && o.is_pending && !o.is_expired"
                        class="flex gap-2 border-t border-slate-100 pt-2"
                    >
                        <button
                            class="btn-success btn-sm flex-1"
                            :disabled="processingId === o.id"
                            @click="respond(o.id, 'accept')"
                        >
                            Accept
                        </button>
                        <button
                            class="btn-danger btn-sm flex-1"
                            :disabled="processingId === o.id"
                            @click="respond(o.id, 'reject')"
                        >
                            Reject
                        </button>
                    </div>
                    <p
                        v-if="o.status === 'accepted' && ['sent', 'accepted'].includes(tab)"
                        class="border-t border-slate-100 pt-2 text-center text-sm font-semibold text-mint-700"
                    >
                        ✓ Accepted — payment required
                    </p>
                </div>
            </div>

            <div class="mt-3">
                <Pagination :links="offers.links" :total="offers.total" />
            </div>
        </template>
    </DashboardLayout>
</template>
