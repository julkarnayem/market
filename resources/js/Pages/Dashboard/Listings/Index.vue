<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import type { Paginated } from '@/types';

interface ListingRow {
    id: number;
    title: string;
    slug: string;
    price_formatted: string;
    quantity: number;
    available_quantity: number;
    status: string;
    is_featured: boolean;
    created_date: string;
}

const props = defineProps<{
    status: string;
    canSell: boolean;
    listings: Paginated<ListingRow>;
}>();

const STATUSES = [
    { key: 'all', label: 'All' },
    { key: 'draft', label: 'Draft' },
    { key: 'pending_review', label: 'Pending Review' },
    { key: 'published', label: 'Published' },
    { key: 'pending_edit_approval', label: 'Edit Pending' },
    { key: 'rejected', label: 'Rejected' },
    { key: 'paused', label: 'Paused' },
    { key: 'sold_out', label: 'Sold Out' },
    { key: 'suspended', label: 'Suspended' },
] as const;
</script>

<template>
    <DashboardLayout title="My Listings" heading="My Listings">
        <template v-if="canSell" #actions>
            <Link :href="route('dashboard.listings.create')" class="btn-primary">+ New Listing</Link>
        </template>

        <!-- Tabs -->
        <div class="tabs mb-3 flex-nowrap overflow-x-auto whitespace-nowrap">
            <Link
                v-for="s in STATUSES"
                :key="s.key"
                :href="route('dashboard.listings', { status: s.key })"
                class="tab"
                :class="status === s.key && 'tab-active'"
            >
                {{ s.label }}
            </Link>
        </div>

        <div v-if="listings.data.length === 0" class="card-p text-center">
            <p class="mb-2 text-4xl">🏷️</p>
            <h2 class="font-display text-lg font-bold text-slate-900">No listings yet</h2>
            <p class="mt-1 text-sm text-slate-500">Create your first listing to start selling digital assets.</p>
            <Link v-if="canSell" :href="route('dashboard.listings.create')" class="btn-primary mt-3">
                Create a listing
            </Link>
            <Link v-else :href="route('dashboard.verification')" class="btn-outline mt-3">
                Get verified to sell
            </Link>
        </div>

        <template v-else>
            <!-- Desktop table -->
            <div class="table-wrap hidden sm:block">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Asset</th>
                            <th>Price</th>
                            <th>Qty</th>
                            <th>Status</th>
                            <th>Featured</th>
                            <th>Created</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="l in listings.data" :key="l.id">
                            <td class="max-w-xs truncate font-medium text-slate-900">{{ l.title }}</td>
                            <td class="money">{{ l.price_formatted }}</td>
                            <td>{{ l.available_quantity }}/{{ l.quantity }}</td>
                            <td><StatusBadge :status="l.status" /></td>
                            <td>{{ l.is_featured ? '⭐ Yes' : '—' }}</td>
                            <td class="text-slate-500">{{ l.created_date }}</td>
                            <td class="text-right">
                                <Link :href="route('marketplace.show', l.slug)" class="btn-ghost btn-sm">View</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <div class="flex flex-col gap-2 sm:hidden">
                <div v-for="l in listings.data" :key="l.id" class="card-p">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="truncate font-semibold text-slate-900">{{ l.title }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ l.created_date }}</p>
                        </div>
                        <StatusBadge :status="l.status" />
                    </div>
                    <div class="mt-2 flex items-center justify-between">
                        <span class="money font-bold text-slate-900">{{ l.price_formatted }}</span>
                        <Link :href="route('marketplace.show', l.slug)" class="btn-ghost btn-sm">View</Link>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <Pagination :links="listings.links" :total="listings.total" />
            </div>
        </template>
    </DashboardLayout>
</template>
