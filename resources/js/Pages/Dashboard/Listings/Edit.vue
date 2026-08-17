<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';

interface EditAttr {
    id: number;
    label: string;
    type: string;
    is_required: boolean;
    unit: string | null;
    placeholder: string | null;
    options: string[];
    value: string | null;
}
interface ListingData {
    id: number;
    title: string;
    description: string | null;
    status: string;
    quantity: number;
    price_bdt: string;
    price_formatted: string;
    is_price_locked: boolean;
}

const props = defineProps<{
    listing: ListingData;
    attributes: EditAttr[];
    feePercent: string;
}>();

const isDraft = props.listing.status === 'draft';
const isPublished = props.listing.status === 'published';

const form = useForm<{
    title: string;
    description: string;
    price_bdt: string;
    quantity: number;
    attributes: Record<string, string>;
    edit_reason: string;
}>({
    title: props.listing.title,
    description: props.listing.description ?? '',
    price_bdt: props.listing.price_bdt,
    quantity: props.listing.quantity,
    attributes: Object.fromEntries(props.attributes.map((a) => [String(a.id), a.value ?? ''])),
    edit_reason: '',
});

// ── Live fee preview ──────────────────────────────────────────────
interface Earning { fee_amount: string; earning: string; fee_percent: string }
const earning = ref<Earning>({ fee_amount: '—', earning: '—', fee_percent: props.feePercent });
let feeTimer: ReturnType<typeof setTimeout> | undefined;
function calcFee() {
    if (feeTimer) clearTimeout(feeTimer);
    feeTimer = setTimeout(async () => {
        if (!form.price_bdt) return;
        try {
            const res = await fetch(
                route('dashboard.listings.fee-preview') + '?price_bdt=' + encodeURIComponent(form.price_bdt),
                { headers: { Accept: 'application/json' } },
            );
            earning.value = await res.json();
        } catch {
            /* keep last value on transient error */
        }
    }, 400);
}
onMounted(calcFee);

function submit() {
    form.patch(route('dashboard.listings.update', props.listing.id));
}
</script>

<template>
    <DashboardLayout :title="'Edit: ' + listing.title">
        <Breadcrumb
            :items="[
                { label: 'My Listings', url: route('dashboard.listings') },
                { label: listing.title, url: route('dashboard.listings.show', listing.id) },
                { label: 'Edit' },
            ]"
        />

        <div v-if="isPublished" class="alert-info mb-3">
            Your live listing stays public while your edit is under review. Changes go live only after admin approval.
        </div>

        <form class="flex max-w-3xl flex-col gap-3" @submit.prevent="submit">
            <!-- Basic info -->
            <div class="card-p">
                <h2 class="section-title mb-3">Basic Information</h2>
                <div class="flex flex-col gap-3">
                    <div>
                        <label class="label">Title <span class="text-rose-500">*</span></label>
                        <input
                            v-model="form.title"
                            type="text"
                            class="input"
                            :class="form.errors.title && 'input-error'"
                            required
                        />
                        <p v-if="form.errors.title" class="field-error">{{ form.errors.title }}</p>
                    </div>
                    <div>
                        <label class="label">Description</label>
                        <textarea
                            v-model="form.description"
                            rows="6"
                            class="textarea"
                            :class="form.errors.description && 'input-error'"
                        ></textarea>
                        <p v-if="form.errors.description" class="field-error">{{ form.errors.description }}</p>
                    </div>
                </div>
            </div>

            <!-- Attributes -->
            <div v-if="attributes.length" class="card-p">
                <h2 class="section-title mb-3">Attributes</h2>
                <div class="flex flex-col gap-3">
                    <div v-for="attr in attributes" :key="attr.id">
                        <label class="label">
                            {{ attr.label }}<span v-if="attr.is_required" class="text-rose-500"> *</span>
                            <span v-if="attr.unit" class="ml-1 text-xs text-slate-400">({{ attr.unit }})</span>
                        </label>
                        <select v-if="attr.type === 'select'" v-model="form.attributes[attr.id]" class="select">
                            <option value="">Select…</option>
                            <option v-for="opt in attr.options" :key="opt" :value="opt">{{ opt }}</option>
                        </select>
                        <input
                            v-else
                            v-model="form.attributes[attr.id]"
                            :type="attr.type === 'number' ? 'number' : 'text'"
                            class="input"
                            :placeholder="attr.placeholder ?? ''"
                        />
                    </div>
                </div>
            </div>

            <!-- Price & Quantity -->
            <div class="card-p">
                <h2 class="section-title mb-3">Price &amp; Quantity</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label">Selling Price (৳) <span class="text-rose-500">*</span></label>
                        <template v-if="listing.is_price_locked">
                            <div class="input money bg-slate-50 text-slate-500">{{ listing.price_formatted }}</div>
                            <p class="field-hint text-amber-600">⚠ Price locked while an offer is pending.</p>
                        </template>
                        <template v-else>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 font-mono text-slate-400">৳</span>
                                <input
                                    v-model="form.price_bdt"
                                    type="number"
                                    class="input pl-7"
                                    :class="form.errors.price_bdt && 'input-error'"
                                    min="1"
                                    required
                                    @input="calcFee"
                                />
                            </div>
                            <p v-if="form.errors.price_bdt" class="field-error">{{ form.errors.price_bdt }}</p>
                        </template>
                    </div>
                    <div>
                        <label class="label">Quantity <span class="text-rose-500">*</span></label>
                        <input
                            v-model.number="form.quantity"
                            type="number"
                            class="input"
                            :class="form.errors.quantity && 'input-error'"
                            min="1"
                            max="9999"
                            required
                        />
                        <p v-if="form.errors.quantity" class="field-error">{{ form.errors.quantity }}</p>
                    </div>
                </div>

                <div class="mt-2 flex flex-col gap-1 rounded-lg bg-mint-50 p-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Platform Fee ({{ earning.fee_percent }}%)</span>
                        <span class="money text-rose-600">{{ earning.fee_amount }}</span>
                    </div>
                    <div class="flex justify-between font-bold">
                        <span class="text-mint-700">You Will Receive</span>
                        <span class="money text-mint-700">{{ earning.earning }}</span>
                    </div>
                </div>
            </div>

            <!-- Edit reason -->
            <div class="card-p">
                <label class="label">Reason for edit (optional)</label>
                <input
                    v-model="form.edit_reason"
                    type="text"
                    class="input"
                    placeholder="e.g. Updated subscriber count"
                />
            </div>

            <div class="flex gap-3">
                <button type="submit" class="btn-primary btn-lg" :disabled="form.processing">
                    {{ isDraft ? 'Save changes' : 'Submit edit for review' }}
                </button>
                <Link :href="route('dashboard.listings.show', listing.id)" class="btn-outline">Cancel</Link>
            </div>
        </form>
    </DashboardLayout>
</template>
