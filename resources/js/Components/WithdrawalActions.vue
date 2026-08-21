<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Pay / reject controls for a single withdrawal. One step: a pending request is
 * either paid out (optionally with the provider's transaction reference) or
 * rejected with a reason. Owns its own reason / reference / processing state, so
 * it drops cleanly into a table row, a mobile card, or the detail page (via the
 * `layout` prop). The server routes enforce `withdrawals.complete` /
 * `withdrawals.reject` regardless of what renders here.
 */
const props = withDefaults(
    defineProps<{
        id: number;
        /** WithdrawalStatus value — only `pending` exposes actions. */
        status: string;
        layout?: 'inline' | 'card';
    }>(),
    { layout: 'inline' },
);

const reason = ref('');
const reference = ref('');
const processing = ref(false);

function submit(action: 'reject' | 'complete', data: Record<string, string> = {}) {
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
            <input v-model="reference" placeholder="TXN ref (optional)" class="input w-32 !py-1 !text-xs" />
            <button
                class="btn-primary btn-sm"
                :disabled="processing"
                @click="submit('complete', { external_reference: reference })"
            >
                Mark paid
            </button>
            <input v-model="reason" placeholder="Reason" class="input w-32 !py-1 !text-xs" />
            <button
                class="btn-danger btn-sm"
                :disabled="processing || !reason.trim()"
                @click="submit('reject', { reason })"
            >
                Reject
            </button>
        </template>
        <span v-else class="text-xs text-slate-300">—</span>
    </div>

    <!-- Card: detail page -->
    <div v-else>
        <div v-if="status === 'pending'" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="card-p">
                <h3 class="mb-2 text-sm font-semibold text-slate-900">Mark as paid</h3>
                <div class="flex flex-col gap-2">
                    <input
                        v-model="reference"
                        placeholder="MFS / bank transaction reference (optional)"
                        class="input text-sm"
                    />
                    <button
                        class="btn-primary w-full"
                        :disabled="processing"
                        @click="submit('complete', { external_reference: reference })"
                    >
                        Mark paid
                    </button>
                </div>
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
    </div>
</template>
