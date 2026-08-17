<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

interface Attr {
    id: number;
    label: string;
    type: string;
    is_required: boolean;
    unit: string | null;
    placeholder: string | null;
    options: string[];
}
interface Sub {
    id: number;
    name: string;
    is_prohibited: boolean;
    is_restricted: boolean;
    selectable: boolean;
    attributes: Attr[];
}
interface Root {
    id: number;
    name: string;
    icon: string | null;
    is_prohibited: boolean;
    children: Sub[];
}

const props = defineProps<{
    categories: Root[];
    feePercent: string;
}>();

const STEPS = ['Category', 'Details', 'Attributes', 'Price & Qty', 'Images'] as const;
const step = ref(1);

const parentId = ref<string>('');

const form = useForm<{
    category_id: string;
    title: string;
    description: string;
    price_bdt: string;
    quantity: number;
    attributes: Record<string, string>;
    images: File[];
    policy_accept: boolean;
}>({
    category_id: '',
    title: '',
    description: '',
    price_bdt: '',
    quantity: 1,
    attributes: {},
    images: [],
    policy_accept: false,
});

/** Roots that have at least one subcategory (matches the Blade filter). */
const parentOptions = computed(() => props.categories.filter((c) => c.children.length));
const parent = computed(() => props.categories.find((c) => String(c.id) === parentId.value) ?? null);
const subcategories = computed(() => parent.value?.children ?? []);
const subcategory = computed(
    () => subcategories.value.find((s) => String(s.id) === form.category_id) ?? null,
);
const subAttributes = computed(() => subcategory.value?.attributes ?? []);

// Reset subcategory + attributes when the parent changes.
watch(parentId, () => {
    form.category_id = '';
    form.attributes = {};
});

// ── Live fee preview ──────────────────────────────────────────────
interface Earning { price: string; fee_amount: string; earning: string; fee_percent: string }
const earning = ref<Earning>({
    price: '৳0.00',
    fee_amount: '৳0.00',
    earning: '৳0.00',
    fee_percent: props.feePercent,
});
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
            /* leave the last value in place on a transient error */
        }
    }, 400);
}

// ── Image previews ────────────────────────────────────────────────
const previews = ref<string[]>([]);
function onImages(e: Event) {
    const files = Array.from((e.target as HTMLInputElement).files ?? []);
    form.images = files;
    previews.value.forEach(URL.revokeObjectURL);
    previews.value = files.map((f) => URL.createObjectURL(f));
}

// ── Submit (dual: draft / review) ─────────────────────────────────
const STEP_OF: Record<string, number> = {
    category_id: 1,
    title: 2,
    description: 2,
    price_bdt: 4,
    quantity: 4,
    policy_accept: 5,
    images: 5,
};
function submit(asDraft: boolean) {
    form
        .transform((data) => {
            const payload: Record<string, unknown> = {
                ...data,
                policy_accept: data.policy_accept ? '1' : '',
            };
            if (asDraft) payload.save_as_draft = '1';
            else delete payload.save_as_draft;
            return payload;
        })
        .post(route('dashboard.listings.store'), {
            forceFormData: true,
            onError: (errors) => {
                // Jump back to the earliest step that has an error.
                const steps = Object.keys(errors).map((k) => STEP_OF[k.split('.')[0]] ?? 5);
                if (steps.length) step.value = Math.min(...steps);
            },
        });
}
</script>

<template>
    <DashboardLayout title="Create Listing" heading="Create a listing">
        <div class="max-w-3xl">
            <!-- Progress -->
            <div class="mb-4 flex items-center gap-2 overflow-x-auto pb-1">
                <div v-for="(label, i) in STEPS" :key="label" class="flex flex-shrink-0 items-center gap-1">
                    <span
                        class="grid h-7 w-7 place-items-center rounded-full text-xs font-bold"
                        :class="
                            step > i + 1
                                ? 'bg-mint-500 text-white'
                                : step === i + 1
                                  ? 'bg-brand-600 text-white'
                                  : 'bg-slate-200 text-slate-500'
                        "
                    >
                        {{ step > i + 1 ? '✓' : i + 1 }}
                    </span>
                    <span
                        class="hidden text-xs font-medium sm:block"
                        :class="step === i + 1 ? 'text-slate-900' : 'text-slate-400'"
                    >
                        {{ label }}
                    </span>
                    <span v-if="i < STEPS.length - 1" class="text-sm text-slate-300">›</span>
                </div>
            </div>

            <form class="flex flex-col gap-3" @submit.prevent="submit(false)">
                <!-- STEP 1: Category -->
                <div v-show="step === 1" class="card-p">
                    <h2 class="section-title mb-3">Select Category</h2>
                    <div class="flex flex-col gap-3">
                        <div>
                            <label class="label">Category <span class="text-rose-500">*</span></label>
                            <select v-model="parentId" class="select" required>
                                <option value="">Choose a category…</option>
                                <option
                                    v-for="cat in parentOptions"
                                    :key="cat.id"
                                    :value="String(cat.id)"
                                    :disabled="cat.is_prohibited"
                                >
                                    {{ cat.icon ? cat.icon + ' ' : '' }}{{ cat.name
                                    }}{{ cat.is_prohibited ? ' (prohibited)' : '' }}
                                </option>
                            </select>
                        </div>
                        <div v-if="parentId">
                            <label class="label">Subcategory <span class="text-rose-500">*</span></label>
                            <select
                                v-model="form.category_id"
                                class="select"
                                :class="form.errors.category_id && 'input-error'"
                                required
                            >
                                <option value="">Choose subcategory…</option>
                                <option
                                    v-for="sub in subcategories"
                                    :key="sub.id"
                                    :value="String(sub.id)"
                                    :disabled="!sub.selectable"
                                >
                                    {{ sub.name
                                    }}{{
                                        sub.is_prohibited
                                            ? ' (prohibited)'
                                            : sub.is_restricted
                                              ? ' (restricted)'
                                              : ''
                                    }}
                                </option>
                            </select>
                            <p v-if="form.errors.category_id" class="field-error">{{ form.errors.category_id }}</p>
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end">
                        <button type="button" class="btn-primary" :disabled="!form.category_id" @click="step = 2">
                            Next: Basic details →
                        </button>
                    </div>
                </div>

                <!-- STEP 2: Basic info -->
                <div v-show="step === 2" class="card-p">
                    <h2 class="section-title mb-3">Basic Information</h2>
                    <div class="flex flex-col gap-3">
                        <div>
                            <label class="label">Title <span class="text-rose-500">*</span></label>
                            <input
                                v-model="form.title"
                                type="text"
                                class="input"
                                :class="form.errors.title && 'input-error'"
                                placeholder="e.g. 50K YouTube Channel – Tech Niche, Monetized"
                                maxlength="255"
                            />
                            <p class="field-hint">Min 10 characters. Be specific and descriptive.</p>
                            <p v-if="form.errors.title" class="field-error">{{ form.errors.title }}</p>
                        </div>
                        <div>
                            <label class="label">Description <span class="text-rose-500">*</span></label>
                            <textarea
                                v-model="form.description"
                                rows="8"
                                class="textarea"
                                :class="form.errors.description && 'input-error'"
                                placeholder="Describe the asset in full detail: age, stats, monetization status, audience demographics, reason for selling…"
                            ></textarea>
                            <p class="field-hint">Min 50 characters. Detailed descriptions get approved faster.</p>
                            <p v-if="form.errors.description" class="field-error">{{ form.errors.description }}</p>
                        </div>
                    </div>
                    <div class="mt-3 flex justify-between">
                        <button type="button" class="btn-ghost" @click="step = 1">← Back</button>
                        <button type="button" class="btn-primary" @click="step = 3">Next: Attributes →</button>
                    </div>
                </div>

                <!-- STEP 3: Attributes -->
                <div v-show="step === 3" class="card-p">
                    <h2 class="section-title mb-1">Asset Details</h2>
                    <p class="section-sub mb-3">Fill in specific details about the asset.</p>
                    <p v-if="!subAttributes.length" class="text-sm italic text-slate-500">
                        No additional attributes for this subcategory.
                    </p>
                    <div v-else class="flex flex-col gap-3">
                        <div v-for="attr in subAttributes" :key="attr.id">
                            <label class="label">
                                {{ attr.label }}<span v-if="attr.is_required" class="text-rose-500"> *</span>
                                <span v-if="attr.unit" class="ml-1 text-xs text-slate-400">({{ attr.unit }})</span>
                            </label>
                            <select
                                v-if="attr.type === 'select'"
                                v-model="form.attributes[attr.id]"
                                class="select"
                                :required="attr.is_required"
                            >
                                <option value="">Select…</option>
                                <option v-for="opt in attr.options" :key="opt" :value="opt">{{ opt }}</option>
                            </select>
                            <select
                                v-else-if="attr.type === 'boolean'"
                                v-model="form.attributes[attr.id]"
                                class="select"
                            >
                                <option value="">Select…</option>
                                <option value="yes">Yes</option>
                                <option value="no">No</option>
                            </select>
                            <input
                                v-else
                                v-model="form.attributes[attr.id]"
                                :type="
                                    ['number', 'decimal'].includes(attr.type)
                                        ? 'number'
                                        : attr.type === 'date'
                                          ? 'date'
                                          : attr.type === 'url'
                                            ? 'url'
                                            : 'text'
                                "
                                class="input"
                                :required="attr.is_required"
                                :placeholder="attr.placeholder ?? (attr.type === 'url' ? 'https://' : '')"
                            />
                        </div>
                    </div>
                    <div class="mt-3 flex justify-between">
                        <button type="button" class="btn-ghost" @click="step = 2">← Back</button>
                        <button type="button" class="btn-primary" @click="step = 4">Next: Price & Quantity →</button>
                    </div>
                </div>

                <!-- STEP 4: Price & Qty -->
                <div v-show="step === 4" class="card-p">
                    <h2 class="section-title mb-1">Price &amp; Quantity</h2>
                    <p class="section-sub mb-3">Set your selling price in BDT.</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="label">Selling Price (৳) <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 font-mono font-bold text-slate-400">৳</span>
                                <input
                                    v-model="form.price_bdt"
                                    type="number"
                                    class="input pl-7"
                                    :class="form.errors.price_bdt && 'input-error'"
                                    min="1"
                                    step="1"
                                    placeholder="0"
                                    required
                                    @input="calcFee"
                                />
                            </div>
                            <p v-if="form.errors.price_bdt" class="field-error">{{ form.errors.price_bdt }}</p>
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
                            <p class="field-hint">Use 1 for unique items.</p>
                            <p v-if="form.errors.quantity" class="field-error">{{ form.errors.quantity }}</p>
                        </div>
                    </div>

                    <!-- Earning summary -->
                    <div class="mt-3 rounded-xl bg-mint-50 p-3">
                        <p class="mb-2 text-sm font-semibold text-mint-700">Your estimated earnings</p>
                        <dl class="flex flex-col gap-2 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Selling Price</dt>
                                <dd class="money font-semibold">{{ earning.price }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Platform Fee ({{ earning.fee_percent }}%)</dt>
                                <dd class="money text-rose-600">{{ earning.fee_amount }}</dd>
                            </div>
                            <div class="flex justify-between border-t border-mint-200 pt-2">
                                <dt class="font-bold text-mint-700">You Will Receive</dt>
                                <dd class="money text-xl font-bold text-mint-700">{{ earning.earning }}</dd>
                            </div>
                        </dl>
                        <p class="mt-2 text-xs text-slate-500">
                            Earnings are released 8 hours after order completion. Fee is deducted from each sale.
                        </p>
                    </div>

                    <div class="mt-3 flex justify-between">
                        <button type="button" class="btn-ghost" @click="step = 3">← Back</button>
                        <button type="button" class="btn-primary" @click="step = 5">Next: Images →</button>
                    </div>
                </div>

                <!-- STEP 5: Images + Policy + Submit -->
                <div v-show="step === 5" class="flex flex-col gap-3">
                    <div class="card-p">
                        <h2 class="section-title mb-1">Images</h2>
                        <p class="section-sub mb-3">
                            Upload screenshots or proof images. The first image will be the cover.
                        </p>
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            multiple
                            class="input"
                            :class="form.errors.images && 'input-error'"
                            @change="onImages"
                        />
                        <p class="field-hint">JPG, PNG, WebP. Max 5MB each. Up to 10 images.</p>
                        <p v-if="form.errors.images" class="field-error">{{ form.errors.images }}</p>

                        <div v-if="previews.length" class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-5">
                            <div
                                v-for="(src, i) in previews"
                                :key="i"
                                class="relative aspect-square overflow-hidden rounded-lg bg-slate-100"
                            >
                                <img :src="src" class="h-full w-full object-cover" alt="" />
                                <span v-if="i === 0" class="badge-mint absolute left-1 top-1 py-0.5">Cover</span>
                            </div>
                        </div>
                    </div>

                    <div class="card-p">
                        <h2 class="section-title mb-2">Seller Policy</h2>
                        <div class="space-y-1.5 text-sm text-slate-500">
                            <p>By submitting this listing I confirm that:</p>
                            <ul class="mt-2 flex list-inside list-disc flex-col gap-1">
                                <li>I own or have the right to sell this asset.</li>
                                <li>All information provided is accurate.</li>
                                <li>
                                    This asset does not violate the
                                    <a :href="route('legal', 'prohibited-assets')" target="_blank" class="text-brand-600 hover:underline">Prohibited Assets Policy</a>.
                                </li>
                                <li>
                                    I have read and agree to the
                                    <a :href="route('legal', 'seller-policy')" target="_blank" class="text-brand-600 hover:underline">Seller Policy</a>.
                                </li>
                            </ul>
                        </div>
                        <label class="mt-3 flex items-start gap-3">
                            <input v-model="form.policy_accept" type="checkbox" class="checkbox mt-1" />
                            <span class="text-sm font-medium text-slate-700">
                                I accept the seller policy and confirm the above statements.
                            </span>
                        </label>
                        <p v-if="form.errors.policy_accept" class="field-error">{{ form.errors.policy_accept }}</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="btn-outline"
                            :disabled="form.processing"
                            @click="submit(true)"
                        >
                            💾 Save as draft
                        </button>
                        <button type="submit" class="btn-primary btn-lg" :disabled="form.processing">
                            Submit for review →
                        </button>
                        <button type="button" class="btn-ghost" @click="step = 4">← Back</button>
                    </div>
                </div>
            </form>
        </div>
    </DashboardLayout>
</template>
