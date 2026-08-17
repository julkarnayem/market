<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Approve / reject / complete controls for a single withdrawal. Owns its own
 * reason / reference / processing state, so it drops cleanly into a table row,
 * a mobile card, or the detail page (via the `layout` prop). Server routes
 * enforce the `withdrawals.approve` permission regardless of what renders here.
 */
const props = withDefaults(
    defineProps<{
        id: number;
        /** WithdrawalStatus value — only `pending` / `approved` expose actions. */
        status: string;
        layout?: 'inline' | 'card';
    }>(),
    { layout: 'inline' },
);

const reason = ref('');
const reference = ref('');
const processing = ref(false);

function submit(action: 'approve' | 'reject' | 'complete', data: Record<string, string> = {}) {
    processing.value = true;
    router.post(route(`admin.withdrawals.${action}`, props.id), data, {
        preserveScroll: true,
        onFinish: () => (processing.value = false),
    });
}
</script>

<template>
    <!-- Inline: table cells + mobile cards -->
    <div v-if="layout === 'inline'" class="flex flex-wrap items-center gap-1">
        <template v-if="status === 'pending'">
            <button class="btn-success btn-sm" :disabled="processing" @click="submit('approve')">Approve</button>
            <input v-model="reason" placeholder="Reason" class="input w-32 !py-1 !text-xs" />
            <button
                class="btn-danger btn-sm"
                :disabled="processing || !reason.trim()"
                @click="submit('reject', { reason })"
            >
                Reject
            </button>
        </template>
        <template v-else-if="status === 'approved'">
            <input v-model="reference" placeholder="TXN ref (optional)" class="input w-36 !py-1 !text-xs" />
            <button
                class="btn-primary btn-sm"
                :disabled="processing"
                @click="submit('complete', { external_reference: reference })"
            >
                Mark paid
            </button>
        </template>
        <span v-else class="text-xs text-slate-300">—</span>
    </div>

    <!-- Card: detail page -->
    <div v-else>
        <div v-if="status === 'pending'" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="card-p">
                <h3 class="mb-2 text-sm font-semibold text-slate-900">Approve</h3>
                <button class="btn-success w-full" :disabled="processing" @click="submit('approve')">
                    Approve withdrawal
                </button>
            </div>
            <div class="card-p">
                <h3 class="mb-2 text-sm font-semibold text-slate-900">Reject</h3>
                <div class="flex flex-col gap-2">
                    <input v-model="reason" placeholder="Reason for rejection…" class="input text-sm" />
                    <button
                        class="btn-danger w-full"
                        :disabled="processing || !reason.trim()"
                        @click="submit('reject', { reason })"
                    >
                        Reject
                    </button>
                </div>
            </div>
        </div>
        <div v-else-if="status === 'approved'" class="card-p">
            <h3 class="section-title mb-2">Mark as Completed</h3>
            <div class="flex gap-3">
                <input
                    v-model="reference"
                    placeholder="MFS transaction reference (optional)"
                    class="input flex-1 text-sm"
                />
                <button
                    class="btn-primary"
                    :disabled="processing"
                    @click="submit('complete', { external_reference: reference })"
                >
                    Mark paid
                </button>
            </div>
        </div>
    </div>
</template>
