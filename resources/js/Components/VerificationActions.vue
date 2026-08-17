<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

/**
 * Approve / reject controls for a single seller verification. Owns its own
 * reason / notes / processing state so it drops into a table row (inline) or the
 * detail sidebar (card). Self-gates on `verification.review`: the index has no
 * server authorize(), so a non-reviewer admin must not see action buttons there.
 * The approve/reject routes re-check the permission server-side regardless.
 */
const props = withDefaults(
    defineProps<{
        id: number;
        /** Only `pending` exposes actions. */
        status: string;
        layout?: 'inline' | 'card';
    }>(),
    { layout: 'inline' },
);

const reason = ref('');
const notes = ref('');
const processing = ref(false);

const canReview = computed(() => {
    const u = usePage().props.auth.user;
    if (!u) return false;
    return u.roles.includes('admin') || u.permissions.includes('verification.review');
});

function submit(action: 'approve' | 'reject', data: Record<string, string> = {}) {
    processing.value = true;
    router.post(route(`admin.verification.${action}`, props.id), data, {
        preserveScroll: true,
        onFinish: () => (processing.value = false),
    });
}
</script>

<template>
    <template v-if="canReview && status === 'pending'">
        <!-- Inline: index table cells -->
        <div v-if="layout === 'inline'" class="flex flex-wrap items-center gap-1">
            <button class="btn-success btn-sm" :disabled="processing" @click="submit('approve')">✓ Approve</button>
            <input v-model="reason" placeholder="Reason" class="input w-32 !py-1 !text-xs" />
            <button
                class="btn-danger btn-sm"
                :disabled="processing || !reason.trim()"
                @click="submit('reject', { reason })"
            >
                ✕ Reject
            </button>
        </div>

        <!-- Card: detail sidebar -->
        <div v-else class="flex flex-col gap-3">
            <div class="card-p">
                <h2 class="section-title mb-2">Approve</h2>
                <div class="flex flex-col gap-2">
                    <label class="text-xs text-slate-500">Admin notes (optional)</label>
                    <textarea
                        v-model="notes"
                        rows="3"
                        class="input text-sm"
                        placeholder="Internal notes…"
                    ></textarea>
                    <button class="btn-success w-full" :disabled="processing" @click="submit('approve', { notes })">
                        ✓ Approve verification
                    </button>
                </div>
            </div>
            <div class="card-p">
                <h2 class="section-title mb-2">Reject</h2>
                <div class="flex flex-col gap-2">
                    <label class="text-xs text-slate-500">
                        Rejection reason <span class="text-rose-500">*</span>
                    </label>
                    <textarea
                        v-model="reason"
                        rows="3"
                        class="input text-sm"
                        placeholder="Explain clearly why the verification is rejected…"
                    ></textarea>
                    <button
                        class="btn-danger w-full"
                        :disabled="processing || !reason.trim()"
                        @click="submit('reject', { reason, notes })"
                    >
                        ✕ Reject
                    </button>
                </div>
            </div>
        </div>
    </template>
</template>
