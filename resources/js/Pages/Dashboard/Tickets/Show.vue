<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

interface TicketMessage {
    id: number;
    is_staff: boolean;
    author: string;
    initial: string;
    body: string;
    created_human: string;
}
interface ContextLink {
    icon: string;
    color: string;
    label: string;
    href: string;
}
interface TicketData {
    id: number;
    reference: string;
    subject: string;
    status: string;
    priority_label: string;
    priority_color: string;
    assignee_name: string | null;
    can_reply: boolean;
    links: ContextLink[];
    messages: TicketMessage[];
}

const props = defineProps<{
    ticket: TicketData;
}>();

const fileInput = ref<HTMLInputElement | null>(null);

const form = useForm<{
    body: string;
    attachment: File | null;
}>({
    body: '',
    attachment: null,
});

function onFile(e: Event) {
    const target = e.target as HTMLInputElement;
    form.attachment = target.files?.[0] ?? null;
}

function submit() {
    form.post(route('dashboard.tickets.reply', props.ticket.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}
</script>

<template>
    <DashboardLayout :title="'Ticket ' + ticket.reference" :heading="ticket.subject">
        <Breadcrumb
            :items="[
                { label: 'Support', url: route('dashboard.tickets') },
                { label: ticket.reference },
            ]"
        />

        <div class="flex max-w-3xl flex-col gap-3">
            <!-- Context links (order / asset / withdrawal) -->
            <div v-if="ticket.links.length" class="card-p flex flex-wrap gap-3 bg-brand-50 text-sm">
                <span class="font-semibold text-brand-700">Linked to:</span>
                <a
                    v-for="(link, i) in ticket.links"
                    :key="i"
                    :href="link.href"
                    :class="`badge-${link.color}`"
                >
                    {{ link.icon }} {{ link.label }}
                </a>
            </div>

            <!-- Status bar -->
            <div class="card-p flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <StatusBadge :status="ticket.status" />
                    <span :class="`badge-${ticket.priority_color}`" class="text-xs">{{ ticket.priority_label }}</span>
                    <span class="text-xs text-slate-400">{{ ticket.reference }}</span>
                </div>
                <p v-if="ticket.assignee_name" class="text-xs text-slate-500">
                    Assigned to: <strong>{{ ticket.assignee_name }}</strong>
                </p>
            </div>

            <!-- Message thread -->
            <div class="flex flex-col gap-3">
                <div
                    v-for="msg in ticket.messages"
                    :key="msg.id"
                    class="flex gap-3"
                    :class="!msg.is_staff && 'flex-row-reverse'"
                >
                    <span
                        class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-sm font-bold"
                        :class="msg.is_staff ? 'bg-brand-600 text-white' : 'bg-slate-200 text-slate-700'"
                    >
                        {{ msg.initial }}
                    </span>
                    <div class="max-w-xl flex-1">
                        <div
                            class="rounded-2xl px-4 py-3 ring-1"
                            :class="msg.is_staff ? 'bg-brand-50 ring-brand-200' : 'bg-white ring-slate-200'"
                        >
                            <div class="mb-1 flex justify-between gap-2">
                                <span
                                    class="text-xs font-semibold"
                                    :class="msg.is_staff ? 'text-brand-800' : 'text-slate-700'"
                                >
                                    {{ msg.author }}
                                </span>
                                <span class="text-xs text-slate-400">{{ msg.created_human }}</span>
                            </div>
                            <p class="whitespace-pre-line text-sm text-slate-800">{{ msg.body }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reply form -->
            <div v-if="ticket.can_reply" class="card-p">
                <h2 class="mb-2 text-sm font-semibold text-slate-900">Send a reply</h2>
                <form class="flex flex-col gap-2" @submit.prevent="submit">
                    <textarea
                        v-model="form.body"
                        rows="4"
                        class="textarea"
                        :class="form.errors.body && 'input-error'"
                        maxlength="5000"
                        required
                        placeholder="Type your reply…"
                    ></textarea>
                    <p v-if="form.errors.body" class="field-error">{{ form.errors.body }}</p>

                    <input
                        ref="fileInput"
                        type="file"
                        class="input text-sm"
                        accept=".jpg,.jpeg,.png,.pdf,.txt,.zip"
                        @change="onFile"
                    />
                    <p v-if="form.errors.attachment" class="field-error">{{ form.errors.attachment }}</p>

                    <div class="flex gap-3">
                        <button type="submit" class="btn-primary" :disabled="form.processing">Send reply</button>
                    </div>
                </form>
            </div>
            <div v-else class="alert-info">
                This ticket is {{ ticket.status }}. No further replies can be submitted.
            </div>
        </div>
    </DashboardLayout>
</template>
