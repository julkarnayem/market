<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const form = useForm({
    name: '',
    email: '',
    message: '',
});

function submit() {
    form.post(route('contact.submit'), {
        preserveScroll: true,
        // contactSubmit() redirects back with a success flash, which
        // PublicLayout renders from the shared `flash` prop.
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head title="Contact">
        <meta
            name="description"
            content="Questions about buying, selling, or your wallet? Send the team a message."
        />
    </Head>

    <PublicLayout>
        <div class="mx-auto max-w-xl px-3 py-8 sm:px-4">
            <h1 class="font-display text-3xl font-bold text-slate-900">Contact us</h1>
            <p class="mt-2 text-sm text-slate-600">
                Questions about buying, selling, or your wallet? Send a message.
            </p>

            <div class="card-p mt-6">
                <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
                    <div>
                        <label for="name" class="label">Name</label>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            name="name"
                            autocomplete="name"
                            required
                            class="input"
                            :class="form.errors.name && 'input-error'"
                            :aria-invalid="Boolean(form.errors.name)"
                            :aria-describedby="form.errors.name ? 'name-error' : undefined"
                        />
                        <p v-if="form.errors.name" id="name-error" class="field-error">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label for="email" class="label">Email</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            name="email"
                            autocomplete="email"
                            required
                            class="input"
                            :class="form.errors.email && 'input-error'"
                            :aria-invalid="Boolean(form.errors.email)"
                            :aria-describedby="form.errors.email ? 'email-error' : undefined"
                        />
                        <p v-if="form.errors.email" id="email-error" class="field-error">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label for="message" class="label">Message</label>
                        <textarea
                            id="message"
                            v-model="form.message"
                            name="message"
                            rows="5"
                            required
                            class="textarea"
                            :class="form.errors.message && 'input-error'"
                            :aria-invalid="Boolean(form.errors.message)"
                            :aria-describedby="form.errors.message ? 'message-error' : undefined"
                        ></textarea>
                        <p v-if="form.errors.message" id="message-error" class="field-error">{{ form.errors.message }}</p>
                    </div>

                    <div>
                        <button type="submit" class="btn-primary" :disabled="form.processing">
                            {{ form.processing ? 'Sending…' : 'Send message' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </PublicLayout>
</template>
