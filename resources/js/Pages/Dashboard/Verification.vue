<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

type Verification = {
    attempt_number: number;
    document_type: string;
    submitted_at: string | null;
    rejection_reason: string | null;
    status: string;
};

const props = defineProps<{
    current: {
        reviewed_at: string | null;
        submitted_ago: string | null;
        rejection_reason: string | null;
    } | null;
    history: Verification[];
    maxDob: string;
}>();

const status = computed(() => usePage().props.auth.user!.verification_status);
const canSubmit = computed(() => status.value === 'not_submitted' || status.value === 'rejected');

const methods = [
    { val: 'nid', icon: '🪪', label: 'NID', hint: 'Front page + Back page' },
    { val: 'passport', icon: '📘', label: 'Passport', hint: '1 image required' },
    { val: 'dob', icon: '📅', label: 'Date of Birth', hint: 'DOB + 1 document' },
    { val: 'driving_license', icon: '🚗', label: 'Driving License', hint: '1 image required' },
] as const;

const DOC_LABELS: Record<string, string> = {
    nid: 'National ID',
    passport: 'Passport',
    dob: 'Date of Birth',
    driving_license: 'Driving License',
};
const docLabel = (t: string) => DOC_LABELS[t] ?? t.toUpperCase();

const form = useForm<{
    verification_method: string;
    document_front: File | null;
    document_back: File | null;
    date_of_birth: string;
}>({
    verification_method: 'nid',
    document_front: null,
    document_back: null,
    date_of_birth: '',
});

// Blade used x-if to drop non-applicable inputs from the DOM so they were never
// submitted; mirror that by clearing fields when the method changes.
watch(
    () => form.verification_method,
    () => {
        form.document_front = null;
        form.document_back = null;
        form.date_of_birth = '';
        form.clearErrors();
    },
);

function pickFront(e: Event) {
    form.document_front = (e.target as HTMLInputElement).files?.[0] ?? null;
}
function pickBack(e: Event) {
    form.document_back = (e.target as HTMLInputElement).files?.[0] ?? null;
}

function submit() {
    form.post(route('dashboard.verification.submit'));
}

const showHistory = computed(
    () =>
        props.history.length > 1 ||
        (props.history.length === 1 && status.value !== 'not_submitted'),
);
</script>

<template>
    <DashboardLayout title="Verification" heading="Seller Verification">
        <div class="flex max-w-2xl flex-col gap-3">
            <!-- Status card -->
            <div class="card-p">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="section-title">Verification Status</h2>
                        <p class="section-sub">Required before you can create or sell listings.</p>
                    </div>
                    <StatusBadge :status="status" />
                </div>
            </div>

            <!-- Status-dependent banner -->
            <div v-if="status === 'approved'" class="alert-success flex-col !items-start">
                <p class="font-semibold">You are a verified seller ✓</p>
                <p v-if="current?.reviewed_at" class="mt-1 text-sm">Verified on {{ current.reviewed_at }}.</p>
            </div>

            <div v-else-if="status === 'pending'" class="alert-warning flex-col !items-start">
                <p class="font-semibold">Verification under review</p>
                <p class="mt-1 text-sm">
                    Submitted {{ current?.submitted_ago }}. We review within 1–2 business days.
                </p>
            </div>

            <template v-else>
                <div
                    v-if="status === 'rejected' && current"
                    class="alert-error flex-col !items-start"
                >
                    <p class="font-semibold">Previous submission rejected</p>
                    <p v-if="current.rejection_reason" class="mt-1 text-sm">Reason: {{ current.rejection_reason }}</p>
                    <p class="mt-1 text-sm">Please submit a new verification below.</p>
                </div>

                <!-- Submit form -->
                <div v-if="canSubmit" class="card-p">
                    <h2 class="section-title mb-3">Submit Verification</h2>
                    <form class="flex flex-col gap-3" novalidate @submit.prevent="submit">
                        <div>
                            <label class="label">Document Type <span class="text-rose-500">*</span></label>
                            <div class="mt-1 grid grid-cols-2 gap-2">
                                <label
                                    v-for="m in methods"
                                    :key="m.val"
                                    class="flex cursor-pointer items-start gap-2 rounded-xl border p-2 transition-colors"
                                    :class="form.verification_method === m.val
                                        ? 'border-brand-400 bg-brand-50'
                                        : 'border-slate-200 hover:border-slate-300'"
                                >
                                    <input
                                        v-model="form.verification_method"
                                        type="radio"
                                        :value="m.val"
                                        class="radio mt-1 flex-shrink-0"
                                    />
                                    <div>
                                        <p class="text-sm font-medium text-slate-900">{{ m.icon }} {{ m.label }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ m.hint }}</p>
                                    </div>
                                </label>
                            </div>
                            <p v-if="form.errors.verification_method" class="field-error">{{ form.errors.verification_method }}</p>
                        </div>

                        <!-- NID: front + back -->
                        <template v-if="form.verification_method === 'nid'">
                            <div>
                                <label class="label">NID — Front Page <span class="text-rose-500">*</span></label>
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png"
                                    class="input"
                                    :class="form.errors.document_front && 'input-error'"
                                    @change="pickFront"
                                />
                                <p class="field-hint">Clear photo of the front of your NID card. JPG or PNG, max 10MB.</p>
                                <p v-if="form.errors.document_front" class="field-error">{{ form.errors.document_front }}</p>
                            </div>
                            <div>
                                <label class="label">NID — Back Page <span class="text-rose-500">*</span></label>
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png"
                                    class="input"
                                    :class="form.errors.document_back && 'input-error'"
                                    @change="pickBack"
                                />
                                <p class="field-hint">Clear photo of the back of your NID card. JPG or PNG, max 10MB.</p>
                                <p v-if="form.errors.document_back" class="field-error">{{ form.errors.document_back }}</p>
                            </div>
                        </template>

                        <!-- Date of birth: date + supporting document -->
                        <template v-else-if="form.verification_method === 'dob'">
                            <div>
                                <label class="label">Date of Birth <span class="text-rose-500">*</span></label>
                                <input
                                    v-model="form.date_of_birth"
                                    type="date"
                                    :max="maxDob"
                                    class="input"
                                    :class="form.errors.date_of_birth && 'input-error'"
                                />
                                <p v-if="form.errors.date_of_birth" class="field-error">{{ form.errors.date_of_birth }}</p>
                            </div>
                            <div>
                                <label class="label">Supporting Document <span class="text-rose-500">*</span></label>
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,application/pdf"
                                    class="input"
                                    :class="form.errors.document_front && 'input-error'"
                                    @change="pickFront"
                                />
                                <p class="field-hint">Birth certificate or official document showing your DOB. JPG, PNG or PDF, max 10MB.</p>
                                <p v-if="form.errors.document_front" class="field-error">{{ form.errors.document_front }}</p>
                            </div>
                        </template>

                        <!-- Passport / Driving License: single document -->
                        <template v-else>
                            <div>
                                <label class="label">
                                    {{ form.verification_method === 'passport' ? 'Passport Photo Page' : 'Driving License' }}
                                    <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,application/pdf"
                                    class="input"
                                    :class="form.errors.document_front && 'input-error'"
                                    @change="pickFront"
                                />
                                <p class="field-hint">
                                    {{ form.verification_method === 'passport'
                                        ? "Photo of your passport's information page."
                                        : 'Clear photo of your driving license.' }}
                                    JPG, PNG or PDF, max 10MB.
                                </p>
                                <p v-if="form.errors.document_front" class="field-error">{{ form.errors.document_front }}</p>
                            </div>
                        </template>

                        <button type="submit" class="btn-primary btn-lg self-start" :disabled="form.processing">
                            {{ form.processing ? 'Submitting…' : 'Submit for Verification' }}
                        </button>
                    </form>
                </div>
            </template>

            <!-- History -->
            <div v-if="showHistory" class="card-p">
                <h2 class="section-title mb-2">Verification History</h2>
                <div class="divide-y divide-slate-100">
                    <div
                        v-for="v in history"
                        :key="v.attempt_number"
                        class="flex items-center justify-between gap-3 py-2"
                    >
                        <div>
                            <p class="text-sm font-medium text-slate-900">
                                Attempt #{{ v.attempt_number }} — {{ docLabel(v.document_type) }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">Submitted {{ v.submitted_at }}</p>
                            <p v-if="v.rejection_reason" class="mt-1 text-xs text-rose-600">
                                Reason: {{ v.rejection_reason }}
                            </p>
                        </div>
                        <StatusBadge :status="v.status" />
                    </div>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
