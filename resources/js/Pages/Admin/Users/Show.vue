<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

interface UserDetail {
    id: number;
    name: string;
    username: string;
    email: string;
    phone: string;
    joined: string;
    last_login: string;
    status: string;
    verification: string;
    is_admin: boolean;
}

const props = defineProps<{
    user: UserDetail;
    wallet: { available_formatted: string; pending_formatted: string };
    counts: { listings: number; purchases: number; sales: number };
}>();

const profileRows = computed(() => [
    { label: 'Name', value: props.user.name },
    { label: 'Username', value: props.user.username },
    { label: 'Email', value: props.user.email },
    { label: 'Phone', value: props.user.phone },
    { label: 'Joined', value: props.user.joined },
    { label: 'Last login', value: props.user.last_login },
]);

// Mirror the server authorize('users.suspend'): super-admin (role 'admin') or the
// explicit permission. The suspend/restore routes re-check this server-side.
const authUser = computed(() => usePage().props.auth.user);
const canManage = computed(() => {
    const u = authUser.value;
    if (!u) return false;
    return u.roles.includes('admin') || u.permissions.includes('users.suspend');
});

const reason = ref('');
const processing = ref(false);

function submit(action: 'suspend' | 'restore', data: Record<string, string> = {}) {
    processing.value = true;
    router.post(route(`admin.users.${action}`, props.user.id), data, {
        preserveScroll: true,
        onFinish: () => (processing.value = false),
    });
}
</script>

<template>
    <AdminLayout :title="user.name" :heading="user.name">
        <Breadcrumb :items="[{ label: 'Users', url: route('admin.users') }, { label: user.name }]" />

        <div class="grid gap-4 lg:grid-cols-[1fr_18rem]">
            <!-- Main column -->
            <div class="flex flex-col gap-3">
                <div class="card-p">
                    <h2 class="section-title mb-3">Profile</h2>
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div v-for="r in profileRows" :key="r.label" class="rounded-lg bg-slate-50 p-2">
                            <dt class="mb-1 text-xs text-slate-500">{{ r.label }}</dt>
                            <dd class="font-medium text-slate-900">{{ r.value }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="card-p">
                    <h2 class="section-title mb-2">Wallet Summary</h2>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-lg bg-mint-50 p-2">
                            <p class="text-xs text-slate-500">Available</p>
                            <p class="money font-bold text-mint-600">{{ wallet.available_formatted }}</p>
                        </div>
                        <div class="rounded-lg bg-amber-50 p-2">
                            <p class="text-xs text-slate-500">Pending</p>
                            <p class="money font-bold text-amber-600">{{ wallet.pending_formatted }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="flex flex-col gap-3">
                <div class="card-p">
                    <h2 class="section-title mb-2">Status</h2>
                    <div class="flex flex-wrap gap-2">
                        <StatusBadge :status="user.status" />
                        <StatusBadge :status="user.verification" />
                    </div>

                    <div v-if="canManage && !user.is_admin" class="mt-3 flex flex-col gap-2 border-t border-slate-100 pt-3">
                        <template v-if="user.status === 'active'">
                            <input
                                v-model="reason"
                                placeholder="Reason for suspension…"
                                class="input text-sm"
                            />
                            <button
                                class="btn-danger btn-sm w-full"
                                :disabled="processing || !reason.trim()"
                                @click="submit('suspend', { reason })"
                            >
                                Suspend user
                            </button>
                        </template>
                        <button
                            v-else
                            class="btn-success btn-sm w-full"
                            :disabled="processing"
                            @click="submit('restore')"
                        >
                            Restore account
                        </button>
                    </div>
                </div>

                <div class="card-p flex flex-col gap-1 text-sm text-slate-500">
                    <p>Listings: <strong class="text-slate-900">{{ counts.listings }}</strong></p>
                    <p>Purchases: <strong class="text-slate-900">{{ counts.purchases }}</strong></p>
                    <p>Sales: <strong class="text-slate-900">{{ counts.sales }}</strong></p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
