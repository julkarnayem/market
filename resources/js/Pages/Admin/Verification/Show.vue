<script setup lang="ts">
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import VerificationActions from '@/Components/VerificationActions.vue';

interface VerificationDetail {
    id: number;
    user_name: string;
    user_email: string;
    type_label: string;
    attempt: number;
    submitted: string;
    status: string;
    date_of_birth: string | null;
    reviewer_name: string;
    is_pending: boolean;
    has_document: boolean;
    has_document_back: boolean;
    document_url: string | null;
    document_back_url: string | null;
}

const props = defineProps<{
    verification: VerificationDetail;
    canViewDocuments: boolean;
}>();

const applicantRows = computed(() => [
    { label: 'Name', value: props.verification.user_name },
    { label: 'Email', value: props.verification.user_email },
    { label: 'Document Type', value: props.verification.type_label },
    { label: 'Attempt #', value: String(props.verification.attempt) },
    { label: 'Submitted', value: props.verification.submitted },
]);

const hasAnyDocument = computed(
    () => props.verification.has_document || props.verification.has_document_back,
);
</script>

<template>
    <AdminLayout :title="'Verify: ' + verification.user_name" heading="Verification Review">
        <Breadcrumb
            :items="[{ label: 'Verification', url: route('admin.verification') }, { label: verification.user_name }]"
        />

        <div class="grid gap-4 lg:grid-cols-[1fr_20rem]">
            <!-- Main column -->
            <div class="flex flex-col gap-3">
                <div class="card-p">
                    <h2 class="section-title mb-3">Applicant</h2>
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div v-for="r in applicantRows" :key="r.label" class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">{{ r.label }}</dt>
                            <dd class="font-medium text-slate-900">{{ r.value }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-2">
                            <dt class="text-xs text-slate-500">Status</dt>
                            <dd><StatusBadge :status="verification.status" /></dd>
                        </div>
                    </dl>
                </div>

                <!-- Confidential data: reaching this page already implies verification.review -->
                <div class="card-p">
                    <h2 class="section-title mb-2">
                        Verification Data <span class="badge-rose ml-2">Confidential</span>
                    </h2>

                    <div
                        v-if="verification.date_of_birth"
                        class="mb-2 rounded-lg bg-amber-50 p-2 text-sm"
                    >
                        <p class="font-medium text-amber-700">Date of Birth</p>
                        <p class="mt-1 text-amber-700">{{ verification.date_of_birth }}</p>
                    </div>

                    <template v-if="hasAnyDocument">
                        <div v-if="canViewDocuments" class="mt-2 flex flex-wrap gap-3">
                            <a
                                v-if="verification.document_url"
                                :href="verification.document_url"
                                target="_blank"
                                rel="noopener"
                                class="btn-outline btn-sm"
                            >
                                📄 View document (front)
                            </a>
                            <a
                                v-if="verification.document_back_url"
                                :href="verification.document_back_url"
                                target="_blank"
                                rel="noopener"
                                class="btn-outline btn-sm"
                            >
                                📄 View document (back)
                            </a>
                        </div>
                        <div v-else class="mt-2 rounded-lg border border-rose-200 bg-rose-50 p-2 text-sm text-rose-600">
                            🔒 Document images are restricted to the platform owner only.
                        </div>
                    </template>
                </div>
            </div>

            <!-- Actions sidebar -->
            <div>
                <VerificationActions
                    v-if="verification.is_pending"
                    :id="verification.id"
                    :status="verification.status"
                    layout="card"
                />
                <div v-else class="card-p">
                    <p class="text-sm text-slate-500">
                        This verification has already been
                        <strong class="text-slate-900">{{ verification.status }}</strong>
                        by {{ verification.reviewer_name }}.
                    </p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
