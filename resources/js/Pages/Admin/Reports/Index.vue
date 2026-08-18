<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface Metric {
    key: string;
    label: string;
    icon: string;
    /** Drives the mono/mint treatment; money arrives pre-formatted from Money::format. */
    is_money: boolean;
    value: string;
}
interface Preset {
    label: string;
    from: string;
    to: string;
}

const props = defineProps<{
    metrics: Metric[];
    daily: { date: string; orders: number; volume: string }[];
    filters: { from: string; to: string; label: string };
    presets: Preset[];
}>();

// A useForm (not the usual reactive filterState) because these two inputs are
// server-validated — `to` must not precede `from` — and form.errors is how the
// message reaches the field.
const form = useForm<{ from: string; to: string }>({
    from: props.filters.from,
    to: props.filters.to,
});

function apply(): void {
    form.get(route('admin.reports'), { preserveState: true, replace: true, preserveScroll: true });
}

function applyPreset(preset: Preset): void {
    form.from = preset.from;
    form.to = preset.to;
    apply();
}

// Plain <a>, never an Inertia <Link>: the response is a text/csv attachment,
// which an Inertia visit cannot consume.
function exportUrl(): string {
    return route('admin.reports', { from: form.from, to: form.to, export: 'csv' });
}
</script>

<template>
    <AdminLayout title="Reports" heading="Platform Reports">
        <template #actions>
            <a :href="exportUrl()" class="btn-outline btn-sm">Export CSV</a>
        </template>

        <!-- Date range -->
        <form class="card-p mb-4" @submit.prevent="apply">
            <div class="flex flex-wrap items-end gap-3">
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
                <button type="submit" class="btn-primary" :disabled="form.processing">Apply</button>
                <Link :href="route('admin.reports')" class="btn-ghost">Reset</Link>
            </div>

            <p v-if="form.errors.from" class="field-error">{{ form.errors.from }}</p>
            <p v-if="form.errors.to" class="field-error">{{ form.errors.to }}</p>

            <div class="mt-3 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                <button
                    v-for="preset in presets"
                    :key="preset.label"
                    type="button"
                    class="btn-ghost btn-sm"
                    :disabled="form.processing"
                    @click="applyPreset(preset)"
                >
                    {{ preset.label }}
                </button>
            </div>
        </form>

        <!-- Summary -->
        <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
            <div v-for="metric in metrics" :key="metric.key" class="stat-card">
                <p class="stat-label">{{ metric.icon }} {{ metric.label }}</p>
                <p class="stat-value" :class="metric.is_money && 'money text-mint-700'">{{ metric.value }}</p>
            </div>
        </div>

        <!-- Daily paid orders -->
        <div v-if="daily.length" class="card-p">
            <h2 class="section-title mb-3">Daily Paid Orders ({{ filters.label }})</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Orders</th>
                            <th>Volume</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="day in daily" :key="day.date">
                            <td>{{ day.date }}</td>
                            <td>{{ day.orders }}</td>
                            <td class="money">{{ day.volume }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <p v-else class="card-p text-sm text-slate-500">No paid orders in this range.</p>
    </AdminLayout>
</template>
