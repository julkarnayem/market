<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

/** One thread message — whitelisted by Admin\TicketController::show(). */
interface MessageRow {
    id: number;
    author: string;
    initial: string;
    body: string;
    is_staff: boolean;
    is_internal: boolean;
    attachment: string | null;
    created: string;
}
interface TicketDetail {
    id: number;
    reference: string;
    subject: string;
    category: string | null;
    status: string;
    priority: string;
    priority_label: string;
    priority_color: string;
    assigned_to: number | null;
    assignee_name: string | null;
    user_name: string;
    user_email: string;
    user_url: string | null;
    messages: MessageRow[];
}
interface Option {
    value: string;
    label: string;
}

const props = defineProps<{
    ticket: TicketDetail;
    staff: Array<{ id: number; name: string }>;
    statuses: Option[];
    priorities: Option[];
}>();

// The page authorizes tickets.view; every write authorizes tickets.manage, and
// a moderator holds view without manage. Server authorize() re-checks — this
// only decides which controls are worth showing.
const canManage = computed(() => {
    const u = usePage().props.auth.user;
    return !!u && (u.roles.includes('admin') || u.permissions.includes('tickets.manage'));
});

// Reply carries a file, so it needs useForm (Inertia switches to FormData when
// it sees a File in the payload) and form.errors for the field messages.
const replyForm = useForm<{ body: string; attachment: File | null }>({
    body: '',
    attachment: null,
});
function onFile(e: Event): void {
    replyForm.attachment = (e.target as HTMLInputElement).files?.[0] ?? null;
}
function sendReply(): void {
    replyForm.post(route('admin.tickets.reply', props.ticket.id), {
        preserveScroll: true,
        onSuccess: () => replyForm.reset(),
    });
}

const noteForm = useForm({ body: '' });
function addNote(): void {
    noteForm.post(route('admin.tickets.note', props.ticket.id), {
        preserveScroll: true,
        onSuccess: () => noteForm.reset(),
    });
}

// The three sidebar controls are single-select writes, so they use bare
// router calls with one in-flight flag rather than a useForm each.
const assignedTo = ref<string>(props.ticket.assigned_to ? String(props.ticket.assigned_to) : '');
const status = ref(props.ticket.status);
const statusReason = ref('');
const priority = ref(props.ticket.priority);
const processing = ref(false);

function submit(method: 'post' | 'patch', routeName: string, data: Record<string, string>): void {
    processing.value = true;
    router[method](route(routeName, props.ticket.id), data, {
        preserveScroll: true,
        onFinish: () => (processing.value = false),
    });
}

/** Amber for an internal note, brand for a staff reply, plain for the user. */
function bubbleTone(m: MessageRow): string {
    if (m.is_internal) return 'bg-amber-50 ring-1 ring-amber-200';
    if (m.is_staff) return 'bg-brand-50 ring-1 ring-brand-100';
    return '';
}
</script>

<template>
    <AdminLayout :title="`Ticket ${ticket.reference}`" heading="Support Ticket">
        <Breadcrumb
            :items="[{ label: 'Tickets', url: route('admin.tickets') }, { label: ticket.reference }]"
        />

        <div class="grid gap-4 lg:grid-cols-[1fr_20rem]">
            <!-- Main thread -->
            <div class="flex flex-col gap-3">
                <div class="card-p">
                    <h2 class="mb-1 font-semibold text-slate-900">{{ ticket.subject }}</h2>
                    <div class="flex flex-wrap items-center gap-2 text-sm text-slate-500">
                        <span>{{ ticket.user_name }}</span>
                        <span aria-hidden="true">·</span>
                        <span v-if="ticket.category">{{ ticket.category }}</span>
                        <span v-if="ticket.category" aria-hidden="true">·</span>
                        <span class="text-xs" :class="`badge-${ticket.priority_color}`">
                            {{ ticket.priority_label }}
                        </span>
                        <StatusBadge :status="ticket.status" />
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <div v-for="msg in ticket.messages" :key="msg.id" class="card-p" :class="bubbleTone(msg)">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span
                                    class="grid h-7 w-7 place-items-center rounded-full text-xs font-bold"
                                    :class="msg.is_staff ? 'bg-brand-600 text-white' : 'bg-slate-200 text-slate-700'"
                                    aria-hidden="true"
                                >
                                    {{ msg.initial }}
                                </span>
                                <span
                                    class="text-sm font-medium"
                                    :class="msg.is_staff ? 'text-brand-800' : 'text-slate-700'"
                                >
                                    {{ msg.author }}
                                    <span v-if="msg.is_staff" class="badge-brand ml-1">Staff</span>
                                    <span v-if="msg.is_internal" class="badge-amber ml-1">INTERNAL</span>
                                </span>
                            </div>
                            <span class="text-xs text-slate-400">{{ msg.created }}</span>
                        </div>
                        <p class="whitespace-pre-line text-sm text-slate-800">{{ msg.body }}</p>
                        <p v-if="msg.attachment" class="mt-2 text-xs text-brand-600">📎 {{ msg.attachment }}</p>
                    </div>
                    <p v-if="ticket.messages.length === 0" class="card-p text-center text-sm text-slate-500">
                        No messages on this ticket yet.
                    </p>
                </div>

                <!-- Staff reply + internal note -->
                <div v-if="canManage" class="card-p">
                    <h2 class="section-title mb-2">Staff Reply</h2>
                    <form class="flex flex-col gap-2" @submit.prevent="sendReply">
                        <textarea
                            v-model="replyForm.body"
                            rows="4"
                            class="textarea"
                            :class="replyForm.errors.body && 'input-error'"
                            placeholder="Type your reply to the user…"
                        ></textarea>
                        <p v-if="replyForm.errors.body" class="field-error">{{ replyForm.errors.body }}</p>
                        <input
                            type="file"
                            class="input text-sm"
                            accept=".jpg,.jpeg,.png,.pdf,.txt"
                            @input="onFile"
                        />
                        <p v-if="replyForm.errors.attachment" class="field-error">
                            {{ replyForm.errors.attachment }}
                        </p>
                        <button
                            type="submit"
                            class="btn-primary"
                            :disabled="replyForm.processing || !replyForm.body.trim()"
                        >
                            Send reply to user
                        </button>
                    </form>

                    <form
                        class="mt-3 flex flex-col gap-2 border-t border-slate-100 pt-3"
                        @submit.prevent="addNote"
                    >
                        <p class="text-sm font-semibold text-slate-900">
                            🔒 Internal Note <span class="font-normal text-slate-500">(staff only — the user cannot see this)</span>
                        </p>
                        <textarea
                            v-model="noteForm.body"
                            rows="3"
                            class="textarea text-sm"
                            :class="noteForm.errors.body && 'input-error'"
                            placeholder="Internal notes, escalation info, finance queries…"
                        ></textarea>
                        <p v-if="noteForm.errors.body" class="field-error">{{ noteForm.errors.body }}</p>
                        <button
                            type="submit"
                            class="btn-outline btn-sm self-start"
                            :disabled="noteForm.processing || !noteForm.body.trim()"
                        >
                            Add internal note
                        </button>
                    </form>
                </div>
            </div>

            <!-- Sidebar controls -->
            <div class="flex flex-col gap-3">
                <template v-if="canManage">
                    <div class="card-p">
                        <h2 class="section-title mb-2">Assignment</h2>
                        <form
                            class="flex flex-col gap-2"
                            @submit.prevent="submit('post', 'admin.tickets.assign', { assigned_to: assignedTo })"
                        >
                            <select v-model="assignedTo" class="select text-sm">
                                <option value="">Unassigned</option>
                                <option v-for="s in staff" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
                            </select>
                            <button type="submit" class="btn-outline btn-sm w-full" :disabled="processing">
                                Update assignment
                            </button>
                        </form>
                    </div>

                    <div class="card-p">
                        <h2 class="section-title mb-2">Status</h2>
                        <form
                            class="flex flex-col gap-2"
                            @submit.prevent="
                                submit('patch', 'admin.tickets.status', { status, reason: statusReason })
                            "
                        >
                            <select v-model="status" class="select text-sm">
                                <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                            </select>
                            <input v-model="statusReason" class="input text-sm" placeholder="Reason (optional)" />
                            <button type="submit" class="btn-outline btn-sm w-full" :disabled="processing">
                                Change status
                            </button>
                        </form>
                    </div>

                    <div class="card-p">
                        <h2 class="section-title mb-2">Priority</h2>
                        <form
                            class="flex flex-col gap-2"
                            @submit.prevent="submit('patch', 'admin.tickets.priority', { priority })"
                        >
                            <select v-model="priority" class="select text-sm">
                                <option v-for="p in priorities" :key="p.value" :value="p.value">{{ p.label }}</option>
                            </select>
                            <button type="submit" class="btn-outline btn-sm w-full" :disabled="processing">
                                Set priority
                            </button>
                        </form>
                    </div>
                </template>

                <!-- View-only staff still need to see who owns the ticket; for a
                     manager the assignment select above already shows it. -->
                <div v-if="!canManage" class="card-p">
                    <h2 class="section-title mb-2">Assigned To</h2>
                    <p class="text-sm text-slate-700">{{ ticket.assignee_name ?? 'Unassigned' }}</p>
                </div>

                <div class="card-p">
                    <h2 class="section-title mb-2">User</h2>
                    <p class="text-sm font-semibold text-slate-900">{{ ticket.user_name }}</p>
                    <p class="text-xs text-slate-500">{{ ticket.user_email }}</p>
                    <Link
                        v-if="ticket.user_url"
                        :href="ticket.user_url"
                        class="btn-ghost btn-sm mt-2 inline-block"
                    >
                        View user →
                    </Link>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
