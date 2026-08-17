<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

const props = defineProps<{
    /** Not part of the shared auth payload — passed by ProfileController. */
    bio: string | null;
    memberSince: string;
}>();

const user = computed(() => usePage().props.auth.user!);
const initial = computed(() => user.value.name.charAt(0).toUpperCase());

const form = useForm<{
    name: string;
    username: string;
    bio: string;
    // File keeps the request multipart; Inertia spoofs PATCH → POST when set.
    profile_photo: File | null;
}>({
    name: user.value.name,
    username: user.value.username,
    bio: props.bio ?? '',
    profile_photo: null,
});

/**
 * Local object-URL preview. The Blade original wired a previewAvatar() helper
 * against `.avatar-preview`/`.avatar-initials` nodes that this view never
 * rendered, so nothing actually previewed — this restores the intent.
 */
const preview = ref<string | null>(null);
function onPhoto(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    form.profile_photo = file;
    preview.value = file ? URL.createObjectURL(file) : null;
}

const avatarSrc = computed(() => preview.value ?? user.value.avatar);

function submit() {
    form.patch(route('dashboard.profile.update'), { preserveScroll: true });
}
</script>

<template>
    <DashboardLayout title="Profile" heading="My Profile">
        <div class="flex max-w-2xl flex-col gap-3">
            <div class="card-p">
                <h2 class="section-title mb-3">Public Profile</h2>
                <form class="flex flex-col gap-3" novalidate @submit.prevent="submit">
                    <div class="flex items-center gap-3">
                        <img
                            v-if="avatarSrc"
                            :src="avatarSrc"
                            alt="Profile photo"
                            class="h-16 w-16 rounded-2xl object-cover"
                        />
                        <span
                            v-else
                            class="grid h-16 w-16 place-items-center rounded-2xl bg-brand-100 text-2xl font-bold text-brand-700"
                            aria-hidden="true"
                        >{{ initial }}</span>
                        <div>
                            <label class="btn-outline btn-sm cursor-pointer">
                                Change photo
                                <input type="file" accept="image/*" class="sr-only" @change="onPhoto" />
                            </label>
                            <p class="mt-1 text-xs text-slate-500">JPEG, PNG up to 5MB</p>
                            <p v-if="form.errors.profile_photo" class="field-error">{{ form.errors.profile_photo }}</p>
                        </div>
                    </div>

                    <div>
                        <label for="name" class="label">Full name</label>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            required
                            class="input"
                            :class="form.errors.name && 'input-error'"
                        />
                        <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label for="username" class="label">Username</label>
                        <input
                            id="username"
                            v-model="form.username"
                            type="text"
                            required
                            class="input"
                            :class="form.errors.username && 'input-error'"
                        />
                        <p v-if="form.errors.username" class="field-error">{{ form.errors.username }}</p>
                    </div>

                    <div>
                        <label for="bio" class="label">Bio</label>
                        <textarea
                            id="bio"
                            v-model="form.bio"
                            rows="3"
                            class="textarea"
                            placeholder="Tell buyers and sellers about yourself…"
                            :class="form.errors.bio && 'input-error'"
                        ></textarea>
                        <p v-if="form.errors.bio" class="field-error">{{ form.errors.bio }}</p>
                    </div>

                    <button type="submit" class="btn-primary self-start" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : 'Save changes' }}
                    </button>
                </form>
            </div>

            <div class="card-p">
                <h2 class="section-title mb-3">Account Information</h2>
                <dl class="flex flex-col gap-2 text-sm">
                    <div class="flex justify-between border-b border-slate-100 py-2">
                        <dt class="text-slate-500">Email</dt>
                        <dd class="font-medium text-slate-900">{{ user.email }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 py-2">
                        <dt class="text-slate-500">Phone</dt>
                        <dd class="font-medium text-slate-900">{{ user.phone ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between py-2">
                        <dt class="text-slate-500">Member since</dt>
                        <dd class="font-medium text-slate-900">{{ memberSince }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </DashboardLayout>
</template>
