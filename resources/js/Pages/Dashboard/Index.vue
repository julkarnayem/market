<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

defineProps<{
    stats: {
        available_formatted: string;
        pending_formatted: string;
        listings: number;
        orders: number;
    };
}>();

const user = computed(() => usePage().props.auth.user!);
const firstName = computed(() => user.value.name.split(' ')[0]);
</script>

<template>
    <DashboardLayout title="Overview" :heading="`Hi, ${firstName}`">
        <!-- Verification nudge -->
        <div v-if="!user.is_verified_seller" class="alert-info mb-3 flex items-center justify-between gap-3">
            <span>Get verified to start selling assets.</span>
            <Link :href="route('dashboard.verification')" class="btn-primary btn-sm flex-shrink-0">Verify now</Link>
        </div>

        <!-- Wallet snapshot -->
        <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="stat-card">
                <p class="text-xs text-slate-500">Available balance</p>
                <span class="money stat-value">{{ stats.available_formatted }}</span>
            </div>
            <div class="stat-card">
                <p class="text-xs text-slate-500">Pending (locked)</p>
                <span class="money stat-value !text-amber-600">{{ stats.pending_formatted }}</span>
            </div>
            <div class="stat-card">
                <p class="text-xs text-slate-500">Active listings</p>
                <p class="stat-value">{{ stats.listings }}</p>
            </div>
            <div class="stat-card">
                <p class="text-xs text-slate-500">Orders</p>
                <p class="stat-value">{{ stats.orders }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <div class="card-p">
                <h2 class="section-title mb-2 text-base">Quick actions</h2>
                <div class="grid grid-cols-2 gap-2">
                    <Link :href="route('marketplace.index')" class="btn-outline">Browse assets</Link>
                    <Link :href="route('dashboard.wallet')" class="btn-outline">Open wallet</Link>
                    <Link
                        v-if="user.can_sell"
                        :href="route('dashboard.listings.create')"
                        class="btn-primary col-span-2"
                    >
                        Create a listing
                    </Link>
                    <Link v-else :href="route('dashboard.verification')" class="btn-primary col-span-2">
                        Become a seller
                    </Link>
                </div>
            </div>

            <div class="card-p">
                <h2 class="section-title mb-2 text-base">Recent activity</h2>
                <!-- Placeholder, exactly as the Blade original: the feed is not built yet. -->
                <div class="py-5 text-center">
                    <div class="mb-2 text-3xl" aria-hidden="true">🕓</div>
                    <h3 class="font-semibold text-slate-900">No activity yet</h3>
                    <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">
                        Your purchases, sales and messages will appear here.
                    </p>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
