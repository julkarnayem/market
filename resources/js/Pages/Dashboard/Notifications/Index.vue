<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import type { Paginated } from '@/types';

interface NotificationRow {
    id: string;
    icon: string;
    title: string;
    message: string;
    is_read: boolean;
    created_human: string;
}

defineProps<{
    tab: string;
    unreadCount: number;
    notifications: Paginated<NotificationRow>;
}>();

// Body-less forms for the row + bulk actions. A shared instance matches the
// dashboard's useForm convention (router is not used elsewhere); the server
// redirects back(), so Inertia reloads the list with fresh read/unread state.
const markAll = useForm({});
const rowAction = useForm({});

const markRead = (id: string) =>
    rowAction.post(route('dashboard.notifications.read', id), { preserveScroll: true });
const destroy = (id: string) =>
    rowAction.delete(route('dashboard.notifications.destroy', id), { preserveScroll: true });
const markAllRead = () =>
    markAll.post(route('dashboard.notifications.read-all'), { preserveScroll: true });
</script>

<template>
    <DashboardLayout title="Notifications" heading="Notifications">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div class="tabs mb-0">
                <Link
                    :href="route('dashboard.notifications', { tab: 'all' })"
                    class="tab"
                    :class="tab === 'all' && 'tab-active'"
                >
                    All
                </Link>
                <Link
                    :href="route('dashboard.notifications', { tab: 'unread' })"
                    class="tab"
                    :class="tab === 'unread' && 'tab-active'"
                >
                    Unread
                    <span v-if="unreadCount > 0" class="badge-rose ml-1">{{ unreadCount }}</span>
                </Link>
            </div>
            <button
                v-if="unreadCount > 0"
                type="button"
                class="btn-ghost btn-sm"
                :disabled="markAll.processing"
                @click="markAllRead"
            >
                Mark all read
            </button>
        </div>

        <!-- Empty state -->
        <div v-if="notifications.data.length === 0" class="card-p text-center">
            <p class="mb-2 text-4xl">🔔</p>
            <h2 class="font-display text-lg font-bold text-slate-900">No notifications</h2>
            <p class="mt-1 text-sm text-slate-500">You're all caught up!</p>
        </div>

        <template v-else>
            <div class="flex flex-col gap-2">
                <div
                    v-for="n in notifications.data"
                    :key="n.id"
                    class="card-p flex gap-3"
                    :class="!n.is_read && 'bg-brand-50/30 ring-1 ring-brand-200'"
                >
                    <span class="mt-1 flex-shrink-0 text-2xl">{{ n.icon }}</span>
                    <div class="flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <p
                                class="text-sm font-semibold"
                                :class="n.is_read ? 'text-slate-900' : 'text-brand-900'"
                            >
                                {{ n.title }}
                            </p>
                            <span class="flex-shrink-0 text-xs text-slate-400">{{ n.created_human }}</span>
                        </div>
                        <p v-if="n.message" class="mt-1 text-sm text-slate-500">{{ n.message }}</p>
                        <div class="mt-2 flex items-center gap-3">
                            <button
                                v-if="!n.is_read"
                                type="button"
                                class="text-xs text-brand-600 hover:underline disabled:opacity-50"
                                :disabled="rowAction.processing"
                                @click="markRead(n.id)"
                            >
                                Mark read
                            </button>
                            <button
                                type="button"
                                class="text-xs text-slate-400 hover:text-rose-600 hover:underline disabled:opacity-50"
                                :disabled="rowAction.processing"
                                @click="destroy(n.id)"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <Pagination :links="notifications.links" :total="notifications.total" />
            </div>
        </template>
    </DashboardLayout>
</template>
