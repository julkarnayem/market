<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

interface FlaggedUser {
    id: number;
    name: string;
    email: string;
    risk_score: number;
    flags: string[];
    status: string | null;
    profile_url: string;
}
/** One fraud event — whitelisted by Admin\FraudController::show(). */
interface EventRow {
    id: number;
    signal: string;
    score_impact: number;
    ip: string | null;
    date: string;
}
interface ReviewState {
    status: string;
    admin_notes: string | null;
    reviewer: string | null;
    reviewed_at: string | null;
}

const props = defineProps<{
    user: FlaggedUser;
    events: EventRow[];
    review: ReviewState | null;
}>();

// The page authorizes fraud.view; clear/restrict authorize fraud.manage.
// Server authorize() re-checks — this only decides what the UI shows.
const canManage = computed(() => {
    const u = usePage().props.auth.user;
    return !!u && (u.roles.includes('admin') || u.permissions.includes('fraud.manage'));
});

// One in-flight flag disables both action buttons so no two writes race.
const processing = ref(false);
const clearNotes = ref('');
const restrictReason = ref('');

function post(routeName: string, data: Record<string, string>): void {
    processing.value = true;
    router.post(route(routeName, props.user.id), data, {
        preserveScroll: true,
        onSuccess: () => {
            clearNotes.value = '';
            restrictReason.value = '';
        },
        onFinish: () => (processing.value = false),
    });
}

// FraudService::THRESHOLD_HIGH = 70, THRESHOLD_REVIEW = 30.
const scoreTone = computed(() => {
    if (props.user.risk_score >= 70) return 'text-rose-600';
    if (props.user.risk_score >= 30) return 'text-amber-600';
    return 'text-mint-600';
});
</script>

<template>
    <AdminLayout :title="`Fraud — ${user.name}`" heading="Fraud Review">
        <Breadcrumb
            :items="[{ label: 'Fraud Queue', url: route('admin.fraud') }, { label: user.name }]"
        />

        <div class="grid gap-4 lg:grid-cols-[1fr_20rem]">
            <!-- Main column -->
            <div class="flex flex-col gap-3">
                <div class="card-p">
                    <h2 class="section-title mb-3">User Profile</h2>
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">Name</dt>
                            <dd class="font-medium">{{ user.name }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">Email</dt>
                            <dd>{{ user.email }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">Risk Score</dt>
                            <dd class="money text-lg font-bold" :class="scoreTone">{{ user.risk_score }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">Status</dt>
                            <dd><StatusBadge :status="user.status" /></dd>
                        </div>
                    </dl>
                    <div v-if="user.flags.length" class="mt-2 flex flex-wrap gap-1">
                        <span v-for="f in user.flags" :key="f" class="badge-rose text-xs">{{ f }}</span>
                    </div>
                </div>

                <div class="card-p">
                    <h2 class="section-title mb-1">Recent Fraud Events</h2>
                    <p class="section-sub mb-2">The 50 most recent signals recorded for this account.</p>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Signal</th>
                                    <th>Score impact</th>
                                    <th>IP</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="e in events" :key="e.id">
                                    <td><span class="badge-rose text-xs">{{ e.signal }}</span></td>
                                    <td class="money text-amber-600">+{{ e.score_impact }}</td>
                                    <td class="money text-xs text-slate-500">{{ e.ip ?? '—' }}</td>
                                    <td class="text-xs text-slate-500">{{ e.date }}</td>
                                </tr>
                                <tr v-if="events.length === 0">
                                    <td colspan="4" class="py-3 text-center text-slate-500">No events.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Actions sidebar -->
            <div class="flex flex-col gap-3">
                <div v-if="review" class="card-p">
                    <h2 class="section-title mb-1">Review Status</h2>
                    <p class="section-sub mb-2">Current: <StatusBadge :status="review.status" /></p>
                    <p v-if="review.admin_notes" class="mb-2 rounded-lg bg-slate-50 p-2 text-xs text-slate-500">
                        {{ review.admin_notes }}
                    </p>
                    <p v-if="review.reviewer" class="text-xs text-slate-400">
                        By {{ review.reviewer }}<span v-if="review.reviewed_at"> · {{ review.reviewed_at }}</span>
                    </p>
                </div>

                <template v-if="canManage">
                    <div class="card-p">
                        <h2 class="section-title mb-2">Clear Risk Score</h2>
                        <p class="mb-2 text-xs text-slate-500">
                            Clears the risk score and flags. Use when satisfied this is a false positive.
                        </p>
                        <form class="flex flex-col gap-2" @submit.prevent="post('admin.fraud.clear', { admin_notes: clearNotes })">
                            <textarea
                                v-model="clearNotes"
                                rows="3"
                                class="textarea text-sm"
                                placeholder="Note explaining the review decision…"
                            ></textarea>
                            <button
                                type="submit"
                                class="btn-success btn-sm w-full"
                                :disabled="processing || !clearNotes.trim()"
                            >
                                ✓ Clear — false positive
                            </button>
                        </form>
                    </div>

                    <div class="card-p">
                        <h2 class="section-title mb-2">Restrict Account</h2>
                        <p class="mb-2 text-xs text-slate-500">
                            Flags the case as restricted for follow-up. Suspension is a separate action on the user page.
                        </p>
                        <form class="flex flex-col gap-2" @submit.prevent="post('admin.fraud.restrict', { reason: restrictReason })">
                            <textarea
                                v-model="restrictReason"
                                rows="3"
                                class="textarea text-sm"
                                placeholder="Restriction reason…"
                            ></textarea>
                            <button
                                type="submit"
                                class="btn-danger btn-sm w-full"
                                :disabled="processing || !restrictReason.trim()"
                            >
                                ⚑ Restrict
                            </button>
                        </form>
                    </div>
                </template>

                <Link :href="user.profile_url" class="btn-outline block w-full text-center text-sm">
                    View full user profile →
                </Link>
            </div>
        </div>
    </AdminLayout>
</template>
