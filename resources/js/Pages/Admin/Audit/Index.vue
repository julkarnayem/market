<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import type { Paginated } from '@/types';

/** One audit row — whitelisted by Admin\AuditController::index(). */
interface AuditRow {
    id: number;
    /** Actor name, already collapsed to 'System' for unattributed entries. */
    actor: string;
    action: string;
    entity: string | null;
    entity_id: number | null;
    ip: string | null;
    date: string | null;
    short_date: string | null;
}

const props = defineProps<{
    logs: Paginated<AuditRow>;
    filters: { q: string; from: string; to: string };
    /** The current URL — see apply(). */
    action: string;
}>();

// A useForm (not the usual reactive filterState) because these inputs are
// server-validated — `to` must not precede `from` — and form.errors is how the
// message reaches the field.
const form = useForm<{ q: string; from: string; to: string }>({ ...props.filters });

function apply(): void {
    // props.action, not route('admin.audit'): /admin/activity-logs is a second
    // route onto the same controller and must filter against itself.
    form.get(props.action, { preserveState: true, replace: true, preserveScroll: true });
}
</script>

<template>
    <AdminLayout title="Audit Logs" heading="Audit Logs">
        <form class="card-p mb-4" @submit.prevent="apply">
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-[12rem] flex-1">
                    <label class="label">Search</label>
                    <input
                        v-model="form.q"
                        type="search"
                        class="input"
                        :class="form.errors.q && 'input-error'"
                        placeholder="Search action or user…"
                    />
                </div>
                <div>
                    <label class="label">From</label>
                    <input
                        v-model="form.from"
                        type="date"
                        class="input"
                        :class="form.errors.from && 'input-error'"
                    />
                </div>
                <div>
                    <label class="label">To</label>
                    <input
                        v-model="form.to"
                        type="date"
                        class="input"
                        :class="form.errors.to && 'input-error'"
                    />
                </div>
                <button type="submit" class="btn-outline" :disabled="form.processing">Filter</button>
                <Link :href="action" class="btn-ghost">Clear</Link>
            </div>

            <p v-if="form.errors.q" class="field-error">{{ form.errors.q }}</p>
            <p v-if="form.errors.from" class="field-error">{{ form.errors.from }}</p>
            <p v-if="form.errors.to" class="field-error">{{ form.errors.to }}</p>
        </form>

        <!-- Desktop -->
        <div class="table-wrap hidden sm:block">
            <table class="table">
                <thead>
                    <tr>
                        <th>Actor</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>ID</th>
                        <th>IP</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="log in logs.data" :key="log.id">
                        <td class="font-medium text-slate-900">{{ log.actor }}</td>
                        <td>
                            <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700">
                                {{ log.action }}
                            </code>
                        </td>
                        <td class="text-sm text-slate-500">{{ log.entity ?? '—' }}</td>
                        <td class="text-sm text-slate-500">{{ log.entity_id ?? '—' }}</td>
                        <td class="font-mono text-xs text-slate-500">{{ log.ip ?? '—' }}</td>
                        <td class="text-xs text-slate-500">{{ log.date ?? '—' }}</td>
                    </tr>
                    <tr v-if="logs.data.length === 0">
                        <td colspan="6" class="py-4 text-center text-slate-500">No audit logs found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile -->
        <div class="flex flex-col gap-2 sm:hidden">
            <div v-for="log in logs.data" :key="log.id" class="card-p text-sm">
                <div class="flex items-start justify-between gap-2">
                    <code class="rounded bg-slate-100 px-2 py-1 text-xs text-slate-700">{{ log.action }}</code>
                    <span class="whitespace-nowrap text-xs text-slate-500">{{ log.short_date ?? '—' }}</span>
                </div>
                <p class="mt-1 font-medium text-slate-900">{{ log.actor }}</p>
                <p v-if="log.entity" class="text-xs text-slate-500">
                    {{ log.entity }} #{{ log.entity_id ?? '—' }}
                </p>
            </div>
            <p v-if="logs.data.length === 0" class="card-p text-center text-sm text-slate-500">
                No audit logs found.
            </p>
        </div>

        <div class="mt-3">
            <Pagination :links="logs.links" :total="logs.total" />
        </div>
    </AdminLayout>
</template>
